<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Start.php
 */

namespace Larowlan\Tl\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Start extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('start')
      ->setDescription('Starts a time entry')
      ->addArgument('issue_number', InputArgument::REQUIRED, 'Issue number to start work on');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $output->writeln(sprintf('Started new entry for <info>%s.</info>', $input->getArgument('issue_number')));
  }

}
