<?php

namespace Larowlan\Tl\Connector;

use Larowlan\Tl\Slot;

/**
 * An interface for backend connectors.
 */
interface Connector {

  /**
   * Gets connector name.
   *
   * @return string
   *   Name.
   */
  public static function getName();

  /**
   * Fetch the details of a ticket from a remote ticketing system.
   *
   * @param int $id
   *   The ticket id from the remote system.
   * @param string $connectorId
   *   Connector ID.
   * @param bool $for_reporting
   *   TRUE if for reporting sake.
   *
   * @return \Larowlan\Tl\TicketInterface
   *   Ticket object.
   */
  public function ticketDetails($id, $connectorId, $for_reporting = FALSE);

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
   * @param \Larowlan\Tl\Slot $entry
   *   A time entry corresponding with a record in the {bot_tl_slot} table.
   *
   * @return int|null
   *   The time entry id from the remote system or NULL if the entry was not
   *   able to be saved.
   */
  public function sendEntry(Slot $entry);

  /**
   * Gets the URL for the given ticket ID.
   *
   * @param mixed $id
   *   Ticket ID.
   * @param string $connectorId
   *   Connector ID.
   *
   * @return string
   *   The URL.
   */
  public function ticketUrl($id, $connectorId);

  /**
   * Gets assigned tickets.
   *
   * @param string $user
   *   The user for which you want the assigned issues.
   *
   * @return array
   *   Array of tickets grouped by project, containting titles keyed by ticket
   *   ID.
   */
  public function assigned($user);

  /**
   * Sets a ticket as in progress.
   *
   * @param mixed $ticket_id
   *   Ticket ID to set in progres.
   * @param string $connectorId
   *   Connector ID.
   * @param bool $assign
   *   TRUE to assign as well.
   * @param string $comment
   *   Comment to use.
   *
   * @return bool
   *   TRUE if success.
   */
  public function setInProgress($ticket_id, $connectorId, $assign = FALSE, $comment = 'Working on this');

  /**
   * Assigns a ticket.
   *
   * @param mixed $ticket_id
   *   Ticket ID to assign.
   * @param string $connectorId
   *   Connector ID.
   * @param string $comment
   *   Comment to use.
   *
   * @return bool
   *   TRUE if success
   */
  public function assign($ticket_id, $connectorId, $comment = 'Working on this');

  /**
   * Sets a ticket as paused.
   *
   * @param mixed $ticket_id
   *   Ticket ID to set as paused.
   * @param string $comment
   *   Comment. Defaults to 'pausing for moment'.
   * @param string $connectorId
   *   Connector ID.
   *
   * @return bool
   *   TRUE if success.
   */
  public function pause($ticket_id, $comment, $connectorId);

  /**
   * Gets project names.
   *
   * @return array
   *   Project names keyed by ID.
   */
  public function projectNames();

  /**
   * Loads a ticket alias.
   *
   * @param int $ticket_id
   *   Ticket ID.
   * @param string $connectorId
   *   Connector ID.
   *
   * @return int
   *   Ticket alias
   */
  public function loadAlias($ticket_id, $connectorId);

}
