<?php

namespace Larowlan\Tl\Connector;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Larowlan\Tl\Configuration\ConfigurableService;
use Larowlan\Tl\Slot;
use Larowlan\Tl\Ticket;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Redmine backend.
 */
class RedmineConnector implements Connector, ConfigurableService {

  protected $httpClient;
  protected $cache;
  protected $url;
  protected $apiKey;
  protected $nonBillableProjects = [];
  // 7 days cache.
  const LIFETIME = 604800;

  /**
   * Constructs a new RedmineConnector object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   HTTP client.
   * @param \Doctrine\Common\Cache\Cache $cache
   *   Cache service.
   * @param array $config
   *   Config options.
   * @param string $version
   *   Version.
   */
  public function __construct(ClientInterface $httpClient, Cache $cache, array $config, $version) {
    $this->httpClient = $httpClient;
    $this->url = $config['url'];
    $this->apiKey = $config['api_key'];
    $this->nonBillableProjects = array_map(function ($item) {
      return (int) $item;
    }, $config['non_billable_projects'] ?? []);
    $this->cache = $cache;
    $this->version = $version;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAlias($ticket_id, $connectorId) {
    // Not supported.
    return $ticket_id;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketDetails($id, $connectorId, $for_reporting = FALSE) {
    $url = $this->url . '/issues/' . $id . '.xml';
    try {
      if ($xml = $this->fetch($url, $this->apiKey)) {
        $title = $xml->subject . ' (' . $xml->project['name'] . ')';
        if ($for_reporting) {
          $title = $xml->subject;
        }
        $entry = new Ticket(
          $title,
          (string) $xml->project['id'],
          $this->isBillable((string) $xml->project['id'])
        );
        return $entry;
      }
    }
    catch (ConnectException $e) {
      return new Ticket(
        'Offline: please try again later',
        'Offline',
        TRUE
      );
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getName() {
    return 'Redmine';
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCategories() {
    $cid = 'redmine-categories';
    if (($details = $this->cache->fetch($this->version . ':' . $cid))) {
      return $details;
    }
    $url = $this->url . '/enumerations/time_entry_activities.xml';
    if ($xml = $this->fetch($url, $this->apiKey)) {
      $categories = array();
      $i = 1;
      foreach ($xml->time_entry_activity as $node) {
        $categories[(string) str_pad($node->id, 3, 0, STR_PAD_LEFT)] = $node->name . ':' . $node->id;
        $i++;
      }
      $this->cache->save($this->version . ':' . $cid, $categories, static::LIFETIME);
      return $categories;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function sendEntry(Slot $entry) {
    if ((float) $entry->getDuration(FALSE, TRUE) == 0) {
      // Zero time after rounding.
      // Return 0 to ensure doesn't send again.
      return 0;
    }
    $url = $this->url . '/time_entries.xml';
    $details = $this->ticketDetails($entry->getTicketId(), $entry->getConnectorId());
    $data = [
      'issue_id'    => $entry->getTicketId(),
      'project_id'  => $details->getProjectId(),
      'spent_on'    => date('Y-m-d', $entry->getStart()),
      'hours'       => $entry->getDuration(FALSE, TRUE) / 3600,
      'activity_id' => $entry->getCategory(),
      'comments'    => $entry->getComment(),
    ];
    $xml = new \SimpleXMLElement('<?xml version="1.0"?><time_entry></time_entry>');
    foreach ($data as $key => $value) {
      $xml->$key = $value;
    }
    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/xml',
          'X-Redmine-API-Key' => $this->apiKey,
        ],
        'body' => $xml->asXml(),
      ]);
    }
    catch (ConnectException $e) {
      throw new \Exception('You appear to be offline, please retry later.');
    }
    if (in_array(substr($response->getStatusCode(), 0, 1), [2, 3])) {
      $return = new \SimpleXMLElement((string) $response->getBody());
      return (string) $return->id;
    }
    // Try again.
    return NULL;
  }

  /**
   * Fetches an item from redmine.
   *
   * @param string $url
   *   The url to fetch.
   * @param string $redmine_key
   *   Redmine key for request authentication.
   *
   * @return string
   *   The returned object or FALSE if not found.
   */
  protected function fetch($url, $redmine_key) {
    try {
      $result = $this->httpClient->request('GET', $url, [
        'headers' => [
          'X-Redmine-API-Key' => $redmine_key,
        ],
      ]);
    }
    catch (ClientException $e) {
      if ($e->getResponse() && $e->getResponse()->getStatusCode() == 401) {
        throw new \Exception('Authentication error: please check your redmine API key.');
      }
      if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
        // No such ticket.
        return FALSE;
      }
      if ($e->getResponse() && $e->getResponse()->getStatusCode() == 403) {
        // Access denied.
        return FALSE;
      }
      throw $e;
    }
    if ($result->getStatusCode() != 200) {
      return FALSE;
    }
    $xml = simplexml_load_string((string) $result->getBody());
    if (!$xml) {
      return FALSE;
    }
    return $xml;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketUrl($id, $connectorId) {
    return $this->url . '/issues/' . $id;
  }

  /**
   * {@inheritdoc}
   */
  public function assigned($user) {
    $url = $this->url . '/issues.xml?assigned_to_id=' . $user;
    $tickets = [];
    if ($xml = $this->fetch($url, $this->apiKey)) {
      foreach ($xml->issue as $node) {
        $project = (string) $node->project['name'];
        if (!isset($tickets[$project])) {
          $tickets[$project] = [];
        }
        $tickets[(string) $node->project['name']][(string) $node->id] = [
          'title' => (string) $node->subject,
          'status' => (string) $node->status['name'],
        ];
      }

      // Sort by status.
      foreach ($tickets as $project => &$project_issues) {
        uasort($project_issues, function ($a, $b) {
          return strcmp($a['status'], $b['status']);
        });
      }

    }
    if ((int) $xml['total_count'] > (int) $xml['limit']) {
      $tickets['...']['...'] = [
        'title' => sprintf('Showing <info>%s</info> of <info>%s</info>', $xml['limit'], $xml['total_count']),
        'status' => 'Too many Issues!',
      ];
    }
    else {
      $tickets['...'][''] = [
        'title' => sprintf('Showing <info>%s</info> issues', $xml['total_count']),
        'status' => '',
      ];
    }

    return $tickets;
  }

  /**
   * {@inheritdoc}
   */
  public function setInProgress($ticket_id, $connectorId, $assign = FALSE, $comment = 'Working on this') {
    $states = $this->getStates();
    if (!isset($states['In progress'])) {
      throw new \Exception('There is no "In progress" status');
    }
    $updates = ['status_id' => $states['In progress']];
    if ($assign) {
      $updates['assigned_to_id'] = $this->getUserId();
    }
    return $this->putUpdate($ticket_id, $updates, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function assign($ticket_id, $connectorId, $comment = 'Working on this') {
    return $this->putUpdate($ticket_id, ['assigned_to_id' => $this->getUserId()], $comment);
  }

  /**
   * {@inheritdoc}
   */
  protected function putUpdate($ticket_id, array $updates, $comment = 'Working on this') {
    $url = $this->url . '/issues/' . $ticket_id . '.xml';
    $xml = new \SimpleXMLElement('<?xml version="1.0"?><issue></issue>');
    $updates += ['notes' => $comment];
    foreach ($updates as $key => $value) {
      $xml->addChild($key, $value);
    }
    try {
      $response = $this->httpClient->request('PUT', $url, [
        'headers' => [
          'Content-Type' => 'application/xml',
          'X-Redmine-API-Key' => $this->apiKey,
        ],
        'body' => $xml->asXml(),
      ]);
    }
    catch (ConnectException $e) {
      throw new \Exception('You appear to be offline, please retry later.');
    }
    if (in_array(substr($response->getStatusCode(), 0, 1), [2, 3])) {
      return TRUE;
    }
    // Try again.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getStates() {
    $cid = 'redmine-states';
    if (($details = $this->cache->fetch($this->version . ':' . $cid))) {
      return $details;
    }
    $url = $this->url . '/issue_statuses.xml';
    if ($xml = $this->fetch($url, $this->apiKey)) {
      $states = array();
      foreach ($xml->issue_status as $node) {
        $states[(string) $node->name] = (string) $node->id;
      }
      // These don't change regularly - use a longer cache - six months.
      $this->cache->save($this->version . ':' . $cid, $states, static::LIFETIME * 26);
      return $states;
    }
    return FALSE;

  }

  /**
   * {@inheritdoc}
   */
  protected function getUserId() {
    $url = $this->url . '/users/current.xml';
    $cid = 'userid';
    if (($uid = $this->cache->fetch($this->version . ':' . $cid))) {
      return $uid;
    }
    if ($xml = $this->fetch($url, $this->apiKey)) {
      // Cache permanent.
      $uid = (int) $xml->id;
      $this->cache->save($this->version . ':' . $cid, $uid, 0);
      return $uid;
    }
    throw new \Exception('Could not determine your user ID');
  }

  /**
   * {@inheritdoc}
   */
  public function pause($ticket_id, $comment, $connectorId) {
    $states = $this->getStates();
    if (!isset($states['Paused'])) {
      throw new \Exception('There is no "Paused" status');
    }
    $updates = ['status_id' => $states['Paused']];
    return $this->putUpdate($ticket_id, $updates, $comment ?: 'Pausing for moment');
  }

  /**
   * Checks if a project is billable.
   *
   * @param string $project_id
   *   Project ID.
   *
   * @return bool
   *   TRUE if billable.
   */
  protected function isBillable($project_id) {
    return !in_array((int) $project_id, $this->nonBillableProjects, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container) {
    $root_node->children()
      ->arrayNode('non_billable_projects')
      ->requiresAtLeastOneElement()
      ->prototype('scalar')
      ->end()
      ->end()
      ->scalarNode('api_key')
      ->isRequired()
      ->defaultValue('')
      ->end()
      ->scalarNode('url')
      ->defaultValue('https://redmine.previousnext.com.au')
      ->isRequired()
      ->end()
      ->end();
  }

  /**
   * {@inheritdoc}
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container) {
    $default_url = isset($config['url']) ? $config['url'] : 'https://redmine.previousnext.com.au';
    $default_key = isset($config['api_key']) ? $config['api_key'] : '';
    // Reset.
    $config = ['url' => '', 'api_key' => ''] + $config;
    $question = new Question(sprintf('Enter your redmine URL: <comment>[%s]</comment>', $default_url), $default_url);
    $config['url'] = $helper->ask($input, $output, $question) ?: $default_url;
    if (strpos($config['url'], 'https') !== 0) {
      $output->writeln('<comment>It is recommended to use https, POSTING over http is not supported</comment>');
    }
    $question = new Question(sprintf('Enter your redmine API Key: <comment>[%s]</comment>', $default_key), $default_key);
    $config['api_key'] = $helper->ask($input, $output, $question) ?: $default_key;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    $default_non_billable = isset($config['non_billable_projects']) ? $config['non_billable_projects'] : [];
    // Reset.
    $config = ['non_billable_projects' => []] + $config;
    try {
      $options = $this->projectNames();
      $output->writeln('<comment>Bear with us while we configure which Redmine projects are non billable</comment>');
    }
    catch (ConnectException $e) {
      $output->writeln('<error>Could not connect to backend, please check your API key and that you are online</error>');
      return $config;
    }
    catch (\Exception $e) {
      $output->writeln('<error>An error occured trying to connect to the backend, please check your API key and that you are online</error>');
      throw $e;
    }
    foreach ($options as $id => $project) {
      $default = in_array($id, $default_non_billable);
      $question = new ConfirmationQuestion(sprintf('Is the %s project non billable?[%s/%s]', $project, $default ? 'Y' : 'y', $default ? 'n' : 'N'), $default);
      if ($helper->ask($input, $output, $question)) {
        $config['non_billable_projects'][] = $id;
      }
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults($config, ContainerBuilder $container) {
    if (!$config) {
      $config = [];
    }
    $config += ['url' => 'https://redmine.previousnext.com.au', 'api_key' => ''];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function projectNames() {
    $cid = 'redmine-projects';
    if (($details = $this->cache->fetch($this->version . ':' . $cid))) {
      return $details;
    }

    $options = [];
    $limit = 100;
    $offset = 0;
    while ($projects = $this->retrieveProjects($limit, $offset)) {
      $options += $projects;
      $offset += $limit;
    }
    $this->cache->save($this->version . ':' . $cid, $options, static::LIFETIME * 4);
    return $options;
  }

  /**
   * Fetch the projects.
   */
  protected function retrieveProjects($limit, $offset) {
    $options = [];
    $url = sprintf($this->url . '/projects.xml?limit=%s&status=1&offset=%s', $limit, $offset);
    if ($xml = $this->fetch($url, $this->apiKey)) {
      foreach ($xml->project as $node) {
        $options[(int) $node->id] = (string) $node->name;
      }
    }
    return $options;
  }

}
