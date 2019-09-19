<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Input;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Edit extends Command implements LogAwareCommand {

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
      ->setName('edit')
      ->setDescription('Edits a time entry. Start time is modified to be duration-ago')
      ->setHelp('Edits a time entry. <comment>Usage:</comment> <info>tl edit [slot ID] [duration in hours]</info>')
      ->addArgument('slot_id', InputArgument::REQUIRED, 'Slot ID to edit')
      ->addArgument('duration', InputArgument::REQUIRED, 'A duration. For example, 15 minutes: 15m or .25 :15. Mixed granularity is also available, for example: 15s, 15m, 1.5h, 1d.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $slot_id = $input->getArgument('slot_id');
    $duration = $input->getArgument('duration');

    try {
      $seconds = Input::parseInterval($duration);
    }
    catch (\Throwable $e) {
      $output->writeln('<error>' . $e->getMessage() . '</error>');
      return 1;
    }

    $slot = $this->repository->slot($slot_id);
    if ($slot === FALSE) {
      $output->writeln('<error>Slot does not exist.</error>');
      return 1;
    }


    if (isset($slot->teid)) {
      $output->writeln('<error>You cannot edit a slot that has been sent to the backend</error>');
      return 1;
    }

    $this->repository->edit($slot_id, $seconds);
    $output->writeln(sprintf('Updated slot %s to <info>%s</info>', $slot_id, Formatter::formatDuration($seconds)));
    return 0;
  }

}
