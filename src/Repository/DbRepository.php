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
      $q = $q->andWhere('s.id = :id')
        ->setParameter('id', $slot_id);
    }
    if ($open = $q
      ->execute()
      ->fetch(\PDO::FETCH_OBJ)) {
      return $open;
    }
    return FALSE;
  }

  public function start($ticket_id, $comment = '') {
    if (!$comment && $continue = $this->qb()->select('*')
      ->from('slots', 's')
      ->where('s.tid = :tid')
      ->andWhere('s.comment IS NULL')
      ->andWhere('s.category IS NULL')
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
    $params = [];
    if ($comment) {
      $record['comment'] = ':comment';
      $params[':comment'] = $comment;
    }

    return array($this->insert($record, $params), FALSE);
  }

  public function insert($slot, $params = []) {
    $query = $this->qb()->insert('slots')
      ->values($slot);
    foreach ($params as $key => $value) {
      $query->setParameter($key, $value);
    }
    $query->execute();
    return $this->connection()->lastInsertId();
  }

  public function status($date = NULL) {
    $stop = $this->stop();
    if (!$date) {
      $stamp = mktime('0', '0');
    }
    else {
      $stamp = strtotime($date);
    }
    $return = $this->qb()->select('id', 'tid', 'end - start AS duration', 'CASE WHEN id = :id THEN 1 ELSE 0 END AS active')
      ->from('slots')
      ->where('start > :start AND start < :end')
      ->setParameter(':start', $stamp)
      ->setParameter(':end', $stamp + (60 * 60 * 24))
      ->setParameter(':id', isset($stop->id) ? $stop->id : 0)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
    if ($stop) {
      $this->qb()->update('slots')
        ->set('end', ':end')
        ->setParameter(':end', NULL)
        ->where('id = :id')
        ->setParameter(':id', $stop->id)
        ->execute();
    }
    return $return;
  }

  public function review($date = NULL, $check = FALSE) {
    $stop = $this->stop();
    if (!$date) {
      $stamp = mktime('0', '0');
    }
    else {
      $stamp = strtotime($date);
    }
    $query = $this->qb()->select('end', 'tid', 'category', 'comment', 'id', 'start')
    ->from('slots');
    $where = $this->qb()->expr()->andX(
      $this->qb()->expr()->isNull('teid'),
      $this->qb()->expr()->gt('start', ':stamp')
    );
    $query->setParameter(':stamp', $stamp);
    if ($check) {
      // Only incomplete records.
      $where->add($this->qb()->expr()->orX(
        $this->qb()->expr()->isNull('comment'),
        $this->qb()->expr()->isNull('category')
      ));
    }
    $return = $query->where($where)->execute()->fetchAll(\PDO::FETCH_OBJ);
    if ($stop) {
      $this->qb()->update('slots')
        ->set('end', ':end')
        ->setParameter(':end', NULL)
        ->where('id = :id')
        ->setParameter(':id', $stop->id)
        ->execute();
    }
    foreach ($return as &$row) {
      $row->duration = round(($row->end - $row->start) / 900) * 900 / 3600;
    }
    return $return;
  }

  public function send() {
    return $this->review('19780101');
  }

  public function store($entries) {
    foreach ($entries as $tid => $entry_id) {
      $this->qb()->update('slots')
        ->set('teid', $entry_id)
        ->where('tid = :tid')
        ->setParameter(':tid', $tid)
        ->andWhere('teid IS NULL')
        ->execute();
    }
  }

  public function edit($slot_id, $duration) {
    $request_time = $this::requestTime();
    return $this->qb()->update('slots')
      ->set('start', $request_time - ($duration * 3600))
      ->set('end', $request_time)
      ->where('id = :id')
      ->setParameter(':id', $slot_id)->execute();
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

  public function tag($tag_id, $slot_id = NULL) {
    $query = $this->qb()->update('slots')
      ->set('category', ':tag')
      ->setParameter(':tag', $tag_id);
    if ($slot_id) {
      $query->andWhere('id = :id')
        ->setParameter(':id', $slot_id);
    }
    return $query->execute();
  }

  public function comment($slot_id, $comment) {
    return $this->qb()->update('slots')
      ->set('comment', ':comment')
      ->setParameter(':comment', $comment)
      ->where('id = :id')
      ->setParameter(':id', $slot_id)
      ->andWhere('comment IS NULL')
      ->execute();
  }

  public function frequent() {
    return $this->qb()
      ->select('tid')
      ->from('slots', 's')
      ->groupBy('tid')
      ->orderBy('COUNT(*)', 'DESC')
      ->setMaxResults(10)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
  }

  public function slot($slot_id) {
    return $this->qb()->select('*')
      ->from('slots')
      ->where('id = :id')
      ->setParameter(':id', $slot_id)
      ->execute()
      ->fetch(\PDO::FETCH_OBJ);
  }

  public function delete($slot_id) {
    return $this->qb()->delete('slots')
      ->where('id = :id')
      // Can't delete sent entries.
      ->andWhere('teid IS NULL')
      ->setParameter(':id', $slot_id)
      ->execute();
  }

  protected function connection() {
    return $this->connection;
  }

  public function addAlias($ticket_id, $alias) {
    return $this->qb()->insert('aliases')
      ->values([
        'tid' => ':ticket_id',
        'alias' => ':alias',
      ])
      ->setParameter(':ticket_id', $ticket_id)
      ->setParameter(':alias', $alias)
      ->execute();
  }

  public function removeAlias($ticket_id, $alias) {
    return $this->qb()->delete('aliases')
      ->where('tid = :ticket_id')
      ->andWhere('alias = :alias')
      ->setParameter(':ticket_id', $ticket_id)
      ->setParameter(':alias', $alias)
      ->execute();
  }

  public function loadAlias($alias) {
    return $this->qb()->select('tid')
      ->from('aliases')
      ->where('alias = :alias')
      ->setParameter(':alias', $alias)
      ->execute()
      ->fetchColumn();
  }

  public function listAliases($filter = '') {
    $query = $this->qb()->select('alias', 'tid')
      ->from('aliases');
    if (!empty($filter)) {
      $query
        ->where('alias LIKE :filter')
        ->setParameter(':filter', $filter . '%');
    }
    return $query
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
  }

  public function totalByTicket($start, $end = NULL) {
    $stop = $this->stop();
    if (!$end) {
      // Some time in the future.
      $end = time() + 86400;
    }
    $return = $this->qb()->select('tid', 'end - start AS duration')
      ->from('slots')
      ->where('start > :start AND start < :end')
      ->setParameter(':start', $start)
      ->setParameter(':end', $end)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
    $totals = [];
    foreach ($return as $row) {
      $row->duration = round($row->duration / 900) * 900;
      if (!isset($totals[$row->tid])) {
        $totals[$row->tid] = 0;
      }
      $totals[$row->tid] += $row->duration;
    }
    if ($stop) {
      $this->qb()->update('slots')
        ->set('end', ':end')
        ->setParameter(':end', NULL)
        ->where('id = :id')
        ->setParameter(':id', $stop->id)
        ->execute();
    }
    return $totals;
  }

}
