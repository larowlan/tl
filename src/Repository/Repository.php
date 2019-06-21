<?php

namespace Larowlan\Tl\Repository;

use Larowlan\Tl\Slot;

/**
 * Interface for storage of slots.
 */
interface Repository {

  public function getActive() : ?Slot;

  public function latest() : ?Slot;

  public function stop($slot_id = NULL) : ?Slot;

  public function start($ticket_id, $connectorId, $comment = '', $force_continue = FALSE) : ?Slot;

  public function insert($slot, $params = []) : int;

  public function status($date = NULL) : array;

  public function comment($slot_id, $comment);

  public function review($date = NULL, $check = FALSE) : array;

  public function send() : array;

  public function store($entries);

  public function edit($slot_id, $duration);

  public function tag($tag_id, $slot_id = NULL);

  public function frequent() : array;

  public function slot($slot_id) : ?Slot;

  public function combine(Slot $slot1, Slot $slot2);

  public function delete($slot_id);

  public function addAlias($ticket_id, $alias);

  public function removeAlias($ticket_id, $alias);

  public function loadAlias($alias);

  public function listAliases($filter = '');

  public function totalByTicket($start_timestamp, $end_timestamp = NULL) : array;

}
