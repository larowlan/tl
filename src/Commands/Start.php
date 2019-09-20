<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Connector\ConnectorManager;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Start extends Command implements CompletionAwareInterface, LogAwareCommand {

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
  public function __construct(ConnectorManager $connector, Repository $repository) {
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
      ->addOption('backend', 'b', InputOption::VALUE_OPTIONAL, 'Backend to use')
      ->addOption('redmine-comment', 'r', InputOption::VALUE_REQUIRED, 'Redmine comment')
      ->addUsage('tl start 12355')
      ->addUsage('tl start 12355 "Doin stuff"')
      ->addUsage('tl start 12345 -a')
      ->addUsage('tl start 12345 --assign')
      ->addUsage('tl start 12345 -a -s')
      ->addUsage('tl start 12345 -a -r "Taking a look"')
      ->addUsage('tl start 12345 -a --redmine-comment "Taking a looksie"')
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
    if ($connector_id = $input->getOption('backend')) {
      $connector_id = 'connector.' . $connector_id;
    }
    else {
      $connector_id = $this->connector->spotConnector($ticket_id, $input, $output);
    }
    if (!$connector_id) {
      throw new \InvalidArgumentException('No such ticket was found in any backends.');
    }
    if ($alias = $this->connector->loadAlias($ticket_id, $connector_id)) {
      $ticket_id = $alias;
    }
    if ($title = $this->connector->ticketDetails($ticket_id, $connector_id)) {
      if ($stop = $this->repository->stop()) {
        $stopped = $this->connector->ticketDetails($stop->getTicketId(), $stop->getConnectorId());
        $output->writeln(sprintf('Closed slot <comment>%d</comment> against ticket <info>%d</info>: %s, duration <info>%s</info>',
          $stop->getId(),
          $stop->getTicketId(),
          $stopped->getTitle(),
          Formatter::formatDuration($stop->getDuration())
        ));
      }
      try {
        $started = $this->repository->start($ticket_id, $connector_id, $input->getArgument('comment'));
        $slot_id = $started->getId();
        $continued = $started->isContinued();
        $output->writeln(sprintf('<bg=blue;fg=white;options=bold>[%s]</> <comment>%s</comment> entry for <info>%d</info>: %s [slot:<comment>%d</comment>]',
          (new \DateTime())->format('h:i'),
          $continued ? 'Continued' : 'Started new',
          $ticket_id,
          $title->getTitle(),
          $slot_id
        ));
        if ($input->getOption('status')) {
          if ($this->connector->setInProgress($ticket_id, $connector_id, $assign = $input->getOption('assign'), $input->getOption('redmine-comment') ?: 'Working on this')) {
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
          if ($this->connector->assign($ticket_id, $connector_id, $input->getOption('redmine-comment') ?: 'Working on this')) {
            $output->writeln(sprintf('Ticket <comment>%s</comment> assigned to you.',
              $ticket_id));
          }
          else {
            $output->writeln('<error>Could not assign ticket</error>');
          }
        }
        elseif ($input->getOption('redmine-comment')) {
          $output->writeln('<error>You cannot provide a comment if you do not provide the --assign or --status flags</error>');
          exit(1);
        }
      }
      catch (\Exception $e) {
        $output->writeln(sprintf('<error>Error creating slot: %s</error>', $e->getMessage()));
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
