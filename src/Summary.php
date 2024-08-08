<?php

namespace Larowlan\Tl;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Value object to represent a review summary.
 */
#[Groups(['summary'])]
class Summary {

  /**
   * Constructs a new Summary.
   */
  public function __construct(
    readonly protected array $items,
    readonly protected int $roundedTotal,
    readonly protected int $exactTotal,
  ) {}

  /**
   * Gets the items.
   *
   * @return array<\Larowlan\Tl\SummaryItem>
   *   The items.
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * Gets the total.
   */
  public function getRoundedTotal(): int {
    return $this->roundedTotal;
  }

  /**
   * Gets the exact total.
   */
  public function getExactTotal(): int {
    return $this->exactTotal;
  }

}
