<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\StartTest.php
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Larowlan\Tl\Tests\TlTestBase;

/**
 * @coversDefaultClass \Larowlan\Tl\Commands\Start
 * @group Commands
 */
class StartTest extends TlTestBase {

  /**
   * @covers ::execute
   */
  public function testStart() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(['title' => 'Running tests']);
    $output = $this->executeCommand('start', ['issue_number' => 1234]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    /** @var Repository $repository */
    $repository = $this->container->get('repository');
    $active = $repository->getActive();
    $this->assertEquals('1234', $active->tid);
    $this->assertNull($active->comment);
    $this->assertNull($active->end);
    $this->assertNull($active->category);
    $this->assertNull($active->teid);
    return $active->id;
  }

  /**
   * @covers ::execute
   */
  public function testStopStart() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturnMap([
        [1234, ['title' => 'Running tests']],
        [4567, ['title' => 'Running more tests']],
      ]);
    $output = $this->executeCommand('start', ['issue_number' => 1234]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    /** @var Repository $repository */
    $repository = $this->container->get('repository');
    $active = $repository->getActive();
    $slot_id = $active->id;
    $output = $this->executeCommand('start', ['issue_number' => 4567]);
    $this->assertRegExp('/Closed slot [0-9]+ against ticket 1234/', $output->getDisplay());
    $this->assertRegExp('/Started new entry for 4567: Running more tests/', $output->getDisplay());

    $active = $repository->getActive();
    $this->assertEquals('4567', $active->tid);
    $this->assertNull($active->comment);
    $this->assertNull($active->end);
    $this->assertNull($active->category);
    $this->assertNull($active->teid);
    $closed = $repository->slot($slot_id);
    $this->assertNotNull($closed->end);
    $this->assertEquals('1234', $closed->tid);
  }

}
