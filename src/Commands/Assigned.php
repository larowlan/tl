<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Assigned extends Command {

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
      ->setName('assigned')
      ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Specify the user Id for which to retrieve the assigned tickets')
      ->setDescription('Shows asssigned stories')
      ->setHelp('Shows assigned stories. <comment>Usage:</comment> <info>tl assigned</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($data = $this->connector->assigned($input->getOption('user') ?: 'me')) {
      $table = new Table($output);
      $table->setHeaders(['JobId', 'Title', 'Status']);
      $rows = [];
      $first = TRUE;
      foreach ($data as $connector_id => $connector_data) {
        foreach ($connector_data as $project => $tickets) {
          if (!$first) {
            $rows[] = new TableSeparator();
          }
          $rows[] = [
            '',
            sprintf('<comment>%s [%s]</comment>', $project, $connector_id),
          ];
          $rows[] = new TableSeparator();
          foreach ($tickets as $id => $ticket_info) {
            $rows[] = [
              $id,
              substr($ticket_info['title'], 0, 50) . '...',
              $ticket_info['status'],
            ];
          }
          $first = FALSE;
        }
      }
      $table->setRows($rows);
      $table->render();
      return 0;
    }
    $output->writeln('<error>No assigned tickets.</error>');
    return 1;
  }

}
