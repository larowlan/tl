<?php

namespace Larowlan\Tl\Reporter;

use Doctrine\Common\Cache\Cache;
use Larowlan\Tl\Configuration\ConfigurableService;
use Larowlan\Tl\Ticket;
use MorningTrain\TogglApi\TogglApi;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines a class for reporting entries to toggl.
 */
class Toggl implements Reporter, ConfigurableService {

  /**
   * Cache lifetime of 7 days.
   */
  const LIFETIME = 604800;

  /**
   * Cache.
   *
   * @var \Doctrine\Common\Cache\Cache
   */
  protected $cache;

  /**
   * Version ID.
   *
   * @var string
   */
  protected $version;

  /**
   * Configuration.
   *
   * @var array
   */
  protected $config = [];

  /**
   * Toggle API.
   *
   * @var \MorningTrain\TogglApi\TogglApi
   */
  protected $api;

  /**
   * Constructs a new Toggl.
   *
   * @param array $configuration
   *   Config.
   * @param \Doctrine\Common\Cache\Cache $cache
   *   Cache.
   * @param string $version
   *   App version.
   */
  public function __construct(array $configuration, Cache $cache, $version) {
    $this->cache = $cache;
    $this->config = $configuration;
    $this->version = $version;
    $this->api = new TogglApi($configuration['toggl_token']);
  }

  /**
   * {@inheritdoc}
   */
  public function report($entry, Ticket $details, array $projects, array $categories) {
    $categories = array_reduce($categories, function (array $carry, $item) {
      list($name, $id) = explode(':', $item);
      $carry[$id] = $name;
      return $carry;
    }, []);
    $connector_id = $entry->connector_id;
    list(, $connector_id) = explode('.', $connector_id);
    $project_id = $this->getProjectId($projects[$details->getProjectId()], $connector_id);
    $task_id = $this->getTaskId($entry->tid, $details->getTitle(), $project_id, $connector_id);
    $result = $this->api->createTimeEntry([
      'description' => $entry->comment,
      'tid' => $task_id,
      'start' => date('c', $entry->start),
      'billable' => $details->isBillable(),
      'duration' => $entry->duration * 3600,
      'created_with' => 'tl',
      'tags' => [
        $categories[$entry->category],
      ],
      'duronly' => TRUE,
    ]);
    return $result->id;
  }

  /**
   * Gets the toggl task ID - creating one if needed.
   *
   * @param int $tid
   *   Entry task ID.
   * @param string $name
   *   Task name.
   * @param int $project_id
   *   Toggl task ID.
   * @param string $connector_id
   *   Connector ID.
   *
   * @return int
   *   Toggl task ID.
   */
  protected function getTaskId($tid, $name, $project_id, $connector_id) {
    $workspace_id = $this->config['toggl_workspace'];
    $cid = sprintf('%s:%s:toggl-tasks', $this->version, $workspace_id);
    if (($cache = $this->cache->fetch($cid)) && isset($cache[$project_id][$connector_id][$tid])) {
      return $cache[$project_id][$connector_id][$tid];
    }
    $tasks = $this->api->getProjectTasks($project_id) ?: [];
    $entries = [];
    foreach ($tasks as $task) {
      $matches = [];
      if (preg_match('/(.*) \((?<connector>jira|redmine):(?<id>\d+)\)/', $task->name, $matches)) {
        $entries[$project_id][$matches['connector']][$matches['id']] = $task->id;
      }
    }
    if (isset($entries[$project_id][$connector_id][$tid])) {
      $this->cache->save($cid, $entries, self::LIFETIME);
      return $entries[$project_id][$connector_id][$tid];
    }
    // Create a task.
    $new = $this->api->createTask([
      'pid' => $project_id,
      'name' => sprintf('%s (%s:%s)', $name, $connector_id, $tid),
    ]);
    $entries[$project_id][$connector_id][$tid] = $new->id;
    $this->cache->save($cid, $entries, self::LIFETIME);
    return $new->id;
  }

  /**
   * Gets the toggl project ID.
   *
   * @param string $project_name
   *   Project name.
   * @param string $connector_id
   *   Connector ID.
   *
   * @return int
   *   Project ID.
   */
  protected function getProjectId($project_name, $connector_id) {
    $workspace_id = $this->config['toggl_workspace'];
    $cid = sprintf('%s:%s:toggl-projects', $this->version, $workspace_id);
    if (($cache = $this->cache->fetch($cid)) && isset($cache[$connector_id][$project_name])) {
      return $cache[$connector_id][$project_name];
    }
    $projects = $this->api->getWorkspaceProjects($workspace_id) ?: [];
    $entries = [];
    foreach ($projects as $project) {
      $matches = [];
      if (preg_match('/(?<name>.*) \((?<connector>jira|redmine)\)/', $project->name, $matches)) {
        $entries[$matches['connector']][$matches['name']] = $project->id;
      }
    }
    if (isset($entries[$connector_id][$project_name])) {
      $this->cache->save($cid, $entries, self::LIFETIME);
      return $entries[$connector_id][$project_name];
    }
    // Create a project.
    $new = $this->api->createProject([
      'wid' => $workspace_id,
      'name' => sprintf('%s (%s)', $project_name, $connector_id),
    ]);
    $entries[$connector_id][$project_name] = $new->id;
    $this->cache->save($cid, $entries, self::LIFETIME);
    return $new->id;
  }

  /**
   * {@inheritdoc}
   */
  public static function getName() {
    return 'Toggl';
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container) {
    $root_node->children()
      ->scalarNode('toggl_token')
      ->defaultValue('')
      ->end()
      ->scalarNode('toggl_workspace')
      ->defaultValue('')
      ->end()
      ->end();
  }

  /**
   * {@inheritdoc}
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container) {
    $default_token = isset($config['toggl_token']) ? $config['toggl_token'] : '';
    // Reset.
    $config = [
      'toggl_token' => '',
    ] + $config;
    $question = new Question(sprintf('Enter your Toggl API token: <comment>[%s]</comment>', $default_token), $default_token);
    $question->setValidator(function ($value) {
      if (trim($value) == '') {
        throw new \Exception('The token cannot be empty');
      }

      return $value;
    });
    $question->setHidden(TRUE);
    $config['toggl_token'] = $helper->ask($input, $output, $question) ?: $default_token;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    $default_workspace = isset($config['toggl_workspace']) ? $config['toggl_workspace'] : '';
    // Reset.
    $config = [
      'toggl_workspace' => '',
    ] + $config;
    try {
      $workspaces = $this->api->getWorkspaces();
      $choices = array_map(function ($item) {
        return $item->name . ':' . $item->id;
      }, $workspaces);
    }
    catch (\Exception $e) {
      $output->writeln('<error>Could not connect to Toggl, please check your token and that you are online</error>');
      return $config;
    }
    $question = new ChoiceQuestion(sprintf('Enter the ID of your Toggle workspace: <comment>[%s]</comment>', $default_workspace), array_combine($choices, $choices), $default_workspace);
    $workspace = $helper->ask($input, $output, $question) ?: $default_workspace;
    list(, $id) = explode(':', $workspace);
    $config['toggl_workspace'] = $id;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults($config, ContainerBuilder $container) {
    return $config = [
      'toggl_workspace' => '',
      'toggl_token' => '',
    ];
  }

}
