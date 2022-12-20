<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\LogHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Log extends Command {

  /**
   * Installed directory.
   *
   * @var string
   */
  protected $directory;

  /**
   *
   */
  public function __construct($directory) {
    $this->directory = $directory;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('log')
      ->setDescription('Shows the log entries')
      ->setHelp('Shows log entries. <comment>Usage:</comment> <info>tl log [records]</info>')
      ->addArgument('records', InputArgument::OPTIONAL, 'Number of records', 10)
      ->addUsage('tl log 100');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $records = $input->getArgument('records');
    $output->writeln(LogHelper::tail($this->directory . '/.tl.log', $records));
    return 0;
  }

}
