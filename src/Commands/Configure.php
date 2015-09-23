<?php

/**
 * @file
 * Contains \Larowlan\Tl\Commands\Configure.php
 */
namespace Larowlan\Tl\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines a class for configuring the app.
 */
class Configure extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('configure')
      ->setDescription('Configure your time logger');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $output->writeln('Configure would ask some questions.');
    $output->writeln('Finished');
  }

}
