<?php
/**
 * @file
 * Contains Reviewer.php
 */

namespace Larowlan\Tl;


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

  public static function headers() {
    return [
      'SlotID',
      'JobID',
      'Tallied',
      'Title',
      'Tag',
      'Comment',
    ];
  }

  public function getSummary($date = 19780101, $check = FALSE) {
    $data = $this->repository->review($date, $check);
    if (count($data) == 0 && !$check) {
      throw new \Exception('All entries stored in remote system');
    }

    $total = 0;
    $categories = $this->connector->fetchCategories();
    foreach ($data as $record) {
      $total += $record->duration;
      $details = $this->connector->ticketDetails($record->tid);
      $rows[] = [
        $record->id,
        $record->tid,
        $record->duration,
        substr($details['title'], 0, 25) . '...',
        $record->category ? $categories[str_pad($record->category, 3, 0, STR_PAD_LEFT)] : '',
        $record->comment,
      ];
    }
    $rows[] = new TableSeparator();
    $rows[] = ['', '<comment>Total</comment>', '<info>' . $total . ' h</info>', '', '', ''];
    return $rows;
  }
}
