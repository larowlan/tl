<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\StatusTest
 * @group Commands
 */
class StatusTest extends TlTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturn(new Ticket('Running tests', 123));
  }

  /**
   * Test the basic functionality of the status command.
   */
  public function testStatusCommand() {
    $repository = $this->getRepository();
    // One entry for today.
    $start = time();
    $repository->insert(['tid' => 1, 'start' => $start, 'end' => $start + 3600 * 7]);

    // Two entries for yesterday.
    $start = time() - 86400;
    $repository->insert(['tid' => 1, 'start' => $start, 'end' => $start + 3600]);
    $repository->insert(['tid' => 2, 'start' => $start, 'end' => $start + 3600 * 3]);

    // One entries for day before yesterday.
    $start = time() - (86400 * 2);
    $repository->insert(['tid' => 3, 'start' => $start, 'end' => $start + 3600]);

    // Today should have 7hrs.
    $result = $this->executeCommand('status');
    $this->assertContains('7:00:00', $result->getDisplay());

    // Yesterday should be 4hrs.
    $result = $this->executeCommand('status', ['date' => '_1']);
    $this->assertContains('4:00:00', $result->getDisplay());

    // Day before yesterday, 1hr.
    $result = $this->executeCommand('status', ['date' => '_2']);
    $this->assertContains('1:00:00', $result->getDisplay());
  }

  /**
   * Test that the date parameter works for the status command.
   */
  public function testStatusWithDateParam() {
    $repository = $this->getRepository();

    // Two entries for day 1.
    $start = strtotime('2pm December 10 2014');
    $repository->insert(['tid' => 1, 'start' => $start, 'end' => $start + 3600]);
    $repository->insert(['tid' => 2, 'start' => $start, 'end' => $start + 3600]);
    // One entry for day 2.
    $start = strtotime('2pm December 11 2014');
    $repository->insert(['tid' => 3, 'start' => $start, 'end' => $start + 3600 * 3]);

    // Day 1 should have two hours.
    $result = $this->executeCommand('status', ['date' => '2014-12-10']);
    $this->assertContains('2:00:00', $result->getDisplay());

    // Day 2 should have 3hrs exactly.
    $result = $this->executeCommand('status', ['date' => '2014-12-11']);
    $this->assertContains('3:00:00', $result->getDisplay());
  }

}
