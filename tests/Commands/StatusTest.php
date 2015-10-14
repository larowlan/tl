<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\StatusTest
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Larowlan\Tl\Tests\TlTestBase;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\StatusTest
 * @group Commands
 */
class StatusTest extends TlTestBase {

  /**
   * Test the basic functionality of the status command.
   */
  public function testStatusCommand() {
    // One entry for today.
    $start = time();
    $this->getRepository()
      ->insert(['tid' => 4, 'start' => $start, 'end' => $start + 3600 * 7]);

    // Today should have 7hrs.
    $result = $this->executeCommand('status');
    $this->assertContains('7:00:00', $result->getDisplay());
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
