<?php

namespace Larowlan\Tl\Reporter;

use Doctrine\Common\Cache\Cache;
use Larowlan\Tl\Configuration\ConfigurableService;
use Larowlan\Tl\Slot;
use Larowlan\Tl\TicketInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines a class for managing reporters.
 */
class Manager implements ConfigurableService, Reporter {

  /**
   * Reporters keyed by ID.
   *
   * @var \Larowlan\Tl\Reporter\Reporter[]
   */
  protected $reporters = [];

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
   * {@inheritdoc}
   */
  public function __construct(ContainerBuilder $container, Cache $cache, array $config, $version) {
    if (!empty($config['reporter_ids'])) {
      foreach ($config['reporter_ids'] as $id) {
        $this->reporters[$id] = $container->get($id);
      }
    }
    $this->cache = $cache;
    $this->version = $version;
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container) {
    foreach (array_keys($container->findTaggedServiceIds('reporter')) as $id) {
      $definition = $container->getDefinition($id);
      $class = $definition->getClass();
      $class::getConfiguration($root_node, $container);
    }
    $root_node->children()
      ->arrayNode('reporter_ids')
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
    $connectorIds = array_keys($container->findTaggedServiceIds('reporter'));
    $activeIds = [];
    foreach ($connectorIds as $id) {
      $definition = $container->getDefinition($id);
      $class = $definition->getClass();
      $name = call_user_func([$class, 'getName']);
      $default = in_array($id, $config['reporter_ids']);
      $question = new ConfirmationQuestion(sprintf('Do you want to use the %s reporter?[%s/%s]', $name, $default ? 'Y' : 'y', $default ? 'n' : 'N'), $default);
      if ($helper->ask($input, $output, $question)) {
        $activeIds[] = $id;
        $config = $class::askPreBootQuestions($helper, $input, $output, $config, $container) + $config;
      }
    }
    $config['reporter_ids'] = $activeIds;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    foreach ($this->reporters as $reporter) {
      $config = $reporter->askPostBootQuestions($helper, $input, $output, $config);
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults($config, ContainerBuilder $container) {
    foreach (array_keys($container->findTaggedServiceIds('reporter')) as $id) {
      $definition = $container->getDefinition($id);
      $class = $definition->getClass();
      $class::getDefaults($config, $container);
    }
    $config['reporter_ids'] = [];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function report(Slot $entry, TicketInterface $details, array $projects, array $categories) {
    $return = TRUE;
    foreach ($this->reporters as $reporter) {
      $return = $return && $reporter->report($entry, $details, $projects, $categories);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public static function getName() {
    return 'Manager';
  }

}
