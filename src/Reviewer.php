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
    foreach ($data as $record) {
      $total += $record->duration;
      $details = $this->connector->ticketDetails($record->tid, $record->connector_id);
      $category_id = str_pad($record->category, 3, 0, STR_PAD_LEFT);
      $category = '';
      if ($record->category) {
        if ($offline) {
          $category = 'Offline';
        }
        elseif (isset($categories[$record->connector_id][$category_id])) {
          $category = $categories[$record->connector_id][$category_id];
        }
        else {
          $category = 'Unknown';
        }
      }
      $duration = sprintf('<fg=%s>%s</>', $details->isBillable() ? 'default' : 'yellow', $record->duration);
      $row = [
        $record->id,
        sprintf('<fg=%s>%s</>', $record->active ? 'green' : 'default', $record->tid),
        $duration,
      ];
      if ($exact) {
        $row[] = Formatter::formatDuration(($record->end ?: time()) - $record->start);
        $exact_total += (($record->end ?: time()) - $record->start);
      }
      $row = array_merge($row, [
        substr($details->getTitle(), 0, 25) . '...',
        $category,
        $record->comment,
      ]);
      $rows[] = $row;
    }
    $rows[] = new TableSeparator();
    if ($exact) {
      $rows[] = [
        '',
        '<comment>Total</comment>',
        '<info>' . $total . ' h</info>',
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
        '<info>' . $total . ' h</info>',
        '',
        '',
        '',
      ];
    }
    return $rows;
  }

}
