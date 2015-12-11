<?php
/**
 * @file
 * Contains \Larowlan\Tl\Configuration\ConfigurationCollector.
 */

namespace Larowlan\Tl\Configuration;

/**
 * Defines an interface for a configuration collector.
 */
interface ConfigurationCollector {

  /**
   * Sets configurable services on the configuration collector.
   *
   * @param string[] $class_names
   */
  public function setConfigurableServices(array $class_names);

}
