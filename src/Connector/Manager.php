<?php

namespace Larowlan\Tl\Connector;

use Doctrine\Common\Cache\Cache;
use Larowlan\Tl\Configuration\ConfigurableService;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines a class for managing connectors.
 */
class Manager implements Connector, ConfigurableService {
  // 7 days cache.
  const LIFETIME = 604800;

  /**
   * Active connector.
   *
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * Connector ID.
   *
   * @var string
   */
  protected $connectorId;

  protected $cache;
  protected $version;
  /**
   * {@inheritdoc}
   */
  public function __construct(ContainerBuilder $container, Cache $cache, array $config, $version) {
    if (!empty($config['connector_id'])) {
      $connector_id = $config['connector_id'];
      $this->connector = $container->get($connector_id);
    }
    $this->cache = $cache;
    $this->version = $version;
  }

  /**
   * Loads the active connector.
   *
   * @return \Larowlan\Tl\Connector\Connector
   *   Connector.
   */
  protected function connector() {
    if (!$this->connector) {
      throw new \InvalidArgumentException('No active backend connector');
    }
    return $this->connector;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketDetails($id) {
    if (($details = $this->cache->fetch($this->version . ':' . $id))) {
      return $details;
    }
    $ticket = $this->connector()->ticketDetails($id);
    $this->cache->save($this->version . ':' . $id, $ticket, static::LIFETIME);
    return $ticket;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCategories() {
    return $this->connector()->fetchCategories();
  }

  /**
   * {@inheritdoc}
   */
  public function sendEntry($entry) {
    return $this->connector()->sendEntry($entry);
  }

  /**
   * {@inheritdoc}
   */
  public function ticketUrl($id) {
    return $this->connector()->ticketUrl($id);
  }

  /**
   * {@inheritdoc}
   */
  public function assigned($user) {
    return $this->connector()->assigned($user);
  }

  /**
   * {@inheritdoc}
   */
  public function setInProgress($ticket_id, $assign = FALSE, $comment = 'Working on this') {
    return $this->connector()->setInProgress($ticket_id, $assign, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function assign($ticket_id, $comment = 'Working on this') {
    return $this->connector()->assign($ticket_id, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function pause($ticket_id, $comment) {
    return $this->connector()->pause($ticket_id, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function projectNames() {
    return $this->connector()->projectNames();
  }

  /**
   * {@inheritdoc}
   */
  public function loadAlias($ticket_id) {
    return $this->connector()->loadAlias($ticket_id);
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
      ->scalarNode('connector_id')
      ->defaultValue('connector.redmine')
      ->end()
    ->end();
    return $root_node;
  }

  /**
   * {@inheritdoc}
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container) {
    $connectorIds = array_keys($container->findTaggedServiceIds('connector'));
    $question = new ChoiceQuestion(
      'Which backend do you want to use',
      array_combine($connectorIds, array_map('ucfirst', $connectorIds)),
      $config['connector_id']
    );
    $config['connector_id'] = $helper->ask($input, $output, $question);
    if (!in_array($config['connector_id'], $connectorIds)) {
      throw new InvalidConfigurationException('You must select a valid backend');
    }
    $definition = $container->getDefinition($config['connector_id']);
    $class = $definition->getClass();
    $config = $class::askPreBootQuestions($helper, $input, $output, $config, $container) + $config;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    $this->connector()->askPostBootQuestions($helper, $input, $output, $config);
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
    $config['connector_id'] = 'connector.redmine';
    return $config;
  }

}
