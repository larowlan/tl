<?php

namespace Larowlan\Tl;

/**
 * Value object to represent a summary item.
 */
class SummaryItem {

  /**
   * Constructs a new SummaryItem.
   */
  public function __construct(
    readonly protected Slot $slot,
    readonly protected TicketInterface $ticket,
    readonly protected string $category,
    readonly protected int $exactDuration,
    readonly protected int $roundedDuration,
  ) {}

  /**
   * Gets the slot.
   */
  public function getSlot(): Slot {
    return $this->slot;
  }

  /**
   * Gets the ticket.
   */
  public function getTicket(): TicketInterface {
    return $this->ticket;
  }

  /**
   * Gets the category.
   */
  public function getCategory(): string {
    return $this->category;
  }

  /**
   * Gets the exact duration.
   */
  public function getExactDuration(): int {
    return $this->exactDuration;
  }

  /**
   * Gets the rounded duration.
   */
  public function getRoundedDuration(): int {
    return $this->roundedDuration;
  }

}
