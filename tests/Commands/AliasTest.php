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
    $this->setupConnector();
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
    ]);
    $this->assertMatchesRegularExpression('/Created new alias/', $output->getDisplay());
    $output = $this->executeCommand('start', [
      'issue_number' => 'pony',
    ]);
    $this->assertTicketIsOpen(1234);
    $this->assertMatchesRegularExpression('/Started new entry for 1234: Running tests/', $output->getDisplay());
  }

  /**
   * @covers ::execute
   */
  public function testDelete() {
    $this->setupConnector();
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
    ]);
    $this->assertMatchesRegularExpression('/Created new alias/', $output->getDisplay());
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
      '--delete' => TRUE,
    ]);
    $this->assertMatchesRegularExpression('/Removed alias/', $output->getDisplay());
  }

  /**
   * @covers ::execute
   */
  public function testList() {
    $this->setupConnector();
    $aliases = [
      'some',
      'drunk',
      'pony',
    ];
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
    ]);
    $this->assertMatchesRegularExpression('/Missing alias/', $output->getDisplay());
    $output = $this->executeCommand('alias', [
      'alias' => 1234,
    ]);
    $this->assertMatchesRegularExpression('/Missing ticket number/', $output->getDisplay());
    foreach ($aliases as $alias) {
      $output = $this->executeCommand('alias', [
        'ticket_id' => 1234,
        'alias' => $alias,
      ]);
      $this->assertMatchesRegularExpression('/Created new alias/', $output->getDisplay());
    }
    $output = $this->executeCommand('alias', [
      '--list' => TRUE,
    ]);
    foreach ($aliases as $alias) {
      $this->assertMatchesRegularExpression('/' . $alias . '/', $output->getDisplay());
    }
  }

}
