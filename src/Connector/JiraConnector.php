<?php

namespace Larowlan\Tl\Connector;

use Doctrine\Common\Cache\Cache;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\JqlQuery;
use JiraRestApi\Issue\Transition;
use JiraRestApi\Issue\Worklog;
use JiraRestApi\Project\ProjectService;
use Larowlan\Tl\Configuration\ConfigurableService;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Ticket;
use Larowlan\Tl\TicketInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines a class for .
 */
class JiraConnector implements Connector, ConfigurableService {

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

  protected $version;
  protected $username;

  /**
   * Constructs a new JiraConnector.
   *
   * @param array $configuration
   *   Configuraiton.
   * @param string $version
   *   Version ID.
   *
   * @throws \JiraRestApi\JiraException
   *   When cannot connect.
   */
  public function __construct(array $configuration, Cache $cache, array $config, $version) {
    $arrayConfiguration = new ArrayConfiguration([
      'jiraHost' => $configuration['jira_url'],
      'jiraUser' => $configuration['jira_username'],
      'jiraPassword' => $configuration['jira_api_token'],
    ]);
    $this->issueService = new IssueService($arrayConfiguration);
    $this->projectService = new ProjectService($arrayConfiguration);
    $this->cache = $cache;
    $this->userName = $configuration['jira_username'];
    $this->version = $version;
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
    $config['jira_api_token'] = $helper->ask($input, $output, $question) ?: $default_key;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
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
   * Fetch the details of a ticket from a remote ticketing system.
   *
   * @param int $id
   *   The ticket id from the remote system.
   *
   * @return TicketInterface
   *   Ticket object.
   */
  public function ticketDetails($id) {
    $issue = $this->issueService->get($id);
    // @todo work out if ticket is billable.
    return new Ticket(sprintf('[%s] %s', $issue->key, $issue->fields->summary), $issue->fields->getProjectId(), TRUE);
  }

  public function loadAlias($ticket_id) {
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
  public function sendEntry($entry) {
    if ((float) $entry->duration == 0) {
      // Zero time after rounding.
      // Return 0 to ensure doesn't send again.
      return 0;
    }
    $worklog = new Worklog();

    $worklog->setComment($entry->comment)
      ->setStarted(date('Y-m-d h:m:s', $entry->start))
      ->setTimeSpent(sprintf('%sh %sm', floor($entry->duration), ($entry->duration - floor($entry->duration)) * 60));
    $ret = $this->issueService->addWorklog($entry->tid, $worklog);
    return $ret->id;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketUrl($id) {
    $id = $this->loadAlias($id);
    $issue = $this->issueService->get($id);
    return sprintf('%s/browse/%s', self::JIRA_URL, $issue->key);
  }

  /**
   * {@inheritdoc}
   */
  public function assigned($user) {
    $search = $this->issueService->search('assignee = currentUser() and status not in (Resolved, closed, Done)', 0, 25);
    $results = [];
    foreach ($search->getIssues() as $issue) {
      $results += [$issue->fields->getProjectId() => []];
      $results[$issue->fields->getProjectId()][$issue->id] = [
        'title' => sprintf('[%s] %s', $issue->key, $issue->fields->summary),
        'status' => $issue->fields->status->name,
      ];
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function setInProgress($ticket_id, $assign = FALSE, $comment = 'Working on this') {
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
  public function assign($ticket_id, $comment = 'Working on this') {
    $this->issueService->changeAssignee($ticket_id, $this->userName);
  }

  /**
   * {@inheritdoc}
   */
  public function pause($ticket_id, $comment) {
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
