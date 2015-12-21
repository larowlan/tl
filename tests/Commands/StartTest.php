<?php
/**
 * @file
 * Contains \Larowlan\Tl\Tests\Commands\StartTest.php
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Larowlan\Tl\Tests\TlTestBase;
use Larowlan\Tl\Ticket;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

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
      ->willReturn(new Ticket('Running tests', 123));
    $output = $this->executeCommand('start', ['issue_number' => 1234]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @covers \Larowlan\Tl\Application::doRun
   */
  public function testStartLogging() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $output =  new StreamOutput(fopen('php://memory', 'w', false));
    $command = $this->container->get('app.command.start');
    $command->setApplication($this->application);
    $this->application->setAutoExit(FALSE);
    $this->application->run(new ArrayInput([
      'command' => 'start',
      'issue_number' => 1234,
    ]), $output);
    rewind($output->getStream());
    $display = stream_get_contents($output->getStream());
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $display);
    $this->assertTicketIsOpen(1234);
    $logged = file_get_contents($this->container->getParameter('directory') . '/.tl.log');
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $logged);
  }

  /**
   * @covers ::execute
   */
  public function testStopStart() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->willReturnMap([
        [1234, new Ticket('Running tests', 123)],
        ["1234", new Ticket('Running tests', 123)],
        [4567, new Ticket('Running more tests', 123)],
      ]);
    $output = $this->executeCommand('start', ['issue_number' => 1234]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $active = $this->assertTicketIsOpen(1234);
    $slot_id = $active->id;
    $output = $this->executeCommand('start', ['issue_number' => 4567]);
    $this->assertRegExp('/Closed slot [0-9]+ against ticket 1234/', $output->getDisplay());
    $this->assertRegExp('/Started new entry for 4567: Running more tests/', $output->getDisplay());
    $this->assertTicketIsOpen('4567');
    $closed = $this->getRepository()->slot($slot_id);
    $this->assertNotNull($closed->end);
    $this->assertEquals('1234', $closed->tid);
  }

  /**
   * @covers ::execute
   */
  public function testStartWithComment() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      'comment' => 'Doing stuff',
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertTicketIsOpen(1234, 'Doing stuff');
  }

  /**
   * @covers ::execute
   */
  public function testAssign() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->once())
      ->method('assign')
      ->with(1234)
      ->willReturn(TRUE);
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      '--assign' => TRUE,
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/Ticket 1234 assigned to you/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @covers ::execute
   */
  public function testAssignShortSyntax() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->once())
      ->method('assign')
      ->with(1234)
      ->willReturn(TRUE);
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      '-a' => TRUE,
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/Ticket 1234 assigned to you/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @covers ::execute
   */
  public function testAssignAlreadyAssigned() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->once())
      ->method('assign')
      ->with(1234)
      ->willReturn(FALSE);
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      '--assign' => TRUE,
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/Could not assign ticket/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @covers ::execute
   */
  public function testStatus() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->once())
      ->method('setInProgress')
      ->with(1234)
      ->willReturn(TRUE);
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      '--status' => TRUE
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/Ticket 1234 set to in-progress/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @covers ::execute
   */
  public function testStatusAndAssign() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->once())
      ->method('setInProgress')
      ->with(1234, TRUE)
      ->willReturn(TRUE);
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      '--status' => TRUE,
      '-a' => TRUE,
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/Ticket 1234 set to in-progress/', $output->getDisplay());
    $this->assertRegExp('/Ticket 1234 assigned to you/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @covers ::execute
   */
  public function testStatusAlreadyInProgress() {
    $this->getMockConnector()->expects($this->any())
      ->method('ticketDetails')
      ->with(1234)
      ->willReturn(new Ticket('Running tests', 123));
    $this->getMockConnector()->expects($this->once())
      ->method('setInProgress')
      ->with(1234)
      ->willReturn(FALSE);
    $output = $this->executeCommand('start', [
      'issue_number' => 1234,
      '-s' => TRUE
    ]);
    $this->assertRegExp('/Started new entry for 1234: Running tests/', $output->getDisplay());
    $this->assertRegExp('/Could not update ticket status/', $output->getDisplay());
    $this->assertTicketIsOpen(1234);
  }

  /**
   * @return mixed
   */
  protected function assertTicketIsOpen($ticket_id, $comment = NULL) {
    /** @var Repository $repository */
    $repository = $this->getRepository();
    $active = $repository->getActive();
    $this->assertEquals($ticket_id, $active->tid);
    $this->assertEquals($comment, $active->comment);
    $this->assertNull($active->end);
    $this->assertNull($active->category);
    $this->assertNull($active->teid);
    return $active;
  }

}
