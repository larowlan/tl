<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
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
      ->setDescription('Edits a time entry')
      ->setHelp('Edits a time entry. <comment>Usage:</comment> <info>tl edit [slot ID] [duration in hours]</info>')
      ->addArgument('slot_id', InputArgument::REQUIRED, 'Slot ID to edit')
      ->addArgument('duration', InputArgument::REQUIRED, 'Duration in hours to change slot to');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $slot_id = $input->getArgument('slot_id');
    $duration = $input->getArgument('duration');
    $slot = $this->repository->slot($slot_id);
    if (isset($slot->teid)) {
      $output->writeln('<error>You cannot edit a slot that has been sent to the backend</error>');
      return 1;
    }
    $this->repository->edit($slot_id, $duration);
    $output->writeln(sprintf('Updated slot %s to <info>%s h</info>', $slot_id, $duration));
  }

}
