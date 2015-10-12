<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Start.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Start extends Command {

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
      ->setName('start')
      ->setDescription('Starts a time entry')
      ->setHelp('Starts a new entry, closes existing one. <comment>Usage:</comment> <info>tl start [ticket number]</info>')
      ->addArgument('issue_number', InputArgument::REQUIRED, 'Issue number to start work on')
      ->addArgument('comment', InputArgument::OPTIONAL, 'Comment to start with')
      ->addUsage('tl start 12355')
      ->addUsage('tl start 12355 "Doin stuff"');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $ticket_id = $input->getArgument('issue_number');
    if ($alias = $this->repository->loadAlias($ticket_id)) {
      $ticket_id = $alias;
    }
    if ($title = $this->connector->ticketDetails($ticket_id)) {
      if ($stop = $this->repository->stop()) {
        $stopped = $this->connector->ticketDetails($stop->tid);
        $output->writeln(sprintf('Closed slot <comment>%d</comment> against ticket <info>%d</info>: %s, duration <info>%s</info>',
          $stop->id,
          $stop->tid,
          $stopped['title'],
          Formatter::formatDuration($stop->duration)
        ));
      }
      try {
        list($slot_id, $continued) = $this->repository->start($ticket_id, $input->getArgument('comment'));
        $output->writeln(sprintf('<bg=blue;fg=white;options=bold>[%s]</> <comment>%s</comment> entry for <info>%d</info>: %s [slot:<comment>%d</comment>]',
          (new \DateTime())->format('h:i'),
          $continued ? 'Continued' : 'Started new',
          $ticket_id,
          $title['title'],
          $slot_id
        ));
      }
      catch (\Exception $e) {
        $output->writeln(sprintf('<error>Error creating slot: %s</error>',  $e->getMessage()));
      }
    }
    else {
      $output->writeln('<error>Error: no such ticket id or access denied</error>');
    }
  }

}
