<?php
/**
 * @file
 * Contains LoggerConfiguration.php
 */

namespace Larowlan\Tl\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LoggerConfiguration implements ConfigurationInterface, ConfigurationCollector {

  /**
   * Array of services to request configuration from.
   *
   * @var ConfigurableService[]
   */
  protected $services = [];

  /**
   * @var ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder() {
    $tree = new TreeBuilder();
    $root = $tree->root('tl');
    foreach ($this->services as $service) {
      $service::getConfiguration($root, $this->container);
    }
    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurableServices(array $class_names) {
    $this->services = $class_names;
  }

  /**
   * {@inheritdoc}
   */
  public function setContainerBuilder(ContainerBuilder $container) {
    $this->container = $container;
  }

}
