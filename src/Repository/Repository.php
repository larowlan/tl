<?php

namespace Larowlan\Tl\Repository;

/**
 * Interface for storage of slots.
 */
interface Repository {

  public function getActive();

  public function latest();

  public function stop($slot_id = NULL);

  public function start($ticket_id, $connectorId, $comment = '', $force_continue = FALSE);

  public function insert($slot, $params = []);

  public function status($date = NULL);

  public function comment($slot_id, $comment);

  public function review($date = NULL, $check = FALSE);

  public function send();

  public function store($entries);

  public function edit($slot_id, int $duration);

  public function tag($tag_id, $slot_id = NULL);

  public function frequent();

  public function slot($slot_id);

  public function delete($slot_id);

  public function addAlias($ticket_id, $alias);

  public function removeAlias($ticket_id, $alias);

  public function loadAlias($alias);

  public function listAliases($filter = '');

  public function totalByTicket($start_timestamp, $end_timestamp = NULL);

}
