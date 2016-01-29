<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\ContinueTest.
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Review
 * @group Commands
 */
class ContinueTest extends TlTestBase {

  protected $slotId1;
  protected $slotId2;
  protected $slotId3;
  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturnMap([
        ['1', new Ticket('Do something', 1)],
        ['2', new Ticket('Do something else', 2)],
        ['3', new Ticket('Do something more', 3)],
      ]);
    $repository = $this->getRepository();
    // Five entries for today.
    $start = time();
    // 7 hrs.
    $this->slotId1 = $repository->insert([
      'tid' => 1,
      'start' => $start,
      'end' => $start + 3588 * 7
    ]);
    // 1 hr.
    $this->slotId2 = $repository->insert([
      'tid' => 2,
      'start' => $start,
      'end' => $start + 3600
    ]);
    // 9 hrs.
    $this->slotId3 = $repository->insert([
      'tid' => 3,
      'start' => $start,
      'end' => $start + 3600 * 9
    ]);
    // Yesterday.
    $repository->insert([
      'tid' => 3,
      'start' => $start - 86400,
      'end' => $start - 86400 + 3600 * 9
    ]);
  }

  /**
   * Test the basic functionality of the continue command.
   */
  public function testContinueCommand() {
    $this->setUp();
    $result = $this->executeCommand('continue');
    $this->assertContains('Continued entry for 3: Do something more [slot:' . $this->slotId3 . ']', $result->getDisplay());
    $this->assertTicketIsOpen(3);
    $this->getRepository()->stop();
    $slot = $this->getRepository()->slot($this->slotId3);
    $this->assertGreaterThanOrEqual(3600 * 9, $slot->end - $slot->start);
    $this->assertLessThanOrEqual((3600 * 9) + 60, $slot->end - $slot->start);
  }

  /**
   * Test the basic functionality of the continue command.
   */
  public function testContinueCommandWithSlotId() {
    $this->setUp();
    $result = $this->executeCommand('continue' ,['slot_id' => $this->slotId2]);
    $this->assertContains('Continued entry for 2: Do something else [slot:' . $this->slotId2 . ']', $result->getDisplay());
    $this->assertTicketIsOpen(2);
    $this->getRepository()->stop();
    $slot = $this->getRepository()->slot($this->slotId2);
    $this->assertGreaterThanOrEqual(3600, $slot->end - $slot->start);
    $this->assertLessThanOrEqual(3600 + 60, $slot->end - $slot->start);
  }

}
