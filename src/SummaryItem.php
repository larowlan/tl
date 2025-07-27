<?php

namespace Larowlan\Tl;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Value object to represent a summary item.
 */
#[Groups(['summary'])]
class SummaryItem {

  /**
   * Constructs a new SummaryItem.
   */
  public function __construct(
    readonly protected Slot $slot,
    readonly protected TicketInterface $ticket,
    readonly protected string $category,
    readonly protected float $exactDuration,
    readonly protected float $roundedDuration,
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
  public function getExactDuration(): float {
    return $this->exactDuration;
  }

  /**
   * Gets the rounded duration.
   */
  public function getRoundedDuration(): float {
    return $this->roundedDuration;
  }

}
