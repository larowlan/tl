<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Continues.php
 */

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Continues extends Command implements LogAwareCommand {

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
      ->setName('continue')
      ->setDescription('Continuess the active time entry')
      ->addArgument('slot_id', InputArgument::OPTIONAL, 'Optional slot ID')
      ->addUsage('tl continue')
      ->addUsage('tl continues 123')
      ->setHelp('Continues the exiting entry. <comment>Usage:</comment> <info>tl continue</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($slot_id = $input->getArgument('slot_id')) {
      $slot = $this->repository->slot($slot_id);
    }
    else {
      $slot = $this->repository->latest();
    }
    if ($slot) {
      $details = $this->connector->ticketDetails($slot->tid);
      list($slot_id, $continued) = $this->repository->start($slot->tid, $slot->comment, $slot->id);
      $output->writeln(sprintf('<bg=blue;fg=white;options=bold>[%s]</> <comment>%s</comment> entry for <info>%d</info>: %s [slot:<comment>%d</comment>]',
        (new \DateTime())->format('h:i'),
        $continued ? 'Continued' : 'Started new',
        $slot->tid,
        $details->getTitle(),
        $slot_id
      ));

      return;
    }
    $output->writeln('<error>Could not find the slot to continue</error>');
  }

}
