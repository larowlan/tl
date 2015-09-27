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

  public function status($uid, $date = NULL);

  public function comment($uid, $ticket_id, $comment);

  public function review($uid, $date = NULL, $check = FALSE);

  public function send($uid);

  public function store($entries, $uid);

  public function edit($uid, $slot_id, $duration);

  public function tag($uid, $tag_id, $ticket_id = NULL);

}
