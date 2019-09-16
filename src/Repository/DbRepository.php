<?php

namespace Larowlan\Tl\Repository;

use Doctrine\DBAL\Connection;
use Larowlan\Tl\Slot;

/**
 * Repository backed by a database.
 */
class DbRepository implements Repository {

  /**
   * The active database connection.
   *
   * @var \Doctrine\Dbal\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  protected function qb() {
    return $this->connection()->createQueryBuilder();
  }

  /**
   * {@inheritdoc}
   */
  public function stop($slot_id = NULL): ?Slot {
    if ($open = $this->getActive($slot_id)) {
      $end = $this::requestTime();
      $this->qb()->update('chunks')
        ->set('end', $end)
        ->where('id = :id')
        ->setParameter('tid', $open->getId())
        ->setParameter('id', $open->lastChunk()->getId())
        ->execute();
      $open->lastChunk()->setEnd($end);
      $open->getDuration(TRUE);
      return $open;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getActive($slot_id = NULL): ?Slot {
    $q = $this->qb()
      ->select('s.id', 's.tid', 's.comment', 's.category', 's.connector_id', 's.teid')
      ->from('slots', 's')
      ->innerJoin('s', 'chunks', 'c', 'c.sid = s.id')
      ->where('c.end IS NULL');
    if ($slot_id) {
      $q = $q->andWhere('s.id = :id')
        ->setParameter('id', $slot_id);
    }
    if ($open = $q
      ->execute()
      ->fetch(\PDO::FETCH_OBJ)) {
      return Slot::fromRecord($open, $this->chunksForSlot($open->id));
    }
    return NULL;
  }

  /**
   * Gets chunks for a slot.
   *
   * @param int $slot_id
   *   Slot ID.
   *
   * @return array
   *   Chunk records.
   */
  protected function chunksForSlot(int $slot_id): array {
    return $this->qb()->select('*')
      ->from('chunks', 'c')
      ->where('c.sid = :sid')
      ->setParameter('sid', $slot_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
  }

  /**
   * {@inheritdoc}
   */
  public function latest(): ?Slot {
    $q = $this->qb()
      ->select('s.id', 's.tid', 's.comment', 's.category', 's.connector_id', 's.teid')
      ->from('slots', 's')
      ->innerJoin('s', 'chunks', 'c', 'c.sid = s.id')
      ->orderBy('c.end', 'DESC');
    if ($open = $q
      ->execute()
      ->fetch(\PDO::FETCH_OBJ)) {
      return Slot::fromRecord($open, $this->chunksForSlot($open->id));
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function start($ticket_id, $connectorId, $comment = '', $force_continue = FALSE): ?Slot {
    $continue_query = $this->qb()->select('*')
      ->from('slots', 's')
      ->where('s.connector_id = :connector_id')
      ->where('s.teid is NULL')
      ->where('s.tid = :tid');
    if (!$force_continue) {
      $continue_query->andWhere('s.comment IS NULL')
        ->andWhere('s.category IS NULL');
    }
    else {
      $continue_query->andWhere('s.id = :id')
        ->setParameter('id', $force_continue);
    }
    $continue = $continue_query->setParameter('tid', $ticket_id)
      ->setParameter('connector_id', $connectorId)
      ->execute()
      ->fetch(\PDO::FETCH_OBJ);
    if ((!$comment || $force_continue) && $continue) {
      $this->insertChunk($this::requestTime(), NULL, $continue->id);
      return Slot::fromRecord($continue, $this->chunksForSlot($continue->id));
    }
    $record = [
      'tid' => $ticket_id,
      'start' => $this::requestTime(),
      'connector_id' => ':connector_id',
    ];
    $params = [':connector_id' => $connectorId];
    if ($comment) {
      $record['comment'] = ':comment';
      $params[':comment'] = $comment;
    }

    return $this->slot($this->insert($record, $params));
  }

  /**
   * {@inheritdoc}
   */
  public function insert($slot, $params = []): int {
    $start = $slot['start'];
    $end = $slot['end'] ?? NULL;
    unset($slot['start'], $slot['end']);
    $query = $this->qb()->insert('slots')
      ->values($slot);
    foreach ($params as $key => $value) {
      $query->setParameter($key, $value);
    }
    $query->execute();
    $slot_id = $this->connection()->lastInsertId();
    $this->insertChunk($start, $end, $slot_id);
    return $slot_id;
  }

  /**
   * {@inheritdoc}
   */
  public function status($date = NULL): array {
    if (!$date) {
      $stamp = mktime('0', '0');
    }
    else {
      $stamp = strtotime($date);
    }
    return array_map(function ($record) {
      return Slot::fromRecord($record, $this->chunksForSlot($record->id));
    }, $this->qb()->select('s.id', 's.tid', 's.connector_id', 's.teid', 's.category', 's.comment')
      ->from('slots', 's')
      ->innerJoin('s', 'chunks', 'c', 'c.sid = s.id')
      ->having('c.start > :start AND c.start < :end')
      ->groupBy('s.id', 's.tid', 's.connector_id', 's.teid', 's.category', 's.comment')
      ->setParameter(':start', $stamp)
      ->setParameter(':end', $stamp + (60 * 60 * 24))
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ));
  }

  /**
   * {@inheritdoc}
   */
  public function review($date = NULL, $check = FALSE): array {
    if (!$date) {
      $stamp = mktime('0', '0');
    }
    else {
      $stamp = strtotime($date);
    }
    $query = $this->qb()
      ->select('s.id', 's.tid', 's.connector_id', 's.teid', 's.category', 's.comment', 'c.end', 'c.start', 'c.sid')
      ->from('slots', 's')
      ->innerJoin('s', 'chunks', 'c', 'c.sid = s.id');
    $where = $this->qb()->expr()->andX(
      $this->qb()->expr()->isNull('teid'),
      $this->qb()->expr()->gt('c.start', ':stamp')
    );
    $query->setParameter(':stamp', $stamp);
    if ($check) {
      // Only incomplete records.
      $where->add($this->qb()->expr()->orX(
        $this->qb()->expr()->isNull('comment'),
        $this->qb()->expr()->isNull('category')
      ));
    }
    return array_map(function (array $record) {
      return Slot::fromRecord($record['record'], $record['chunks']);
    }, array_reduce($query->where($where)->execute()->fetchAll(\PDO::FETCH_OBJ), function (array $carry, $record) {
      $carry[$record->sid]['record'] = $record;
      $carry[$record->sid]['chunks'][] = $record;
      return $carry;
    }, []));
  }

  /**
   * {@inheritdoc}
   */
  public function send(): array {
    return $this->review('19780101');
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function edit($slot_id, $duration) {
    $slot = $this->slot($slot_id);
    $chunks = $slot->getChunks();
    $existing = $slot->getDuration();
    $difference = $duration * 3600 - $existing;
    if ($difference < 0) {
      $remove = abs($difference);
      // We're reducing the total.
      while ($remove) {
        $chunk = array_pop($chunks);
        if ($chunk->getDuration() > $remove) {
          $this->qb()->update('chunks')
            ->set('end', ($chunk->getEnd() ?: time()) - $remove)
            ->where('id = :id')
            ->setParameter(':id', $chunk->getId())->execute();
          return;
        }
        $remove -= $chunk->getDuration();
        $this->qb()
          ->delete('chunks')
          ->where('id = :id')
          ->setParameter(':id', $chunk->getId())
          ->execute();
      }
      return;
    }
    // We're increasing the total.
    $chunk = $slot->lastChunk();
    return $this->qb()->update('chunks')
      ->set('end', ($chunk->getEnd() ?: time()) + $difference)
      ->where('id = :id')
      ->setParameter(':id', $chunk->getId())->execute();
  }

  /**
   * Wraps REQUEST_TIME constant.
   *
   * @return int
   *   Current request time.
   */
  protected static function requestTime() {
    return time();
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function comment($slot_id, $comment) {
    return $this->qb()->update('slots')
      ->set('comment', ':comment')
      ->setParameter(':comment', $comment)
      ->where('id = :id')
      ->setParameter(':id', $slot_id)
      ->andWhere('comment IS NULL')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function frequent(): array {
    return array_map(function ($record) {
      return Slot::fromRecord($record, $this->chunksForSlot($record->id));
    }, $this->qb()
      ->select('tid', 'connector_id')
      ->from('slots', 's')
      ->groupBy('tid')
      ->orderBy('COUNT(*)', 'DESC')
      ->setMaxResults(10)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ));
  }

  /**
   * {@inheritdoc}
   */
  public function slot($slot_id): ?Slot {
    $slot = $this->qb()->select('*')
      ->from('slots')
      ->where('id = :id')
      ->setParameter(':id', $slot_id)
      ->execute()
      ->fetch(\PDO::FETCH_OBJ);
    if ($slot) {
      return Slot::fromRecord($slot, $this->chunksForSlot($slot->id));
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($slot_id) {
    $return = $this->qb()->delete('slots')
      ->where('id = :id')
      // Can't delete sent entries.
      ->andWhere('teid IS NULL')
      ->setParameter(':id', $slot_id)
      ->execute();
    if ($return) {
      $this->qb()->delete('chunks')->where('sid = :sid')
        ->setParameter('sid', $slot_id)
        ->execute();
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function connection() {
    return $this->connection;
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function removeAlias($ticket_id, $alias) {
    return $this->qb()->delete('aliases')
      ->where('tid = :ticket_id')
      ->andWhere('alias = :alias')
      ->setParameter(':ticket_id', $ticket_id)
      ->setParameter(':alias', $alias)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function loadAlias($alias) {
    return $this->qb()->select('tid')
      ->from('aliases')
      ->where('alias = :alias')
      ->setParameter(':alias', $alias)
      ->execute()
      ->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function totalByTicket($start, $end = NULL): array {
    if (!$end) {
      // Some time in the future.
      $end = time() + 86400;
    }
    $return = $this->qb()->select('s.tid', 's.category', 's.comment', 's.connector_id', 's.teid', 'c.start', 'c.end', 'c.sid')
      ->from('slots', 's')
      ->innerJoin('s', 'chunks', 'c', 'c.sid = s.id')
      ->where('c.start > :start AND c.start < :end')
      ->setParameter(':start', $start)
      ->setParameter(':end', $end)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
    $ticket_map = [];
    foreach ($return as $row) {
      $ticket_map[$row->sid] = $row->tid;
      if (!isset($totals[$row->connector_id][$row->sid])) {
        $totals[$row->connector_id][$row->sid] = 0;
      }
      $totals[$row->connector_id][$row->sid] += (($row->end ?: time()) - $row->start);
    }
    $aggregated = [];
    foreach ($totals as $connector_id => $slots) {
      $aggregated[$connector_id] = array_reduce(array_keys($slots), function (array $carry, $sid) use ($ticket_map, $slots) {
        $carry[$ticket_map[$sid]] = ($carry[$ticket_map[$sid]] ?? 0) + round($slots[$sid] / 900) * 900;
        return $carry;
      }, []);
    }
    return $aggregated;
  }

  /**
   * Inserts a chunk.
   *
   * @param int $start
   *   Start.
   * @param int $end
   *   End.
   * @param int $slot_id
   *   Slot ID.
   */
  protected function insertChunk(int $start, ?int $end, int $slot_id) {
    $chunk = [
      'start' => $start,
      'end' => $end,
      'sid' => $slot_id,
    ];
    $this->qb()->insert('chunks')->values(array_filter($chunk))->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function combine(Slot $slot1, Slot $slot2) {
    $this->qb()->update('chunks')
      ->where('sid = :sid2')
      ->set('sid', ':sid1')
      ->setParameter('sid2', $slot2->getId())
      ->setParameter('sid1', $slot1->getId())->execute();
    $this->delete($slot2->getId());
  }

}
