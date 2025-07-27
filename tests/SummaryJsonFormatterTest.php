<?php

namespace Larowlan\Tl\Tests;

use Larowlan\Tl\Slot;
use Larowlan\Tl\Summary;
use Larowlan\Tl\SummaryItem;
use Larowlan\Tl\SummaryJsonFormatter;
use Larowlan\Tl\Ticket;
use PHPUnit\Framework\TestCase;

class SummaryJsonFormatterTest extends TestCase {

  public function testFormatJson(): void {
    $slot1 = $this->createMock(Slot::class);
    $slot1->method('getId')
      ->willReturn(1);
    $slot1->method('getTicketId')
      ->willReturn(101);
    $slot1->method('isOpen')
      ->willReturn(FALSE);
    $slot1->method('getComment')
      ->willReturn('comment1');

    $slot2 = $this->createMock(Slot::class);
    $slot2->method('getId')
      ->willReturn(2);
    $slot2->method('getTicketId')
      ->willReturn(102);
    $slot2->method('isOpen')
      ->willReturn(TRUE);
    $slot2->method('getComment')
      ->willReturn('comment2');

    $summary = new Summary([
      new SummaryItem(
        $slot1,
        new Ticket('ticket1', 'project1', TRUE),
        'category1',
        10,
        10,
      ),
      new SummaryItem(
        $slot2,
        new Ticket('ticket2', 'project2', TRUE),
        'category2',
        20,
        20,
      ),
    ],
      30,
      30,
    );

    $json = SummaryJsonFormatter::formatJson($summary);
    $this->assertJson($json);

    // Compare the expected JSON with the actual JSON stripping out whitespace.
    $expected_raw = file_get_contents(__DIR__ . '/fixtures/summary.json');

    $this->assertEquals($expected_raw, $json . "\n");
  }

}
