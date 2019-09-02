<?php

namespace Larowlan\Tl\Connector;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Transition;
use JiraRestApi\Issue\Worklog;
use JiraRestApi\JiraException;
use JiraRestApi\Project\ProjectService;
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
 * Defines a class for .
 */
class JiraConnector implements Connector, ConfigurableService {

  /**
   * Cache.
   *
   * @var \Doctrine\Common\Cache\Cache
   */
  protected $cache;

  /**
   * {@inheritdoc}
   */
  const JIRA_URL = 'https://jiramigration.cd.pnx.com.au';

  /**
   * Issue service.
   *
   * @var \JiraRestApi\Issue\IssueService
   */
  protected $issueService;

  /**
   * Project service.
   *
   * @var \JiraRestApi\Project\ProjectService
   */
  protected $projectService;

  /**
   * Version ID.
   *
   * @var string
   */
  protected $version;

  /**
   * Jira Username.
   *
   * @var string
   */
  protected $username;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * Non billable project IDs.
   *
   * @var array
   */
  protected $nonBillableProjects = [];

  /**
   * Configuration.
   *
   * @var array
   */
  protected $config = [];

  /**
   * Constructs a new JiraConnector.
   *
   * @param array $configuration
   *   Configuraiton.
   * @param \Doctrine\Common\Cache\Cache $cache
   *   Cache.
   * @param array $config
   *   Config.
   * @param string $version
   *   Version ID.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   HttpClient.
   *
   * @throws \JiraRestApi\JiraException
   *   When cannot connect.
   */
  public function __construct(array $configuration, Cache $cache, array $config, $version, ClientInterface $httpClient) {
    $arrayConfiguration = new ArrayConfiguration([
      'jiraHost' => $configuration['jira_url'],
      'jiraUser' => $configuration['jira_username'],
      'jiraPassword' => $configuration['jira_api_token'],
    ]);
    $this->issueService = new IssueService($arrayConfiguration);
    $this->projectService = new ProjectService($arrayConfiguration);
    $this->cache = $cache;
    $this->config = $configuration;
    $this->userName = $configuration['jira_username'];
    $this->version = $version;
    $this->httpClient = $httpClient;
    $this->nonBillableProjects = array_map(function ($item) {
      return (int) $item;
    }, $config['jira_non_billable_projects'] ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container) {
    $root_node->children()
      ->scalarNode('jira_username')
      ->defaultValue('')
      ->end()
      ->scalarNode('jira_api_token')
      ->defaultValue('')
      ->end()
      ->scalarNode('jira_url')
      ->defaultValue(self::JIRA_URL)
      ->end()
      ->arrayNode('jira_non_billable_projects')
      ->requiresAtLeastOneElement()
      ->prototype('scalar')
      ->end()
      ->end()
      ->end();
  }

  /**
   * {@inheritdoc}
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container) {
    $default_url = isset($config['jira_url']) ? $config['jira_url'] : self::JIRA_URL;
    $default_key = isset($config['jira_api_token']) ? $config['jira_api_token'] : '';
    $default_username = isset($config['jira_username']) ? $config['jira_username'] : '';
    // Reset.
    $config = [
      'jira_url' => '',
      'jira_api_token' => '',
      'jira_username' => '',
    ] + $config;
    $question = new Question(sprintf('Enter your Jira URL: <comment>[%s]</comment>', $default_url), $default_url);
    $config['jira_url'] = $helper->ask($input, $output, $question) ?: $default_url;
    if (strpos($config['jira_url'], 'https') !== 0) {
      $output->writeln('<comment>It is recommended to use https, POSTING over http is not supported</comment>');
    }
    $question = new Question(sprintf('Enter your Jira username: <comment>[%s]</comment>', $default_username), $default_username);
    $config['jira_username'] = $helper->ask($input, $output, $question) ?: $default_username;
    $question = new Question(sprintf('Enter your Jira API token: <comment>[%s]</comment>', $default_key), $default_key);
    $question->setValidator(function ($value) {
      if (trim($value) == '') {
        throw new \Exception('The token cannot be empty');
      }

      return $value;
    });
    $question->setHidden(TRUE);
    $config['jira_api_token'] = $helper->ask($input, $output, $question) ?: $default_key;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    $default_non_billable = isset($config['jira_non_billable_projects']) ? $config['jira_non_billable_projects'] : [];
    // Reset.
    $config = ['jira_non_billable_projects' => []] + $config;
    try {
      $output->writeln('<comment>Bear with us while we configure which Jira projects are non billable</comment>');
      $options = $this->projectNames();
    }
    catch (JiraException $e) {
      $output->writeln('<error>Could not connect to backend, please check your API key and that you are online</error>');
      return $config;
    }
    foreach ($options as $id => $project) {
      $default = in_array($id, $default_non_billable);
      $question = new ConfirmationQuestion(sprintf('Is the %s project non billable?[%s/%s]', $project, $default ? 'Y' : 'y', $default ? 'n' : 'N'), $default);
      if ($helper->ask($input, $output, $question)) {
        $config['jira_non_billable_projects'][] = $id;
      }
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults($config, ContainerBuilder $container) {
    return $config = [
      'jira_url' => static::JIRA_URL,
      'jira_api_token' => '',
      'jira_username' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getName() {
    return 'Jira';
  }

  /**
   * {@inheritdoc}
   */
  public function ticketDetails($id, $connectorId, $for_reporting = FALSE) {
    try {
      $issue = $this->issueService->get($id);
    }
    catch (JiraException $e) {
      try {
        // Check if we're offline.
        $this->httpClient->request('GET', $this->config['jira_url']);
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
    return new Ticket(sprintf('[%s] %s', $issue->key, $issue->fields->summary), $issue->fields->getProjectId(), !in_array((int) $issue->fields->getProjectId(), $this->nonBillableProjects, TRUE));
  }

  /**
   * {@inheritdoc}
   */
  public function loadAlias($ticket_id, $connectorId) {
    if (($details = $this->cache->fetch($this->version . ':alias:' . $ticket_id))) {
      return $details;
    }
    try {
      $issue = $this->issueService->get($ticket_id);
    }
    catch (\Exception $e) {
      return $ticket_id;
    }

    $this->cache->save($this->version . ':alias:' . $ticket_id, $issue->id, Manager::LIFETIME);
    return $issue->id;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCategories() {
    // Jira doesn't require time log entries to be classified.
    return ['Work:1' => 'Work:1'];
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
    $worklog = new Worklog();

    $duration = $entry->getDuration(FALSE, TRUE) / 3600;
    $worklog->setComment($entry->getComment())
      ->setStartedDateTime((new \DateTime('now', new \DateTimeZone('UTC')))->setTimestamp($entry->getStart()))
      ->setTimeSpent(sprintf('%sh %sm', floor($duration), ($duration - floor($duration)) * 60));
    $ret = $this->issueService->addWorklog($entry->getTicketId(), $worklog);
    return $ret->id;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketUrl($id, $connectorId) {
    $id = $this->loadAlias($id, $connectorId);
    $issue = $this->issueService->get($id);
    return sprintf('%s/browse/%s', $this->config['jira_url'], $issue->key);
  }

  /**
   * {@inheritdoc}
   */
  public function assigned($user) {
    $search = $this->issueService->search('assignee = currentUser() and status not in (Resolved, closed, Done)', 0, 25);
    $results = [];
    foreach ($search->getIssues() as $issue) {
      $results += [$issue->fields->project->name => []];
      $results[$issue->fields->project->name][$issue->id] = [
        'title' => sprintf('[%s] %s', $issue->key, $issue->fields->summary),
        'status' => $issue->fields->status->name,
      ];
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function setInProgress($ticket_id, $connectorId, $assign = FALSE, $comment = 'Working on this') {
    $transition = new Transition();
    $transition->setTransitionName('In Progress');
    $transition->setCommentBody($comment);
    $this->issueService->transition($ticket_id, $transition);
    if ($assign) {
      $this->issueService->changeAssignee($ticket_id, $this->userName);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function assign($ticket_id, $connectorId, $comment = 'Working on this') {
    $this->issueService->changeAssignee($ticket_id, $this->userName);
  }

  /**
   * {@inheritdoc}
   */
  public function pause($ticket_id, $comment, $connectorId) {
    // No paused status.
  }

  /**
   * {@inheritdoc}
   */
  public function projectNames() {
    $cid = 'jira-projects';
    if (($details = $this->cache->fetch($this->version . ':' . $cid))) {
      return $details;
    }
    $projects = $this->projectService->getAllProjects();
    $return = [];
    foreach ($projects as $project) {
      $return[$project->id] = $project->name;
    }
    $this->cache->save($this->version . ':' . $cid, $return, Manager::LIFETIME * 4);
    return $return;
  }

}
