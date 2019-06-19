<?php

namespace Larowlan\Tl\Connector;

use Doctrine\Common\Cache\Cache;
use Larowlan\Tl\Configuration\ConfigurableService;
use Larowlan\Tl\Reporter\Manager as RepoterManager;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines a class for managing connectors.
 */
class Manager implements ConfigurableService, ConnectorManager {

  // 7 days cache.
  const LIFETIME = 604800;

  /**
   * Connectors keyed by ID.
   *
   * @var \Larowlan\Tl\Connector\Connector[]
   */
  protected $connectors;

  /**
   * Cache backend.
   *
   * @var \Doctrine\Common\Cache\Cache
   */
  protected $cache;

  /**
   * Application version, used in cache IDs.
   *
   * @var int
   */
  protected $version;

  /**
   * Reporter.
   *
   * @var \Larowlan\Tl\Reporter\Manager
   */
  private $reporter;

  /**
   * {@inheritdoc}
   */
  public static function getName() {
    return 'Manager';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ContainerBuilder $container, Cache $cache, array $config, $version, RepoterManager $reporter) {
    if (!empty($config['connector_ids'])) {
      foreach ($config['connector_ids'] as $id) {
        $this->connectors[$id] = $container->get($id);
      };
    }
    $this->cache = $cache;
    $this->version = $version;
    $this->reporter = $reporter;
  }

  /**
   * Loads the given connector.
   *
   * @param string $connector_id
   *   Connector ID.
   *
   * @return \Larowlan\Tl\Connector\Connector
   *   Connector.
   */
  protected function connector($connector_id) {
    if (!$this->connectors[$connector_id]) {
      throw new \InvalidArgumentException('No such backend connector');
    }
    return $this->connectors[$connector_id];
  }

  /**
   * {@inheritdoc}
   */
  public function spotConnector($id, InputInterface $input, OutputInterface $output) {
    if ((!$backends = $this->cache->fetch($this->version . ':resolve_connector:' . $id))) {
      // Ask each connector.
      $backends = [];
      foreach ($this->connectors as $connector_id => $connector) {
        if ($connector->ticketDetails($id, $connector_id)) {
          list(, $connector_id) = explode('.', $connector_id);
          $backends[$connector_id] = call_user_func([get_class($connector), 'getName']);
        }
      }
      // Cache result.
      $this->cache->save($this->version . ':resolve_connector:' . $id, $backends, static::LIFETIME);
    }
    if (!$backends) {
      return FALSE;
    }
    if (count($backends) == 1) {
      return 'connector.' . key($backends);
    }
    // Ask for backend.
    $question = new ChoiceQuestion(
      'The given ticket ID is found in more than one backend - which backend do you want to use',
      $backends,
      key($backends)
    );
    $helper = new QuestionHelper();
    return 'connector.' . $helper->ask($input, $output, $question);
  }

  /**
   * {@inheritdoc}
   */
  public function ticketDetails($id, $connectorId) {
    if (($details = $this->cache->fetch($this->version . ':' . $connectorId . ':' . $id))) {
      return $details;
    }
    $ticket = $this->connector($connectorId)->ticketDetails($id, $connectorId);
    $this->cache->save($this->version . ':' . $connectorId . ':' . $id, $ticket, static::LIFETIME);
    return $ticket;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCategories() {
    $categories = [];
    foreach ($this->connectors as $id => $connector) {
      $categories[$id] = $connector->fetchCategories();
    }
    return $categories;
  }

  /**
   * {@inheritdoc}
   */
  public function sendEntry($entry) {
    $connector = $this->connector($entry->connector_id);
    if ($sendEntry = $connector->sendEntry($entry)) {
      $details = $connector->ticketDetails($entry->tid, $entry->connector_id);
      $projects = $connector->projectNames();
      $categories = $connector->fetchCategories();
      return $sendEntry && $this->reporter->report($entry, $details, $projects, $categories);
    }
    return $sendEntry;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketUrl($id, $connectorId) {
    return $this->connector($connectorId)->ticketUrl($id, $connectorId);
  }

  /**
   * {@inheritdoc}
   */
  public function assigned($user) {
    $assigned = [];
    foreach ($this->connectors as $id => $connector) {
      $name = call_user_func([get_class($connector), 'getName']);
      $assigned[$name] = $connector->assigned($user);
    }
    return $assigned;
  }

  /**
   * {@inheritdoc}
   */
  public function setInProgress($ticket_id, $connectorId, $assign = FALSE, $comment = 'Working on this') {
    return $this->connector($connectorId)->setInProgress($ticket_id, $connectorId, $assign, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function assign($ticket_id, $connectorId, $comment = 'Working on this') {
    return $this->connector($connectorId)->assign($ticket_id, $connectorId, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function pause($ticket_id, $comment, $connectorId) {
    return $this->connector($connectorId)->pause($ticket_id, $comment, $connectorId);
  }

  /**
   * {@inheritdoc}
   */
  public function projectNames() {
    $projectNames = [];
    foreach ($this->connectors as $id => $connector) {
      $projectNames[$id] = $connector->projectNames();
    }
    return $projectNames;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAlias($ticket_id, $connectorId) {
    return $this->connector($connectorId)->loadAlias($ticket_id, $connectorId);
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container) {
    foreach (array_keys($container->findTaggedServiceIds('connector')) as $id) {
      $definition = $container->getDefinition($id);
      $class = $definition->getClass();
      $class::getConfiguration($root_node, $container);
    }
    $root_node->children()
      ->arrayNode('connector_ids')
      ->requiresAtLeastOneElement()
      ->prototype('scalar')
      ->end()
      ->end()
      ->end();
    return $root_node;
  }

  /**
   * {@inheritdoc}
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container) {
    $connectorIds = array_keys($container->findTaggedServiceIds('connector'));
    $activeIds = [];
    foreach ($connectorIds as $id) {
      $definition = $container->getDefinition($id);
      $class = $definition->getClass();
      $name = call_user_func([$class, 'getName']);
      $default = in_array($id, $config['connector_ids']);
      $question = new ConfirmationQuestion(sprintf('Do you want to use the %s backend?[%s/%s]', $name, $default ? 'Y' : 'y', $default ? 'n' : 'N'), $default);
      if ($helper->ask($input, $output, $question)) {
        $activeIds[] = $id;
        $config = $class::askPreBootQuestions($helper, $input, $output, $config, $container) + $config;
      }
    }
    if (empty($activeIds)) {
      throw new InvalidConfigurationException('You must select at least one backend');
    }
    $config['connector_ids'] = $activeIds;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    foreach ($this->connectors as $connector) {
      $config = $connector->askPostBootQuestions($helper, $input, $output, $config);
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults($config, ContainerBuilder $container) {
    foreach (array_keys($container->findTaggedServiceIds('connector')) as $id) {
      $definition = $container->getDefinition($id);
      $class = $definition->getClass();
      $class::getDefaults($config, $container);
    }
    $config['connector_ids'] = [];
    return $config;
  }

  /**
   * Format conenctor id.
   *
   * @param string $connector_id
   *   Connector ID in connector.{id} format.
   *
   * @return string
   *   Connector ID with 'connector.' prefix stripped.
   */
  public static function formatConnectorId($connector_id) {
    if (strpos($connector_id, 'connector.') !== 0) {
      return $connector_id;
    }
    return explode('.', $connector_id)[1];
  }

}
