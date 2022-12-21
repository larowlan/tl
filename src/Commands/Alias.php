<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Alias extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   *
   */
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
      ->addOption('list', NULL, InputOption::VALUE_NONE, 'List aliases')
      ->addArgument('ticket_id', InputArgument::OPTIONAL, 'Ticket ID to add an alias for')
      ->addArgument('alias', InputArgument::OPTIONAL, 'Alias to use')
      ->addUsage('tl alias 12345 "foobar"')
      ->addUsage('tl alias --list')
      ->addUsage('tl alias 12345 "foobar" --delete');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('list')) {
      $table = new Table($output);
      $table->setHeaders(['Alias', 'Issue number']);
      $list = $this->repository->listAliases();
      $rows = [];
      foreach ($list as $alias) {
        $rows[] = [
          $alias->alias,
          $alias->tid,
        ];
      }
      $table->setRows($rows);
      $table->render();
      return 0;
    }
    $alias = $input->getArgument('alias');
    $tid = $input->getArgument('ticket_id');
    if ($input->getOption('delete')) {
      if ($this->repository->removeAlias($tid, $alias)) {
        $output->writeln('Removed alias');
      }
      else {
        $output->writeln('<error>Unable to delete alias</error>');
        return 1;
      }
    }
    else {
      if (!$alias) {
        $output->writeln('<error>Missing alias</error>');
        return 1;
      }
      if (!$tid) {
        $output->writeln('<error>Missing ticket number</error>');
        return 1;
      }
      if ($this->repository->addAlias($tid, $alias)) {
        $output->writeln('Created new alias');
      }
      else {
        $output->writeln('<error>Unable to create alias</error>');
        return 1;
      }
    }

    return 0;
  }

}
