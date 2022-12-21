<?php

namespace Larowlan\Tl\Commands;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Update extends Command {

  const MANIFEST_FILE = 'http://larowlan.github.io/tl/manifest.json';

  /**
   *
   */
  protected function configure() {
    $this
      ->setName('self-update')
      ->setDescription('Updates tl to the latest version from larowlan.github.io');
  }

  /**
   *
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
    $manager->update($this->getApplication()->getVersion(), FALSE, TRUE);
    return 0;
  }

}
