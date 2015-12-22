<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\AliasTest.php
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Alias
 * @group Commands
 */
class AliasTest extends TlTestBase {

  /**
   * @covers ::execute
   */
  public function testCreate() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
    ]);
    $this->assertRegExp('/Created new alias/', $output->getDisplay());
    $output = $this->executeCommand('start', [
      'issue_number' => 'pony',
    ]);
    $this->assertTicketIsOpen(1234);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
  }

  /**
   * @covers ::execute
   */
  public function testDelete() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
    ]);
    $this->assertRegExp('/Created new alias/', $output->getDisplay());
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
      '--delete' => TRUE,
    ]);
    $this->assertRegExp('/Removed alias/', $output->getDisplay());
  }

}
