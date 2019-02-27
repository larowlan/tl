<?php

namespace Larowlan\Tl\Configuration;

/**
 * Defines an interface for a configuration collector.
 */
interface ConfigurationCollector {

  /**
   * Sets configurable services on the configuration collector.
   *
   * @param string[] $class_names
   *   Class names.
   */
  public function setConfigurableServices(array $class_names);

}
