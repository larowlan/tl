<?php
/**
 * @file
 * Contains \Larowlan\Tl\TicketInterface.
 */

namespace Larowlan\Tl;

/**
 * Defines an interface for tickets.
 */
interface TicketInterface extends \ArrayAccess {

  /**
   * Gets the ticket title.
   *
   * @return string
   *   Ticket title.
   */
  public function getTitle();

  /**
   * Gets the ticket project ID.
   *
   * @return mixed
   */
  public function getProjectId();

  /**
   * Checks if the ticket is billable.
   *
   * @return bool
   *   TRUE if billable.
   */
  public function isBillable();

}
