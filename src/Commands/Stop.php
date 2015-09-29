<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Stop.php
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

class Stop extends Command {

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
      ->setName('stop')
      ->setDescription('Stops the active time entry')
      ->setHelp('Stops the exiting entry. <comment>Usage:</comment> <info>tl stop</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($stop = $this->repository->stop()) {
      $stopped = $this->connector->ticketDetails($stop->tid);
      $output->writeln(sprintf('<bg=blue;fg=white;options=bold>[%s]</> Closed slot <comment>%d</comment> against ticket <info>%d</info>: %s, duration <info>%s</info>',
        (new \DateTime())->format('h:i'),
        $stop->id,
        $stop->tid,
        $stopped['title'],
        Formatter::formatDuration($stop->duration)
      ));
      return;
    }
    $output->writeln('<error>No active slot</error>');
  }

}
