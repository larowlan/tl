<?php

namespace Larowlan\Tl\Commands;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines an interface for container aware commands.
 */
interface ContainerAwareCommand extends PreinstallCommand {

  /**
   * Some pre-install commands are container aware.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   Container.
   */
  public function setContainerBuilder(ContainerBuilder $container);

}
