<?php

namespace Larowlan\Tl\Reporter;

use Larowlan\Tl\Slot;
use Larowlan\Tl\TicketInterface;

/**
 * Defines an interface for reporting on entries.
 */
interface Reporter {

  /**
   * Reports on an entry.
   *
   * @param \Larowlan\Tl\Slot $entry
   *   A time entry corresponding with a record in the {bot_tl_slot} table.
   * @param \Larowlan\Tl\TicketInterface $details
   *   Ticket details.
   * @param array $projects
   *   Project details.
   * @param array $categories
   *   Project categories.
   *
   * @return bool
   *   TRUE if succeeded.
   */
  public function report(Slot $entry, TicketInterface $details, array $projects, array $categories);

  /**
   * Gets the name of the reporter.
   *
   * @return string
   *   Name.
   */
  public static function getName();

}
