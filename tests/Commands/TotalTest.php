<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Total
 * @group Commands
 */
class TotalTest extends TlTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturn(new Ticket('Running tests', 123));
  }

  /**
   * Test the basic functionality of the total command.
   */
  public function testTotalCommand() {
    $repository = $this->getRepository();
    // One entry for today.
    $start = time();
    $repository->insert(['tid' => 1, 'start' => $start, 'end' => $start + 3600 * 7]);

    $result = $this->executeCommand('total');
    $this->assertStringContainsString('7:00:00', $result->getDisplay());
  }

}
