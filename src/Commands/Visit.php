<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Visit.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Visit extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  public function __construct(Connector $connector, Repository $repository) {
    $this->connector = $connector;
    $this->repository = $repository;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('visit')
      ->setDescription('Opens the active time-entry in the browser')
      ->setHelp('Opens the current time-entry in the browser')
      ->addUsage('tl visit')
      ->addUsage('tl visit 123456')
      ->addArgument('issue', InputArgument::OPTIONAL, 'Optional issue to open');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!($issue_number = $input->getArgument('issue'))) {
      if ($data = $this->repository->getActive()) {
        $issue_number = $data->tid;
      }
    }
    if (!$issue_number) {
      $output->writeln('<error>No active ticket, please use tl visit {ticket_id} to specifiy a ticket.</error>');
      return;
    }
    $url = $this->connector->ticketUrl($issue_number);
    $this->open($url, $output);
  }

  /**
   * Opens item in browser if possible.
   *
   * @param string $url
   *   URL to open.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output interface.
   */
  protected function open($url, OutputInterface $output) {
    // See if we can find an OS helper to open URLs in default browser.
    $browser = FALSE;
    if (shell_exec('which xdg-open')) {
      $browser = 'xdg-open';
    }
    elseif (shell_exec('which open')) {
      $browser = 'open';
    }
    elseif (substr(PHP_OS, 0, 3) == 'WIN') {
      $browser = 'start';
    }

    if ($browser) {
      shell_exec($browser . ' ' . escapeshellarg($url));
      return;
    }
    else {
      // Can't find assets valid browser.
      $output->writeln('<error>Could not find a browser helper.</error>');
    }
  }

}
