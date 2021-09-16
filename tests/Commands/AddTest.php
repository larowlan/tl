<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Slot;
use Larowlan\Tl\Tests\TlTestBase;

/**
 * The test for tl add command.
 *
 * @coversDefaultClass \Larowlan\Tl\Commands\Add
 * @group Commands
 */
class AddTest extends TlTestBase {

  /**
   * Tests add command with minimum params.
   *
   * @covers ::execute
   */
  public function testAdd() {
    $this->setupConnector();
    $now = new \DateTime();
    $output = $this->executeCommand('add', [
      'issue_number' => 1234,
      'duration' => .25,
    ]);
    $this->assertMatchesRegularExpression('/' . $now->format('Y-m-d h:i') . '/', $output->getDisplay());
    $this->assertMatchesRegularExpression('/Added entry for 1234: Running tests/', $output->getDisplay());
    $this->assertMatchesRegularExpression('/for 15:00 m/', $output->getDisplay());
    $slot = $this->assertSlotAdded(1234);
    $this->assertEquals(.25 * 60 *60, $slot->getDuration());
    $this->assertEquals((int) $now->format('U'), $slot->getStart());

  }

  /**
   * Tests add command with comment argument.
   *
   * @covers ::execute
   */
  public function testAddWithComment() {
    $this->setupConnector();
    $now = new \DateTime();
    $output = $this->executeCommand('add', [
      'issue_number' => 1234,
      'duration' => '1h',
      'comment' => 'Doing stuff',
    ]);
    $this->assertMatchesRegularExpression('/' . $now->format('Y-m-d h:i') . '/', $output->getDisplay());
    $this->assertMatchesRegularExpression('/Added entry for 1234: Running tests/', $output->getDisplay());
    $this->assertMatchesRegularExpression('/for 1:00:00/', $output->getDisplay());
    $slot = $this->assertSlotAdded(1234, 'Doing stuff');
    $this->assertEquals(1 * 60 *60, $slot->getDuration());
    $this->assertEquals((int) $now->format('U'), $slot->getStart());
  }
  /**
   * Tests add command with start params.
   *
   * @covers ::execute
   */
  public function testAddInPast() {
    $this->setupConnector();
    $start = '11 am';
    $time = new \DateTime($start);
    $output = $this->executeCommand('add', [
      'issue_number' => 1234,
      'duration' => 3.25,
      '--start' => $start,
    ]);
    $this->assertMatchesRegularExpression('/' . $time->format('Y-m-d h:i') . '/', $output->getDisplay());
    $this->assertMatchesRegularExpression('/Added entry for 1234: Running tests/', $output->getDisplay());
    $this->assertMatchesRegularExpression('/for 3:15:00./', $output->getDisplay());
    $slot = $this->assertSlotAdded(1234);
    $this->assertEquals(3.25 * 60 *60, $slot->getDuration());
    $this->assertEquals((int) $time->format('U'), $slot->getStart());
  }

  /**
   * Asserts that slot is added.
   *
   * @param string $ticket_id
   *   The ticket ID.
   * @param string $comment
   *   (Optional) Comment.
   *
   * @return \Larowlan\Tl\Slot
   *   Slot.
   */
  protected function assertSlotAdded($ticket_id, $comment = NULL) : Slot {
    /** @var \Larowlan\Tl\Repository\Repository $repository */
    $repository = $this->getRepository();
    $slot = $repository->latest();
    $this->assertEquals($ticket_id, $slot->getTicketId());
    $this->assertEquals($comment, $slot->getComment());
    $this->assertNotNull($slot->lastChunk()->getEnd());
    $this->assertNull($slot->getCategory());
    $this->assertNull($slot->getRemoteEntryId());
    return $slot;
  }

}
