<?php

namespace Larowlan\Tl\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configuration for the logger app.
 */
class LoggerConfiguration implements ConfigurationInterface, ConfigurationCollector {

  /**
   * Array of services to request configuration from.
   *
   * @var \Larowlan\Tl\Configuration\ConfigurableService[]
   *   Services.
   */
  protected $services = [];

  /**
   * Container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder() {
    $tree = new TreeBuilder('tl');
    foreach ($this->services as $service) {
      $service::getConfiguration($tree->getRootNode(), $this->container);
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
