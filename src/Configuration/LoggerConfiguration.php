<?php
/**
 * @file
 * Contains LoggerConfiguration.php
 */

namespace Larowlan\Tl\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class LoggerConfiguration implements ConfigurationInterface, ConfigurationCollector {

  /**
   * Array of services to request configuration from.
   *
   * @var ConfigurableService[]
   */
  protected $services = [];

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder() {
    $tree = new TreeBuilder();
    $root = $tree->root('tl');
    foreach ($this->services as $service) {
      $service::getConfiguration($root);
    }
    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurableServices(array $class_names) {
    $this->services = $class_names;
  }

}
