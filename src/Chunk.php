<?php

namespace Larowlan\Tl;

/**
 * Defines a class for modelling a chunk of spent time.
 */
class Chunk {

  /**
   * Chunk ID.
   *
   * @var int
   */
  protected $id;

  /**
   * Start timestamp.
   *
   * @var int
   */
  protected $start;

  /**
   * End timestamp.
   *
   * @var int
   */
  protected $end;

  /**
   * Constructs a new Chunk.
   *
   * @param int $id
   *   ID.
   * @param int $start
   *   Start.
   * @param int $end
   *   End.
   */
  public function __construct(int $id, int $start, ?int $end) {
    $this->id = $id;
    $this->start = $start;
    $this->end = $end;
  }

  /**
   * Gets value of Start.
   *
   * @return int
   *   Value of Start.
   */
  public function getStart(): int {
    return $this->start;
  }

  /**
   * Gets value of End.
   *
   * @return int
   *   Value of End.
   */
  public function getEnd(): ?int {
    return $this->end;
  }

  /**
   * Gets value of Id.
   *
   * @return int
   *   Value of Id.
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * Sets value of End.
   *
   * @param int $end
   *   Value for End.
   *
   * @return $this
   */
  public function setEnd(int $end): Chunk {
    $this->end = $end;
    return $this;
  }

}
