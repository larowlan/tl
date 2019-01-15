<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

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

  /**
   * @covers ::execute
   */
  public function testList() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $aliases = [
      'some',
      'drunk',
      'pony',
    ];
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
    ]);
    $this->assertRegExp('/Missing alias/', $output->getDisplay());
    $output = $this->executeCommand('alias', [
      'alias' => 1234,
    ]);
    $this->assertRegExp('/Missing ticket number/', $output->getDisplay());
    foreach ($aliases as $alias) {
      $output = $this->executeCommand('alias', [
        'ticket_id' => 1234,
        'alias' => $alias,
      ]);
      $this->assertRegExp('/Created new alias/', $output->getDisplay());
    }
    $output = $this->executeCommand('alias', [
      '--list' => TRUE,
    ]);
    foreach ($aliases as $alias) {
      $this->assertRegExp('/' . $alias . '/', $output->getDisplay());
    }
  }

}
