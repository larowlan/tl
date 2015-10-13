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
    $this->assertRegExp('/Started new entry for 1234/', $output->getDisplay());
    /** @var Repository $repository */
    $repository = $this->container->get('repository');
    $active = $repository->getActive();
    $this->assertEquals('1234', $active->tid);
    $this->assertNull($active->comment);
    $this->assertNull($active->end);
    $this->assertNull($active->category);
    $this->assertNull($active->teid);
  }

}
