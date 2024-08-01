<?php

namespace Larowlan\Tl;

use GuzzleHttp\Exception\ConnectException;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;

/**
 * Defines a class for common reviewing functionality.
 */
class Reviewer {

  /**
   * Constructs a new Reviewer.
   */
  public function __construct(
    readonly protected Connector $connector,
    readonly protected Repository $repository,
  ) {
  }

  /**
   * Gets summary of tickets.
   *
   * @param int $date
   *   Start date.
   * @param bool $check
   *   Check if stored remotely already.
   *
   * @return \Larowlan\Tl\Summary
   *   The summary.
   *
   * @throws \Exception
   *   When all entries already stored and check is TRUE.
   */
  public function getSummary(int $date = 19780101, bool $check = FALSE): Summary {
    $data = $this->repository->review($date, $check);
    if (count($data) == 0 && !$check) {
      throw new \Exception("All entries stored in remote system \xF0\x9F\x8D\xBA \xF0\x9F\x8D\xBA \xF0\x9F\x8D\xBA");
    }

    $roundedTotal = 0;
    $offline = FALSE;
    try {
      $categories = $this->connector->fetchCategories();
    }
    catch (ConnectException $e) {
      $offline = TRUE;
    }
    $exactTotal = 0;
    $items = [];
    /** @var \Larowlan\Tl\Slot $record */
    foreach ($data as $record) {
      $roundedTotal += $record->getDuration(FALSE, TRUE);
      $details = $this->connector->ticketDetails($record->getTicketId(), $record->getConnectorId());
      $category_id = str_pad($record->getCategory() ?? '', 3, 0, STR_PAD_LEFT);
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
      $roundedDuration = $record->getDuration(FALSE, TRUE) / 3600;
      $exactDuration = $record->getDuration();
      $duration = sprintf('<fg=%s>%s</>', $details->isBillable() ? 'default' : 'yellow', $roundedDuration);
      $item = new SummaryItem(
        $record,
        $details,
        $category,
        $exactDuration,
        $roundedDuration
      );
      $items[] = $item;
      $exactTotal += $record->getDuration();
    }
    return new Summary($items, $roundedTotal, $exactTotal);

  }

}
