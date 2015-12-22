<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\ReviewTest.
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Review
 * @group Commands
 */
class ReviewTest extends TlTestBase {

  /**
   * Test the basic functionality of the review command.
   */
  public function testReviewCommand() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturnMap([
        ['1', new Ticket('Do something', 1)],
        ['2', new Ticket('Do something else ', 2)],
        ['3', new Ticket('Do something more', 3)],
      ]);
    $repository = $this->getRepository();
    // Five entries for today.
    $start = time();
    // 7 hrs.
    $repository->insert(['tid' => 1, 'start' => $start, 'end' => $start + 3588 * 7]);
    // 1 hr.
    $repository->insert(['tid' => 2, 'start' => $start, 'end' => $start + 3600]);
    // 3 hrs.
    $repository->insert(['tid' => 3, 'start' => $start, 'end' => $start + 3600 * 3]);
    $result = $this->executeCommand('review');
    $this->assertContains('11 h', $result->getDisplay());
    $this->assertContains('Do something', $result->getDisplay());
    $this->assertContains('Do something else', $result->getDisplay());
    $this->assertContains('Do something more', $result->getDisplay());
  }
}
