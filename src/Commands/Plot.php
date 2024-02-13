<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Plot extends Command {

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
      ->setName('plot')
      ->setDescription('Shows plot of activity for a given date')
      ->setHelp('Shows plot of time by ticket for a given date. <comment>Usage:</comment> <info>tl plot [optional date]</info>')
      ->addArgument('date', InputArgument::OPTIONAL, 'Date in ISO format e.g. 2015-12-31');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Allow special date param in the form of _1 for back one day, _2 for two
    // days etc.
    $date = $input->getArgument('date');
    if (!empty($date) && preg_match('/_(\d+)/', $date, $matches)) {
      $date = date('Y-m-d', time() - 86400 * $matches[1]);
    }
    $data = $this->repository->status($date);
    if (count($data) === 0) {
      $output->writeln('No data found');
      return 1;
    }
    $table = new Table($output);
    $compact = new TableStyle();
    $compact
      ->setHorizontalBorderChars('')
      ->setVerticalBorderChars('')
      ->setDefaultCrossingChar('')
      ->setCellRowContentFormat('%s');
    $table->setStyle($compact);
    $chunks = [];
    /** @var \Larowlan\Tl\Slot $record */
    foreach ($data as $record) {
      $details = $this->connector->ticketDetails($record->getTicketId(), $record->getConnectorId());
      $job_id = sprintf('<fg=%s>%s</>', $details->isBillable() ? 'default' : 'yellow', $record->getId());
      /** @var \Larowlan\Tl\Chunk $chunk */
      foreach ($record->getChunks() as $chunk) {
        $chunks[] = [$job_id, substr($details->getTitle(), 0, 10) . '...', $chunk->getStart(), $chunk->getEnd(), $details->isBillable() ? 'green' : 'yellow'];
      }
    }
    uasort($chunks, fn(array $a, array $b) => $a[2] <=> $b[2]);
    $start = reset($chunks)[2];
    $end = end($chunks)[3];
    $range = $end - $start;

    // Assuming average terminal width of 80 chars, remove 8 for the ticket ID
    // and we have a nice 72 chars for the chart.
    $col_duration = floor($range / 72);
    $rows = [];
    $comparisons = [];
    foreach (range(0, 71) as $ix) {
      $comparisons[] = $start + ($ix * $col_duration);

    }
    foreach ($chunks as $chunk) {
      $row = $rows[$chunk[0]] ?? array_pad([$chunk[0], $chunk[1]], 74, ' ');
      foreach ($comparisons as $ix => $stamp) {
        if ($stamp >= $chunk[2] && $stamp <= $chunk[3]) {
          $row[$ix + 2] = '<bg=' . $chunk[4] . '> </>';
        }
      }
      $rows[$chunk[0]] = $row;
    }

    // We allow 6 chars per time, so lets derive 12 timestamps for headers.
    $headers = ['#', 'Title'];
    $start_date = new \DateTime('@' . $start);
    $start_date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $headers[] = new TableCell($start_date->format('h:i'), ['colspan' => 6]);
    foreach (range(0, 10) as $ix) {
      $headers[] = new TableCell($start_date->modify(sprintf('+%d seconds', 6 * $col_duration))->format('h:i'), ['colspan' => 6]);
    }
    $table->setHeaders($headers);
    $table->setRows($rows);
    $table->render();
    return 0;
  }

}
