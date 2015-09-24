<?php
/**
 * @file
 * Contains Application.php
 */

namespace Larowlan\Tl;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Application extends BaseApplication {
  protected $container;

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN', ContainerBuilder $container) {
    parent::__construct($name, $version);
    $this->container = $container;
    foreach ($this->container->findTaggedServiceIds('command') as $service_id => $tags) {
      $this->add($container->get($service_id));
    }
  }

}
