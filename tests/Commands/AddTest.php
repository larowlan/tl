<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

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
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234, 'connector.jira')
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->any())
      ->method('spotConnector')
      ->willReturn('connector.jira');
    $output = $this->executeCommand('add', [
      'issue_number' => 1234,
      'duration' => .25,
    ]);
    $now = new \DateTime();
    $this->assertRegExp('/' . $now->format('Y-m-d h:i') . '/', $output->getDisplay());
    $this->assertRegExp('/Added entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/for 15:00 m/', $output->getDisplay());
    $slot = $this->assertSlotAdded(1234);
    $this->assertEquals((int) $now->format('U') + .25 * 60 *60, $slot->end);
  }

  /**
   * Tests add command with comment argument.
   *
   * @covers ::execute
   */
  public function testAddWithComment() {
    $this->setupConnector();
    $output = $this->executeCommand('add', [
      'issue_number' => 1234,
      'duration' => '1h',
      'comment' => 'Doing stuff',
    ]);
    $now = new \DateTime();
    $this->assertRegExp('/' . $now->format('Y-m-d h:i') . '/', $output->getDisplay());
    $this->assertRegExp('/Added entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/for 1:00:00/', $output->getDisplay());
    $slot = $this->assertSlotAdded(1234, 'Doing stuff');
    $this->assertEquals((int) $now->format('U') + 1 * 60 *60, $slot->end);
  }
  /**
   * Tests add command with start params.
   *
   * @covers ::execute
   */
  public function testAddInPast() {
    $start = '11 am';
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234, 'connector.jira')
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->any())
      ->method('spotConnector')
      ->willReturn('connector.jira');
    $output = $this->executeCommand('add', [
      'issue_number' => 1234,
      'duration' => 3.25,
      '--start' => $start,
    ]);
    $time = new \DateTime($start);
    $this->assertRegExp('/' . $time->format('Y-m-d h:i') . '/', $output->getDisplay());
    $this->assertRegExp('/Added entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/for 3:15:00./', $output->getDisplay());
    $slot = $this->assertSlotAdded(1234);
    $this->assertEquals((int) $time->format('U') + 3.25 * 60 *60, $slot->end);
  }

  /**
   * Asserts that slot is added.
   *
   * @param string $ticket_id
   *   The ticket ID.
   * @param string $comment
   *   (Optional) Comment.
   *
   * @return object
   */
  protected function assertSlotAdded($ticket_id, $comment = NULL) {
    /** @var \Larowlan\Tl\Repository\Repository $repository */
    $repository = $this->getRepository();
    $slot = $repository->latest();
    $this->assertEquals($ticket_id, $slot->tid);
    $this->assertEquals($comment, $slot->comment);
    $this->assertNotNull($slot->end);
    $this->assertNull($slot->category);
    $this->assertNull($slot->teid);
    return $slot;
  }

}
