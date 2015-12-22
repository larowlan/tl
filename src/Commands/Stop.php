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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Stop extends Command implements LogAwareCommand {

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
      ->addOption('pause', 'p', InputOption::VALUE_NONE, 'Set status to paused')
      ->addOption('comment', 'c', InputOption::VALUE_OPTIONAL, 'Set status to paused and leave comment')
      ->addUsage('tl stop')
      ->addUsage('tl stop -p')
      ->addUsage('tl stop -c "Pausing for now"')
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
        $stopped->getTitle(),
        Formatter::formatDuration($stop->duration)
      ));
      if (($comment = $input->getOption('comment')) || $input->getOption('pause')) {
        if ($this->connector->pause($stop->tid, $comment)) {
          $output->writeln(sprintf('Ticket <comment>%s</comment> set to paused.', $stop->tid));
        }
        else {
          $output->writeln('<error>Could not update ticket status</error>');
        }
      }
      return;
    }
    $output->writeln('<error>No active slot</error>');
  }

}
