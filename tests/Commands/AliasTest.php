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
    // Delete by alias name (ticket_id arg used as the alias name), confirm yes.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 'pony',
      '--delete' => TRUE,
    ], ['y']);
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

  /**
   * @covers ::execute
   * Tests interactive add loop.
   */
  public function testInteractiveAdd() {
    $this->setupConnector();
    // Simulate: ticket=1234, alias=myproject, then no to "Add another?"
    $output = $this->executeCommand('alias', [], [
      '1234',
      'myproject',
      'n',
    ]);
    $this->assertMatchesRegularExpression('/Created new alias/', $output->getDisplay());
    // Verify alias was stored.
    $existing = $this->getRepository()->findAlias('myproject');
    $this->assertNotNull($existing);
    $this->assertEquals(1234, $existing->tid);
  }

  /**
   * @covers ::execute
   * Tests interactive add loop with multiple aliases.
   */
  public function testInteractiveAddMultiple() {
    $this->setupConnector();
    // First: ticket=1234, alias=proj1, yes to another
    // Second: ticket=5678, alias=proj2, no to another
    $output = $this->executeCommand('alias', [], [
      '1234',
      'proj1',
      'y',
      '5678',
      'proj2',
      'n',
    ]);
    $display = $output->getDisplay();
    $this->assertMatchesRegularExpression('/Created new alias/', $display);
    $this->assertNotNull($this->getRepository()->findAlias('proj1'));
    $this->assertNotNull($this->getRepository()->findAlias('proj2'));
  }

  /**
   * @covers ::execute
   * Tests duplicate prevention with overwrite prompt — user says yes.
   */
  public function testDuplicateAddOverwrite() {
    $this->setupConnector();
    // Create original alias.
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    // Try to add same alias pointing to a different ticket — confirm overwrite.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 5678,
      'alias' => 'pony',
    ], ['y']);
    $this->assertMatchesRegularExpression('/Updated alias/', $output->getDisplay());
    $existing = $this->getRepository()->findAlias('pony');
    $this->assertEquals(5678, $existing->tid);
  }

  /**
   * @covers ::execute
   * Tests duplicate prevention with overwrite prompt — user says no.
   */
  public function testDuplicateAddSkip() {
    $this->setupConnector();
    // Create original alias.
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    // Try to add same alias — decline overwrite.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 5678,
      'alias' => 'pony',
    ], ['n']);
    $this->assertMatchesRegularExpression('/Skipped/', $output->getDisplay());
    // Alias still points to original ticket.
    $existing = $this->getRepository()->findAlias('pony');
    $this->assertEquals(1234, $existing->tid);
  }

  /**
   * @covers ::execute
   * Tests deleting a single alias by name.
   */
  public function testDeleteByName() {
    $this->setupConnector();
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    // alias arg is the first positional; pass as ticket_id since alias is 2nd.
    // With --delete and only one arg provided, it reads ticket_id as the alias name.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 'pony',
      '--delete' => TRUE,
    ], ['y']);
    $this->assertMatchesRegularExpression('/Removed alias/', $output->getDisplay());
    $this->assertNull($this->getRepository()->findAlias('pony'));
  }

  /**
   * @covers ::execute
   * Tests deleting a single alias by name — user cancels.
   */
  public function testDeleteByNameCancelled() {
    $this->setupConnector();
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    $output = $this->executeCommand('alias', [
      'ticket_id' => 'pony',
      '--delete' => TRUE,
    ], ['n']);
    $this->assertMatchesRegularExpression('/Cancelled/', $output->getDisplay());
    $this->assertNotNull($this->getRepository()->findAlias('pony'));
  }

  /**
   * @covers ::execute
   * Tests editing an alias — rename and re-target.
   */
  public function testEdit() {
    $this->setupConnector();
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    // Edit: new alias name = "unicorn", new ticket = 5678.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 'pony',
      '--edit' => TRUE,
    ], ['unicorn', '5678']);
    $this->assertMatchesRegularExpression('/Updated alias/', $output->getDisplay());
    $this->assertNull($this->getRepository()->findAlias('pony'));
    $updated = $this->getRepository()->findAlias('unicorn');
    $this->assertNotNull($updated);
    $this->assertEquals(5678, $updated->tid);
  }

  /**
   * @covers ::execute
   * Tests editing an alias — keep same name, change ticket only.
   */
  public function testEditTicketOnly() {
    $this->setupConnector();
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    // Press Enter to keep alias name, change ticket to 5678.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 'pony',
      '--edit' => TRUE,
    ], ['', '5678']);
    $this->assertMatchesRegularExpression('/Updated alias/', $output->getDisplay());
    $updated = $this->getRepository()->findAlias('pony');
    $this->assertNotNull($updated);
    $this->assertEquals(5678, $updated->tid);
  }

  /**
   * @covers ::execute
   * Tests editing with no changes made.
   */
  public function testEditNoChanges() {
    $this->setupConnector();
    $this->executeCommand('alias', ['ticket_id' => 1234, 'alias' => 'pony']);
    // Keep both values unchanged.
    $output = $this->executeCommand('alias', [
      'ticket_id' => 'pony',
      '--edit' => TRUE,
    ], ['', '']);
    $this->assertMatchesRegularExpression('/No changes made/', $output->getDisplay());
    $existing = $this->getRepository()->findAlias('pony');
    $this->assertEquals(1234, $existing->tid);
  }

}
