<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 *
 */
class MostFrequentlyUsed extends Command {

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
      ->setName('frequent')
      ->setDescription('View most frequently used issues.')
      ->setHelp('Shows frequent issues. <comment>Usage:</comment> <info>tl frequent</info>')
      ->addUsage('tl frequent');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($entries = $this->repository->frequent()) {
      $table = new Table($output);
      $table->setHeaders(['JobId', 'Title']);

      $rows = [];
      foreach ($entries as $entry) {
        if (!$details = $this->connector->ticketDetails($entry->tid, $entry->connector_id)) {
          continue;
        }
        $rows[] = [$entry->tid, $details->getTitle()];
      }
      $table->setRows($rows);
      $table->render();
    }
  }

}
