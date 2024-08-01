<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Open extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   *
   */
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
      ->setName('open')
      ->addOption('slot', 's', InputOption::VALUE_NONE, 'Show slot ID only')
      ->setDescription('Shows the open time-entry')
      ->setHelp('Shows the open entry. <comment>Usage:</comment> <info>tl open</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($data = $this->repository->getActive()) {
      $details = $this->connector->ticketDetails($data->getTicketId(), $data->getConnectorId());
      if ($input->getOption('slot')) {
        $output->writeLn($data->getId());
        return 0;
      }
      $output->writeLn(sprintf('%s [<info>%d</info>] - <comment>%s</comment> [slot: <comment>%d</comment>]',
        $details->getTitle(),
        $data->getTicketId(),
        Formatter::formatDuration($data->getDuration()),
        $data->getId()
      ));
      return 0;
    }
    $output->writeln('<error>No active slot</error>');
    return 1;
  }

}
