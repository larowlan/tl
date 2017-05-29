<?php
/**
 * @file
 * Contains Reviewer.php
 */

namespace Larowlan\Tl;


use GuzzleHttp\Exception\ConnectException;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Helper\TableSeparator;

class Reviewer {

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
  }

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

  public function getSummary($date = 19780101, $check = FALSE, $exact = FALSE) {
    $data = $this->repository->review($date, $check);
    if (count($data) == 0 && !$check) {
      throw new \Exception('All entries stored in remote system');
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
      $details = $this->connector->ticketDetails($record->tid);
      $category_id = str_pad($record->category, 3, 0, STR_PAD_LEFT);
      $category = '';
      if ($record->category) {
        if ($offline) {
          $category = 'Offline';
        }
        elseif (isset($categories[$category_id])) {
          $category = $categories[$category_id];
        }
        else {
          $category = 'Unknown';
        }
      }
      $duration = sprintf('<fg=%s>%s</>', $details->isBillable() ? 'default' : 'yellow', $record->duration);
      $row = [
        $record->id,
        $record->tid,
        $duration,
      ];
      if ($exact) {
        $row[] = Formatter::formatDuration($record->end - $record->start);
        $exact_total += ($record->end - $record->start);
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
        ''
      ];
    }
    else {
      $rows[] = [
        '',
        '<comment>Total</comment>',
        '<info>' . $total . ' h</info>',
        '',
        '',
        ''
      ];
    }
    return $rows;
  }
}
