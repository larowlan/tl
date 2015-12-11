<?php
/**
 * @file
 * Contains Ticket.php
 */

namespace Larowlan\Tl;


class Ticket implements TicketInterface {

  /**
   * @var string
   */
  protected $title;

  /**
   * @var mixed
   */
  protected $projectId;

  /**
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
   *
   * @deprecated
   */
  public function offsetExists($offset) {
    return in_array($offset, ['title', 'projectId', 'isBillable']);
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated
   */
  public function offsetGet($offset) {
    return $this->{$offset};
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated
   */
  public function offsetSet($offset, $value) {
    $this->{$offset} = $value;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated
   */
  public function offsetUnset($offset) {
    unset($this->{$offset});
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
