<?php

/**
 * @file
 * Contains \Larowlan\Tl\Repository\DbRepository
 */

namespace Larowlan\Tl\Repository;

use Doctrine\DBAL\Connection;

class DbRepository implements Repository {

  /**
   * Array of user details keyed by irc nick.
   *
   * @var array
   */
  protected $userDetails = array();

  /**
   * The active database connection.
   *
   * @var \Doctrine\Dbal\Connection
   */
  protected $connection;

  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  protected function qb() {
    return $this->connection()->createQueryBuilder();
  }
  /**
   * {@inheritdoc}
   */
  public function stop($slot_id = NULL) {
    if ($open = $this->getActive($slot_id)) {
      $end = $this::requestTime();
      $this->qb()->update('slots')
        ->set('end', $end)
        ->where('id = :id')
        ->setParameter('id', $open->id)
        ->execute();
      $open->end = $end;
      $open->duration = $open->end - $open->start;
      return $open;
    }
    return FALSE;
  }

  public function getActive($slot_id = NULL) {
    $q = $this->qb()->select('*')
      ->from('slots', 's')
      ->where('s.end IS NULL');
    if ($slot_id) {
      $q = $q->where('s.id = :id')
        ->setParameter('id', $slot_id);
    }
    if ($open = $q
      ->execute()
      ->fetch(\PDO::FETCH_OBJ)) {
      return $open;
    }
    return FALSE;
  }

  public function start($ticket_id) {
    if ($continue = $this->qb()->select('*')
      ->from('slots', 's')
      ->where('s.tid = :tid')
      ->where('s.comment IS NULL')
      ->where('s.category IS NULL')
      ->setParameter('tid', $ticket_id)
      ->execute()
      ->fetch(\PDO::FETCH_OBJ)) {
      $this->qb()->update('slots')
        ->where('id = :id')
        ->setParameter('id', $continue->id)
        ->set('start', $this::requestTime() + $continue->start - $continue->end)
        ->set('end', ':end')
        ->setParameter(':end', NULL)
        ->execute();
      return array($continue->id, TRUE);
    }
    $record = array(
      'tid' => $ticket_id,
      'start' => $this::requestTime(),
    );
    $this->qb()->insert('slots')
      ->values($record)
      ->execute();
    return array($this->connection()->lastInsertId(), FALSE);
  }

  public function status($uid, $date = NULL) {
    $stop = $this->stop($uid);
    if (!$date) {
      $stamp = mktime('0', '0');
    }
    else {
      $stamp = strtotime($date);
    }
    $query = $this->connection()->select('bot_tl_slot', 'b')
      ->condition('uid', $uid)
      ->condition('start', $stamp, '>');
    $query->addExpression('end - start', 'duration');
    if ($stop) {
      $query->addExpression('CASE WHEN id = :id THEN 1 ELSE 0 END', 'active', array(
        ':id' => $stop->id,
      ));
    }
    $return = $query->fields('b', array('id', 'tid'))
      ->execute()
      ->fetchAll();
    if ($stop) {
      $this->connection()->update('bot_tl_slot')
        ->fields(array('end' => NULL))
        ->condition('id', $stop->id)
        ->execute();
    }
    return $return;
  }

  public function review($uid, $date = NULL, $check = FALSE) {
    $stop = $this->stop($uid);
    if (!$date) {
      $stamp = mktime('0', '0');
    }
    else {
      $stamp = strtotime($date);
    }
    $query = $this->connection()->select('bot_tl_slot', 'b')
      ->condition('uid', $uid)
      ->isNull('teid')
      ->condition('start', $stamp, '>');
    $query->addExpression('ROUND((end - start) / 900) * 900 / 3600', 'duration');
    if ($check) {
      // Only incomplete records.
      $query->condition($this->orCondition()
        ->isNull('category')
        ->isNull('comment'));
    }
    $return = $query->fields('b', array('tid', 'category', 'comment', 'id', 'start'))
      ->execute()
      ->fetchAll();
    if ($stop) {
      $this->connection()->update('bot_tl_slot')
        ->fields(array('end' => NULL))
        ->condition('id', $stop->id)
        ->execute();
    }
    return $return;
  }

  public function send($uid) {
    return $this->review($uid, '19780101');
  }

  public function store($entries, $uid) {
    foreach ($entries as $tid => $entry_id) {
      $this->connection()->update('bot_tl_slot')
        ->fields(array('teid' => $entry_id))
        ->condition('tid', $tid)
        ->condition('uid', $uid)
        ->isNull('teid')
        ->execute();
    }
  }

  public function edit($uid, $slot_id, $duration) {
    $request_time = $this::requestTime();
    $query = $this->connection()->update('bot_tl_slot', array(
      'return' => \Database::RETURN_AFFECTED,
    ))
      ->fields(array(
        'start' => $request_time - ($duration * 3600),
        'end' => $request_time,
      ))
      ->condition('uid', $uid)
      ->condition('id', $slot_id);
    return $query->execute();
  }

  /**
   * Returns the active connection.
   *
   * @return \Doctrine\Dbal\Connection
   *   The active connection.
   */
  protected function connection() {
    return $this->connection;
  }

  /**
   * Wraps REQUEST_TIME constant
   *
   * @return int
   *   Current request time.
   */
  protected static function requestTime() {
    return time();
  }

  public function tag($uid, $tag_id, $ticket_id = NULL) {
    $query = $this->connection()->update('bot_tl_slot', array(
      'return' => \Database::RETURN_AFFECTED,
    ))
      ->fields(array('category' => $tag_id))
      ->condition('uid', $uid);
    if (!$ticket_id) {
      // Tagging all open slots.
      $query->isNull('category');
    }
    else {
      $query->condition('tid', $ticket_id);
    }
    return $query->execute();
  }

  public function comment($uid, $ticket_id, $comment) {
    return $this->connection()->update('bot_tl_slot', array(
      'return' => \Database::RETURN_AFFECTED,
    ))
      ->fields(array('comment' => $comment))
      ->condition('uid', $uid)
      ->condition('tid', $ticket_id)
      ->isNull('comment')
      ->execute();
  }
}
