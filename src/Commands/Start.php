<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Start.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Start extends Command implements CompletionAwareInterface {

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
      ->setName('start')
      ->setDescription('Starts a time entry')
      ->setHelp('Starts a new entry, closes existing one. <comment>Usage:</comment> <info>tl start [ticket number]</info>')
      ->addArgument('issue_number', InputArgument::REQUIRED, 'Issue number to start work on')
      ->addArgument('comment', InputArgument::OPTIONAL, 'Comment to start with')
      ->addOption('status', 's', InputOption::VALUE_NONE, 'Set issue to in progress')
      ->addOption('assign', 'a', InputOption::VALUE_NONE, 'Assign issue to you')
      ->addUsage('tl start 12355')
      ->addUsage('tl start 12355 "Doin stuff"')
      ->addUsage('tl start 12345 -a')
      ->addUsage('tl start 12345 --assign')
      ->addUsage('tl start 12345 -a -s')
      ->addUsage('tl start 12345 --assign --status')
      ->addUsage('tl start 12345 --status')
      ->addUsage('tl start 12345 -s');

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $ticket_id = $input->getArgument('issue_number');
    if ($alias = $this->repository->loadAlias($ticket_id)) {
      $ticket_id = $alias;
    }
    if ($title = $this->connector->ticketDetails($ticket_id)) {
      if ($stop = $this->repository->stop()) {
        $stopped = $this->connector->ticketDetails($stop->tid);
        $output->writeln(sprintf('Closed slot <comment>%d</comment> against ticket <info>%d</info>: %s, duration <info>%s</info>',
          $stop->id,
          $stop->tid,
          $stopped['title'],
          Formatter::formatDuration($stop->duration)
        ));
      }
      try {
        list($slot_id, $continued) = $this->repository->start($ticket_id, $input->getArgument('comment'));
        $output->writeln(sprintf('<bg=blue;fg=white;options=bold>[%s]</> <comment>%s</comment> entry for <info>%d</info>: %s [slot:<comment>%d</comment>]',
          (new \DateTime())->format('h:i'),
          $continued ? 'Continued' : 'Started new',
          $ticket_id,
          $title['title'],
          $slot_id
        ));
        if ($input->getOption('status')) {
          if ($this->connector->setInProgress($ticket_id, $assign = $input->getOption('assign'))) {
            $output->writeln(sprintf('Ticket <comment>%s</comment> set to in-progress.', $ticket_id));
            if ($assign) {
              $output->writeln(sprintf('Ticket <comment>%s</comment> assigned to you.', $ticket_id));
            }
          }
          else {
            $output->writeln('<error>Could not update ticket status</error>');
            if ($assign) {
              $output->writeln('<error>Could not assign ticket</error>');
            }
          }
        }
        elseif ($input->getOption('assign')) {
          if ($this->connector->assign($ticket_id)) {
            $output->writeln(sprintf('Ticket <comment>%s</comment> assigned to you.',
              $ticket_id));
          }
          else {
            $output->writeln('<error>Could not assign ticket</error>');
          }
        }
      }
      catch (\Exception $e) {
        $output->writeln(sprintf('<error>Error creating slot: %s</error>',  $e->getMessage()));
      }
    }
    else {
      $output->writeln('<error>Error: no such ticket id or access denied</error>');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function completeOptionValues($optionName, CompletionContext $context) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function completeArgumentValues($argumentName, CompletionContext $context) {
    $aliases = [];
    if ($argumentName === 'issue_number') {
      // Get all the aliases that are similar to our current search.
      $results = $this->repository->listAliases($context->getWordAtIndex(2));
      foreach ($results as $alias) {
        $aliases[] = $alias->alias;
      }
    }
    return $aliases;
  }

}
