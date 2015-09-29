<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Combine.php
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
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Combine extends Command {

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
      ->setName('combine')
      ->setDescription('Combine two time entries')
      ->setHelp('Combine two time entries. <comment>Usage:</comment> <info>tl combine slot1 slot2</info>')
      ->addUsage('tl combine slot1 slot2')
      ->addArgument('slot1', InputArgument::REQUIRED)
      ->addArgument('slot2', InputArgument::REQUIRED);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $slot1 = $input->getArgument('slot1');
    $slot2 = $input->getArgument('slot2');
    list($entry1, $entry2) = $this->validateSlots($slot1, $slot2);

    // Create a new combined entry and then remove fields we don't want to keep
    // around in the new entry.
    $combined_entry = clone $entry1;
    unset($combined_entry->id, $combined_entry->tag, $combined_entry->comment, $combined_entry->teid);

    // Extend the entry date by the amount of time logged in the second entry.
    $combined_entry->end = $entry1->end + ($entry2->end - $entry2->start);

    // Insert the new entry, if all is well delete the two existing ones.
    if ($new_slot = $this->repository->insert((array) $combined_entry)) {
      $this->repository->delete($entry1->id);
      $this->repository->delete($entry2->id);

      $output->writeln(sprintf('Combined %s and %s into new slot %s', $slot1, $slot2, $new_slot));
    }
  }

  protected function validateSlots($slot1, $slot2) {
    // Ensure we can load both slots.
    if (!$entry1 = $this->repository->slot($slot1)) {
      throw new \InvalidArgumentException(sprintf('Invalid slot id %s', $slot1));
    }
    if (!$entry2 = $this->repository->slot($slot2)) {
      throw new \InvalidArgumentException(sprintf('Invalid slot id %s', $slot2));
    }
    // Ensure we've not already sent the slots.
    if (!empty($entry1->teid) || !empty($entry1->teid)) {
      throw new \InvalidArgumentException('You cannot combine entries that have already been sent.');
    }
    // Ensure the slots are both against the same job.
    if ($entry1->tid != $entry2->tid) {
      throw new \InvalidArgumentException('You cannot combine entries from separate issues.');
    }
    return [$entry1, $entry2];
  }
}
