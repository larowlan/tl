<?php

namespace Larowlan\Tl;

use Symfony\Component\Console\Helper\TableSeparator;

/**
 * Utility formatter for Summary objects.
 */
class SummaryTableFormatter {

  /**
   * Formats a summary as table rows.
   */
  public static function formatTableRows(Summary $summary, bool $exact = FALSE): array {
    $rows = [];
    foreach ($summary->getItems() as $item) {
      $slot = $item->getSlot();
      $ticket = $item->getTicket();
      $duration = sprintf('<fg=%s>%s</>', $ticket->isBillable() ? 'default' : 'yellow', $item->getRoundedDuration());
      $row = [
        $slot->getId(),
        sprintf('<fg=%s>%s</>', $slot->isOpen() ? 'green' : 'default', $slot->getTicketId()),
        $duration,
      ];
      if ($exact) {
        $row[] = DurationFormatter::formatDuration($item->getExactDuration());
      }
      $rows[] = array_merge($row, [
        substr($ticket->getTitle(), 0, 25) . '...',
        $item->getCategory(),
        $slot->getComment(),
      ]);
    }
    $rows[] = new TableSeparator();
    if ($exact) {
      $rows[] = [
        '',
        '<comment>Total</comment>',
        '<info>' . $summary->getRoundedTotal() / 3600 . ' h</info>',
        '<info>' . DurationFormatter::formatDuration($summary->getExactTotal()) . '</info>',
        '',
        '',
        '',
      ];
    }
    else {
      $rows[] = [
        '',
        '<comment>Total</comment>',
        '<info>' . $summary->getRoundedTotal() / 3600 . ' h</info>',
        '',
        '',
        '',
      ];
    }
    return $rows;
  }

  /**
   * Gets table headers.
   */
  public static function getHeaders(bool $exact = FALSE): array {
    $headers = [
      'SlotID',
      'JobID',
      'Tallied',
    ];
    if ($exact) {
      $headers[] = 'Exact';
    }
    return array_merge($headers, [
      'Title',
      'Tag',
      'Comment',
    ]);
  }

}
