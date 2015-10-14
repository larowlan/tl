<?php

/**
 * @file
 * Contains \Larowlan\Tl\Connector\Connector.
 */

namespace Larowlan\Tl\Connector;

interface Connector {

  /**
   * Fetch the details of a ticket from a remote ticketing system.
   *
   * @param int $id
   *   The ticket id from the remote system.
   *
   * @return array
   *   Array with keys project and title.
   */
  public function ticketDetails($id);

  /**
   * Fetch the details of time categories from a remote ticketing system.
   *
   * @return array
   *   Array of categories keyed by id.
   */
  public function fetchCategories();

  /**
   * Send a time entry to the remote ticketing system.
   *
   * @param object $entry
   *   A time entry corresponding with a record in the {bot_tl_slot} table.
   *
   * @return int|NULL
   *   The time entry id from the remote system or NULL if the entry was not
   *   able to be saved.
   */
  public function sendEntry($entry);

  /**
   * Gets the URL for the given ticket ID.
   *
   * @param mixed $id
   *   Ticket ID.
   *
   * @return string
   *   The URL.
   */
  public function ticketUrl($id);

  /**
   * Gets assigned tickets.
   *
   * @return array
   *   Array of tickets grouped by project, containting titles keyed by ticket
   *   ID.
   */
  public function assigned();

}
