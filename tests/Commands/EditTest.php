<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Chunk;
use Larowlan\Tl\Slot;
use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Edit
 * @group Commands
 */
class EditTest extends TlTestBase {

  /**
   * Slot.
   *
   * @var \Larowlan\Tl\Slot
   */
  protected $slot;

  /**
   * Start time.
   *
   * @var int
   */
  protected $start;
  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturnMap([
        [1, 'connector.redmine', FALSE, new Ticket('Do something', 1)],
        [2, 'connector.redmine', FALSE, new Ticket('Do something else', 2)],
        [3, 'connector.redmine', FALSE, new Ticket('Do something more', 3)],
      ]);
    $this->getMockConnector()->expects($this->any())
      ->method('spotConnector')
      ->willReturn('connector.redmine');
    $repository = $this->getRepository();
    // Five entries for today.
    $this->start = time();
    // 7 hrs.
    $repository->insert([
      'tid' => 1,
      'start' => $this->start,
      'end' => $this->start + 3588 * 7,
      'connector_id' => ':connector_id',
    ], [':connector_id' => 'connector.redmine']);
    // Add another chunk, 1 hour long.
    $this->slot = $repository->start(1, 'connector.redmine');
    $repository->stop($this->slot->getId());
  }

  /**
   * Test the basic functionality of the edit command.
   */
  public function testEditIncreaseCommand() {
    $this->setUp();
    $result = $this->executeCommand('edit', [
      'slot_id' => $this->slot->getId(),
      'duration' => '8h',
    ]);
    $this->assertStringContainsString(sprintf('Updated slot %d to 8:00:00', $this->slot->getId()), $result->getDisplay());
    $slots = $this->getRepository()->review();
    $total = array_reduce($slots, function (int $carry, Slot $slot) {
      return $carry + $slot->getDuration();
    }, 0);
    $this->assertEquals(8 * 3600, $total);
  }

  /**
   * Test the basic functionality of the edit command.
   */
  public function testEditDecreaseCommand() {
    $this->setUp();
    $result = $this->executeCommand('edit', [
      'slot_id' => $this->slot->getId(),
      'duration' => '6h',
    ]);
    $this->assertStringContainsString(sprintf('Updated slot %d to 6:00:00', $this->slot->getId()), $result->getDisplay());
    $slots = $this->getRepository()->review();
    $total = array_reduce($slots, function (int $carry, Slot $slot) {
      return $carry + $slot->getDuration();
    }, 0);
    $this->assertEquals(6 * 3600, $total);
  }

  /**
   * Test the basic functionality of the edit command when ticket is open
   */
  public function testEditWhileOpenCommand() {
    $this->setUp();
    $slot = $this->getRepository()->start(2, 'connector.redmine');
    $slots = $this->getRepository()->review();
    $this->assertCount(1, end($slots)->getChunks());
    $result = $this->executeCommand('edit', [
      'slot_id' => $slot->getId(),
      'duration' => .25,
    ]);
    $this->assertStringContainsString(sprintf('Updated slot %d to 15:00 m', $slot->getId()), $result->getDisplay());
    $slots = $this->getRepository()->review();
    $total = array_reduce($slots, function (int $carry, Slot $theSlot) use ($slot) {
      return $carry + $theSlot->getId() === $slot->getId() ? $theSlot->getDuration() : 0;
    }, 0);
    $this->assertEquals(0.25 * 3600, $total);
    $this->assertCount(1, end($slots)->getChunks());
  }

}
