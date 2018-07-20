<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\SendTest.php
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Send
 * @group Commands
 */
class SendTest extends TlTestBase {

  /**
   * @covers ::execute
   */
  public function testSend() {
    // Start time log on a ticket.
    $this->executeCommand('start', [
      'issue_number' => 1234,
      'comment' => 'Foo bar',
    ]);
    $this->getRepository()->tag(9, 1234);
    $this->assertTicketIsOpen(1234, 'Foo bar');
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 1234));
    $output = $this->executeCommand('send');
    $this->assertRegExp('/Please stop open time logs first: Running tests [1234]/', $output->getDisplay());
  }

}
