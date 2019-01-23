<?php

namespace Larowlan\Tl;

/**
 * Ticket value object.
 */
class Ticket implements TicketInterface {

  /**
   * Title.
   *
   * @var string
   */
  protected $title;

  /**
   * Project ID.
   *
   * @var mixed
   */
  protected $projectId;

  /**
   * Is billable flag.
   *
   * @var bool
   */
  protected $isBillable = FALSE;

  /**
   * Constructs a new Ticket object.
   *
   * @param string $title
   *   Title title.
   * @param mixed $project_id
   *   Project ID.
   * @param bool $is_billable
   *   Billable status. Defaults to FALSE.
   */
  public function __construct($title, $project_id, $is_billable = FALSE) {
    $this->isBillable = $is_billable;
    $this->projectId = $project_id;
    $this->title = $title;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectId() {
    return $this->projectId;
  }

  /**
   * {@inheritdoc}
   */
  public function isBillable() {
    return $this->isBillable;
  }

}
