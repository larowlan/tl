<?php

declare(strict_types=1);

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\DurationFormatter;
use Larowlan\Tl\Repository\Repository;
use Larowlan\Tl\Ticket;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines a class for show commad.
 */
final class Show extends Command {

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
      ->setName('show')
      ->setDescription('Shows detailed entry for a given slot')
      ->setHelp('Shows details for a given slot ID')
      ->addArgument('slot', InputArgument::OPTIONAL, 'Slot ID to show detail for. If omitted defaults to current ticket.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $slot_id = $input->getArgument('slot');
    if (!$slot_id) {
      $slot = $this->repository->getActive();
      if (!$slot) {
        $slot = $this->repository->latest();
      }
      if (!$slot) {
        $output->writeln('<info>No active or latest slot</info>');
        exit(1);
      }
    }
    else {
      $slot = $this->repository->slot($slot_id);
      if (!$slot) {
        $output->writeln('<error>Slot not found</error>');
        exit(1);
      }
    }
    $table = new Table($output);
    try {
      $ticket = $this->connector->ticketDetails($slot->getTicketId(), $slot->getConnectorId());
    }
    catch (\InvalidArgumentException $e) {
      $ticket = new Ticket(\sprintf('Connector %s no longer available', $slot->getConnectorId()), 'N/A', TRUE);
    }
    $tz = new \DateTimeZone(date_default_timezone_get());
    $rows[] = ['<info>Ticket ID</info>', $slot->getTicketId()];
    $rows[] = ['<info>Title</info>', $ticket->getTitle()];
    $projectNames = $this->connector->projectNames();
    $rows[] = ['<info>Project name</info>', ($projectNames[$slot->getConnectorId()][(int) $ticket->getProjectId()] ?? 'N/A') . ' (' . (int) $ticket->getProjectId() . ')'];
    $rows[] = ['<info>Start</info>', (new \DateTime('@' . $slot->getStart()))->setTimezone($tz)->format('Y-m-d H:i:s')];
    $rows[] = ['<info>End</info>', (new \DateTime('@' . $slot->getEnd()))->setTimezone($tz)->format('Y-m-d H:i:s')];
    $rows[] = ['<info>Duration</info>', DurationFormatter::formatDuration($slot->getDuration())];
    $rows[] = ['<info>Comment</info>', $slot->getComment()];
    $table->setRows($rows);
    $output->writeln('<info>Details</info>');
    $table->render();

    $chunks = new Table($output);
    $output->writeln('');
    $output->writeln('<info>Breakdown</info>');
    $chunks->setHeaders([
      'ID', 'Start', 'End', 'Duration',
    ]);
    $rows = [];
    foreach ($slot->getChunks() as $chunk) {
      $rows[] = [
        $chunk->getId(),
        (new \DateTime('@' . $chunk->getStart()))->setTimezone($tz)->format('Y-m-d H:i:s'),
        $chunk->getEnd() ? (new \DateTime('@' . $chunk->getEnd()))->setTimezone($tz)->format('Y-m-d H:i:s') : '-',
        DurationFormatter::formatDuration($chunk->getDuration()),
      ];
    }
    $chunks->setRows($rows);
    $chunks->render();
    exit(0);
  }

}
