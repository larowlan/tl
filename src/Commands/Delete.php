<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Delete.php
 */

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Delete extends Command {

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
      ->setName('delete')
      ->setDescription('Deletes an entry')
      ->setHelp('Deletes an entry. <comment>Usage:</comment> <info>tl delete [slot id]</info>')
      ->addUsage('tl delete 6')
      ->addUsage('tl delete 6 -y')
      ->addOption('confirm', 'y', InputOption::VALUE_NONE, 'Confirm deletion')
      ->addArgument('slot_id', InputArgument::REQUIRED, 'Slot to delete');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $slot_id = $input->getArgument('slot_id');
    $helper = $this->getHelper('question');
    $question = new ConfirmationQuestion('Are you sure?', false);

    $confirm = NULL;
    if (($slot = $this->repository->slot($slot_id)) && ($confirm = ($input->hasOption('confirm') || $helper->ask($input, $output, $question))) && $this->repository->delete($slot_id)) {
      $deleted = $this->connector->ticketDetails($slot->tid);
      $output->writeln(sprintf('Deleted slot <comment>%d</comment> against ticket <info>%d</info>: %s, duration <info>%s</info>',
        $slot->id,
        $slot->tid,
        $deleted->getTitle(),
        Formatter::formatDuration($slot->end - $slot->start)
      ));
      return;
    }
    if ($confirm !== FALSE) {
      $output->writeln('<error>Cannot delete slot, either does not exist or has been sent to back end.</error>');
    }
  }

}
