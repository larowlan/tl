<?php

namespace Larowlan\Tl;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Defines a class for describing a slot which comprises chunks of time.
 */
class Slot {

  /**
   * Slot ID.
   *
   * @var int
   */
  protected $id;

  /**
   * Ticket ID.
   *
   * @var int
   */
  protected $ticketId;

  /**
   * Remote entry ID.
   *
   * @var int
   */
  protected $remoteEntryId;

  /**
   * Comment.
   *
   * @var string
   */
  protected $comment = '';

  /**
   * Category ID.
   *
   * @var string
   */
  protected $category = '';

  /**
   * Connector ID.
   *
   * @var string
   */
  protected $connectorId = '';

  /**
   * Duration.
   *
   * @var int
   */
  protected $duration;

  /**
   * Project name.
   *
   * @var string
   */
  protected $projectName;

  /**
   * Chunks.
   *
   * @var \Larowlan\Tl\Chunk[]
   */
  protected $chunks = [];

  /**
   * Gets value of Id.
   *
   * @return int
   *   Value of Id.
   */
  #[Groups(['summary'])]
  public function getId(): int {
    return $this->id;
  }

  /**
   * Gets value of Start.
   *
   * @return int
   *   Value of Start.
   */
  public function getStart(): int {
    $this->sortChunks();
    return (int) reset($this->chunks)->getStart();
  }

  /**
   * Gets current timestamp.
   *
   * @return int
   *   Current timestamp.
   */
  protected function now() {
    return (new \DateTime())->getTimestamp();
  }

  /**
   * Gets value of End.
   *
   * @return int
   *   Value of End.
   */
  public function getEnd(): ?int {
    $this->sortChunks();
    return end($this->chunks)->getEnd() ?: $this->now();
  }

  /**
   * Check if the slot is open.
   *
   * @return bool
   *   TRUE if open
   */
  #[Groups(['summary'])]
  public function isOpen() : bool {
    $this->sortChunks();
    return !end($this->chunks)->getEnd();
  }

  /**
   * Gets value of RemoteEntryId.
   *
   * @return int
   *   Value of RemoteEntryId.
   */
  public function getRemoteEntryId(): ?int {
    return $this->remoteEntryId;
  }

  /**
   * Gets value of Comment.
   *
   * @return string
   *   Value of Comment.
   */
  #[Groups(['summary'])]
  public function getComment(): ?string {
    return $this->comment;
  }

  /**
   * Gets value of Category.
   *
   * @return string
   *   Value of Category.
   */
  public function getCategory(): ?string {
    return $this->category;
  }

  /**
   * Gets value of ConnectorId.
   *
   * @return string
   *   Value of ConnectorId.
   */
  public function getConnectorId(): string {
    return $this->connectorId;
  }

  /**
   * Is the chunk active.
   *
   * @return bool
   *   TRUE if it is active.
   */
  public function isActive(): bool {
    return !$this->lastChunk()->getEnd();
  }

  /**
   * Gets the last slot.
   */
  public function lastChunk() {
    $this->sortChunks();
    return end($this->chunks);
  }

  /**
   * Gets value of Duration.
   *
   * @param bool $reset
   *   TRUE to reset.
   * @param bool $rounded
   *   TRUE to round.
   *
   * @return int
   *   Value of Duration.
   */
  public function getDuration($reset = FALSE, $rounded = FALSE): int {
    if (!$this->duration || $reset) {
      $this->duration = array_sum(array_map(function (Chunk $chunk) {
        return ($chunk->getEnd() ?: $this->now()) - $chunk->getStart();
      }, $this->chunks));
    }
    return $rounded ? round($this->duration / 900) * 900 : $this->duration;
  }

  /**
   * Gets value of ProjectName.
   *
   * @return string
   *   Value of ProjectName.
   */
  public function getProjectName(): ?string {
    return $this->projectName;
  }

  /**
   * Sorts the chunks.
   */
  protected function sortChunks(): void {
    uasort($this->chunks, function (Chunk $a, Chunk $b) {
      return $a->getStart() <=> $b->getStart();
    });
  }

  /**
   * Is this slot continued.
   *
   * @return bool
   *   TRUE if continued.
   */
  public function isContinued() : bool {
    return count($this->chunks) > 1;
  }

  /**
   * Creates a slot from DB records.
   *
   * @param object $record
   *   DB Record.
   * @param array $chunk_records
   *   Chunk records.
   *
   * @return \Larowlan\Tl\Slot
   *   Slot.
   */
  public static function fromRecord($record, array $chunk_records) : Slot {
    $static = new static();
    $static->id = $record->id;
    $static->ticketId = $record->tid;
    $static->comment = $record->comment;
    $static->category = $record->category;
    $static->connectorId = $record->connector_id;
    $static->remoteEntryId = $record->teid;
    $static->chunks = array_map(function ($chunk) {
      return new Chunk($chunk->id, $chunk->start, $chunk->end);
    }, $chunk_records);
    return $static;
  }

  /**
   * Gets value of TicketId.
   *
   * @return int
   *   Value of TicketId.
   */
  #[Groups(['summary'])]
  public function getTicketId(): int {
    return $this->ticketId;
  }

  /**
   * Gets value of Chunks.
   *
   * @return \Larowlan\Tl\Chunk[]
   *   Value of Chunks.
   */
  public function getChunks(): array {
    return $this->chunks;
  }

}
