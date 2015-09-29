<?php

/**
 * @file
 * Contains \Larowlan\Tl\Repository\Repository.
 */

namespace Larowlan\Tl\Repository;

interface Repository {

  public function getActive();

  public function stop($slot_id = NULL);

  public function start($ticket_id);

  public function insert($slot);

  public function status($date = NULL);

  public function comment($slot_id, $comment);

  public function review($date = NULL, $check = FALSE);

  public function send();

  public function store($entries);

  public function edit($slot_id, $duration);

  public function tag($tag_id, $slot_id = NULL);

  public function frequent();
  public function slot($slot_id);
  public function delete($slot_id);

}
