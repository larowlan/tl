<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;

/**
 * The test for tl visit command.
 *
 * @coversDefaultClass \Larowlan\Tl\Commands\Visit
 * @group Commands
 */
class VisitTest extends TlTestBase {

  /**
   * Tests visit command with minimum params.
   *
   * @covers ::execute
   */
  public function testNoActiveVisit() {
    $output = $this->executeCommand('visit');
    $this->assertStringContainsString('No active ticket, please use tl visit {ticket_id} to specifiy a ticket.', $output->getDisplay());
  }

  /**
   * Tests visit command with active ticket.
   *
   * @covers ::execute
   */
  public function testActiveVisit() {
    $this->setupConnector();
    $this->getMockConnector()->expects($this->once())
      ->method('ticketUrl')
      ->with(1234, 'connector.redmine')
      ->willReturn('https://www.jetbrains.com');
    $this->executeCommand('start', [
      'issue_number' => '1234',
    ]);
    $this->assertTicketIsOpen(1234);
    $output = $this->executeCommand('visit');
    $this->assertStringContainsString('Could not find a browser helper.', $output->getDisplay());
  }

  /**
   * Tests visit command with issue param.
   *
   * @covers ::execute
   */
  public function testIssueVisit() {
    $this->setupConnector();
    $this->getMockConnector()->expects($this->once())
      ->method('ticketUrl')
      ->with(1234, 'connector.redmine')
      ->willReturn('https://www.jetbrains.com');
    $output = $this->executeCommand('visit', [
      'issue' => '1234',
    ]);
    $this->assertStringContainsString('Could not find a browser helper.', $output->getDisplay());
  }

  /**
   * Tests visit command with issue alias param.
   *
   * @covers ::execute
   */
  public function testAliasVisit() {
    $this->setupConnector();
    $this->getMockConnector()->expects($this->once())
      ->method('ticketUrl')
      ->with(1234, 'connector.redmine')
      ->willReturn('https://www.jetbrains.com');
    $output = $this->executeCommand('alias', [
      'ticket_id' => 1234,
      'alias' => 'pony',
    ]);
    $this->assertMatchesRegularExpression('/Created new alias/', $output->getDisplay());
    $output = $this->executeCommand('visit', [
      'issue' => 'pony',
    ]);
    $this->assertStringContainsString('Could not find a browser helper.', $output->getDisplay());
  }

  /**
   * Tests visit command with invalid issue.
   *
   * @covers ::execute
   */
  public function testInvalidTicketVisit() {
    $this->getMockConnector()->expects($this->once())
      ->method('spotConnector')
      ->willReturn(FALSE);
    $this->expectException(\InvalidArgumentException::class);
    $this->executeCommand('visit', [
      'issue' => '5678',
    ]);
  }

}
