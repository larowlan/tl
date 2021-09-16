<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Review
 * @group Commands
 */
class ReviewTest extends TlTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturnMap([
        [1, 'connector.redmine', FALSE, new Ticket('Do something', 1)],
        [2, 'connector.redmine', FALSE, new Ticket('Do something else ', 2)],
        [3, 'connector.redmine', FALSE, new Ticket('Do something more', 3)],
      ]);
    $this->getMockConnector()->expects($this->any())
      ->method('spotConnector')
      ->willReturn('connector.redmine');
    $repository = $this->getRepository();
    // Five entries for today.
    $start = time();
    // 7 hrs.
    $repository->insert([
      'tid' => 1,
      'start' => $start,
      'end' => $start + 3588 * 7,
      'connector_id' => ':connector_id',
    ], [':connector_id' => 'connector.redmine']);
    // 1 hr.
    $repository->insert([
      'tid' => 2,
      'start' => $start,
      'end' => $start + 3600,
      'connector_id' => ':connector_id',
    ], [':connector_id' => 'connector.redmine']);
    // 3 hrs.
    $repository->insert([
      'tid' => 3,
      'start' => $start,
      'end' => $start + 3600 * 3,
      'connector_id' => ':connector_id',
    ], [':connector_id' => 'connector.redmine']);
  }

  /**
   * Test the basic functionality of the review command.
   */
  public function testReviewCommand() {
    $this->setUp();
    $result = $this->executeCommand('review');
    $this->assertStringContainsString('11 h', $result->getDisplay());
    $this->assertStringContainsString('Do something', $result->getDisplay());
    $this->assertStringContainsString('Do something else', $result->getDisplay());
    $this->assertStringContainsString('Do something more', $result->getDisplay());
  }

  /**
   * Test the basic functionality of the review command.
   */
  public function testReviewExact() {
    $this->setUp();
    $result = $this->executeCommand('review', ['--exact' => TRUE]);
    $this->assertStringContainsString('10:58:36', $result->getDisplay());
    $this->assertStringContainsString('6:58:36', $result->getDisplay());
    $this->assertStringContainsString('3:00:00', $result->getDisplay());
    $this->assertStringContainsString('Do something', $result->getDisplay());
    $this->assertStringContainsString('Do something else', $result->getDisplay());
    $this->assertStringContainsString('Do something more', $result->getDisplay());
  }

}
