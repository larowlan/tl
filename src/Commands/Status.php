<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Status.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Command {

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
      ->setName('status')
      ->setDescription('Shows detailed entries for a given date')
      ->setHelp('Shows time logged by ticket for a given date. <comment>Usage:</comment> <info>tl status [optional date]</info>')
      ->addArgument('date', InputArgument::OPTIONAL, 'Date in ISO format e.g. 2015-12-31');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $data = $this->repository->status($input->getArgument('date'));
    $table = new Table($output);
    $table->setHeaders(['Slot', 'JobId', 'Time', 'Title']);
    $rows = [];
    $total = 0;
    foreach ($data as $record) {
      $total += $record->duration;
      $details = $this->connector->ticketDetails($record->tid);
      if (!empty($record->active)) {
        $record->tid .= ' *';
      }
      $rows[] = [$record->id, $record->tid, Formatter::formatDuration($record->duration), $details['title']];
    }
    $rows[] = new TableSeparator();
    $rows[] = ['', '<comment>Total</comment>', '<info>' . Formatter::formatDuration($total) . '</info>', ''];
    $table->setRows($rows);
    $table->render();
  }

}
