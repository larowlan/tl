<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Connector\Manager;
use Larowlan\Tl\DurationFormatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Combine extends Command {

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
      ->setName('combine')
      ->setDescription('Combine two time entries')
      ->setHelp('Combine two time entries. <comment>Usage:</comment> <info>tl combine slot1 slot2</info>')
      ->addUsage('tl combine slot1 slot2')
      ->addArgument('slot1', InputArgument::REQUIRED)
      ->addArgument('slot2', InputArgument::REQUIRED);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $slot1 = $input->getArgument('slot1');
    $slot2 = $input->getArgument('slot2');

    /** @var \Larowlan\Tl\Slot $entry1 */
    /** @var \Larowlan\Tl\Slot $entry2 */
    [$entry1, $entry2] = $this->validateSlots($slot1, $slot2, $output);
    $this->repository->combine($entry1, $entry2);

    $output->writeln(sprintf('Combined %s and %s into new slot %s', $slot1, $slot2, $slot1));
    return 0;
  }

  /**
   *
   */
  protected function validateSlots($slot1, $slot2, OutputInterface $output) {
    if ($slot1 === $slot2) {
      throw new \InvalidArgumentException('You cannot combine a slot with itself.');
    }
    // Stop any open tickets to ensure they get an end time.
    $this->stopTicket($slot1, $output);
    // Ensure we can load both slots.
    if (!$entry1 = $this->repository->slot($slot1)) {
      throw new \InvalidArgumentException(sprintf('Invalid slot id %s', $slot1));
    }
    // Stop any open tickets to ensure they get an end time.
    $this->stopTicket($slot2, $output);
    if (!$entry2 = $this->repository->slot($slot2)) {
      throw new \InvalidArgumentException(sprintf('Invalid slot id %s', $slot2));
    }
    if ($entry1->getConnectorId() !== $entry2->getConnectorId()) {
      throw new \InvalidArgumentException(sprintf('You cannot combine slots from %s backend with slots from %s backend', Manager::formatConnectorId($entry2->getConnectorId()), Manager::formatConnectorId($entry1->getConnectorId())));
    }
    // Ensure we've not already sent the slots.
    if (!empty($entry1->getRemoteEntryId()) || !empty($entry1->getRemoteEntryId())) {
      throw new \InvalidArgumentException('You cannot combine entries that have already been sent.');
    }
    // Ensure the slots are both against the same job.
    if ($entry1->getTicketId() != $entry2->getTicketId()) {
      throw new \InvalidArgumentException('You cannot combine entries from separate issues.');
    }
    return [$entry1, $entry2];
  }

  /**
   *
   */
  protected function stopTicket($slot_id, OutputInterface $output) {
    if ($stop = $this->repository->stop($slot_id)) {
      $stopped = $this->connector->ticketDetails($stop->getTicketId(), $stop->getConnectorId());
      $output->writeln(sprintf('Closed slot <comment>%d</comment> against ticket <info>%d</info>: %s, duration <info>%s</info>',
        $stop->getId(),
        $stop->getTicketId(),
        $stopped->getTitle(),
        DurationFormatter::formatDuration($stop->getDuration())
      ));
    }
  }

}
