<?php

namespace Larowlan\Tl;

use GuzzleHttp\Exception\ConnectException;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Helper\TableSeparator;

/**
 * Reviewer helper.
 */
class Reviewer {

  /**
   * Connector.
   *
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * Repository.
   *
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   * Constructs a new Reviewer.
   *
   * @param \Larowlan\Tl\Connector\Connector $connector
   *   Connector.
   * @param \Larowlan\Tl\Repository\Repository $repository
   *   Repository.
   */
  public function __construct(Connector $connector, Repository $repository) {
    $this->connector = $connector;
    $this->repository = $repository;
  }

  /**
   * Gets table headers.
   *
   * @param bool $exact
   *   TRUE to include exact column.
   *
   * @return array
   *   Headers.
   */
  public static function headers($exact = FALSE) {
    $headers = [
      'SlotID',
      'JobID',
      'Tallied',
    ];
    if ($exact) {
      $headers[] = 'Exact';
    }
    $headers = array_merge($headers, [
      'Title',
      'Tag',
      'Comment',
    ]);
    return $headers;
  }

  /**
   * Gets summary of tickets.
   *
   * @param int $date
   *   Start date.
   * @param bool $check
   *   Check if stored remotely already.
   * @param bool $exact
   *   TRUE to include exact column.
   *
   * @return array
   *   Rows.
   *
   * @throws \Exception
   *   When all entries already stored and check is TRUE.
   */
  public function getSummary($date = 19780101, $check = FALSE, $exact = FALSE) {
    $data = $this->repository->review($date, $check);
    if (count($data) == 0 && !$check) {
      throw new \Exception("All entries stored in remote system \xF0\x9F\x8D\xBA \xF0\x9F\x8D\xBA \xF0\x9F\x8D\xBA");
    }

    $total = 0;
    $offline = FALSE;
    try {
      $categories = $this->connector->fetchCategories();
    }
    catch (ConnectException $e) {
      $offline = TRUE;
    }
    $exact_total = 0;
    /** @var \Larowlan\Tl\Slot $record */
    foreach ($data as $record) {
      $total += $record->getDuration(FALSE, TRUE);
      $details = $this->connector->ticketDetails($record->getTicketId(), $record->getConnectorId());
      $category_id = str_pad($record->getCategory(), 3, 0, STR_PAD_LEFT);
      $category = '';
      if ($record->getCategory()) {
        if ($offline) {
          $category = 'Offline';
        }
        elseif (isset($categories[$record->getConnectorId()][$category_id])) {
          $category = $categories[$record->getConnectorId()][$category_id];
        }
        else {
          $category = 'Unknown';
        }
      }
      $duration = sprintf('<fg=%s>%s</>', $details->isBillable() ? 'default' : 'yellow', $record->getDuration(FALSE, TRUE) / 3600);
      $row = [
        $record->getId(),
        sprintf('<fg=%s>%s</>', $record->isOpen() ? 'green' : 'default', $record->getTicketId()),
        $duration,
      ];
      if ($exact) {
        $row[] = Formatter::formatDuration($record->getDuration());
        $exact_total += $record->getDuration();
      }
      $row = array_merge($row, [
        substr($details->getTitle(), 0, 25) . '...',
        $category,
        $record->getComment(),
      ]);
      $rows[] = $row;
    }
    $rows[] = new TableSeparator();
    if ($exact) {
      $rows[] = [
        '',
        '<comment>Total</comment>',
        '<info>' . $total / 3600 . ' h</info>',
        '<info>' . Formatter::formatDuration($exact_total) . '</info>',
        '',
        '',
        '',
      ];
    }
    else {
      $rows[] = [
        '',
        '<comment>Total</comment>',
        '<info>' . $total / 3600 . ' h</info>',
        '',
        '',
        '',
      ];
    }
    return $rows;
  }

}
