<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Alias.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Alias extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  public function __construct(Connector $connector, Repository $repository) {
    $this->connector = $connector;
    $this->repository = $repository;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('alias')
      ->setDescription('Creates an alias')
      ->setHelp('Creates an alias')
      ->addOption('delete', NULL, InputOption::VALUE_NONE, 'Delete the given combination')
      ->addArgument('ticket_id', InputArgument::REQUIRED, 'Ticket ID to add an alias for')
      ->addArgument('alias', InputArgument::REQUIRED, 'Alias to use')
      ->addUsage('tl alias 12345 "foobar"');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $alias = $input->getArgument('alias');
    $tid = $input->getArgument('ticket_id');
    if ($input->getOption('delete')) {
      if ($this->repository->removeAlias($tid, $alias)) {
        $output->writeln('Removed alias');
      }
      else {
        $output->writeln('<error>Unable to delete alias</error>');
      }
    }
    else {
      if ($this->repository->addAlias($tid, $alias)) {
        $output->writeln('Created new alias');
      }
      else {
        $output->writeln('<error>Unable to create alias</error>');
      }
    }
  }

}
