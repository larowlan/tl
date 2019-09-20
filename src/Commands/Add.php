<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Connector\ConnectorManager;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Input;
use Larowlan\Tl\Repository\Repository;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The add command for tl.
 */
class Add extends Command implements LogAwareCommand {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   * Add command constructor.
   * @param \Larowlan\Tl\Connector\ConnectorManager $connector
   *   The connector.
   * @param \Larowlan\Tl\Repository\Repository $repository
   *   The repository.
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
      ->setName('add')
      ->setDescription('Add a time entry')
      ->setHelp('Add a new entry, for a given task. <comment>Usage:</comment> <info>tl add [ticket number] [duration in hours]</info>')
      ->addArgument('issue_number', InputArgument::REQUIRED, 'Issue number to log time to')
      ->addArgument('duration', InputArgument::REQUIRED, 'Duration in hours to change slot to')
      ->addArgument('comment', InputArgument::OPTIONAL, 'Comment to describe the activity')
      ->addOption('start', 's', InputOption::VALUE_OPTIONAL, 'An absolute or relative date and time', 'now')
      ->addUsage('tl add 12355 0.5')
      ->addUsage('tl add 12355 1 "Doin stuff"')
      ->addUsage('tl add 12355 .25 "Daily Scrum" -s 11am')
      ->addUsage('tl add 12345 1 -s 2018-12-31 11 am');

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $ticket_id = $input->getArgument('issue_number');
    $duration = $input->getArgument('duration');

    try {
      $seconds = Input::parseInterval($duration);
    }
    catch (\Throwable $e) {
      $output->writeln('<error>' . $e->getMessage() . '</error>');
      return 1;
    }

    $start = $input->getOption('start');
    if ($alias = $this->repository->loadAlias($ticket_id)) {
      $ticket_id = $alias;
    }
    $connector_id = $this->connector->spotConnector($ticket_id, $input, $output);
    if (!$connector_id) {
      throw new \InvalidArgumentException('No such ticket was found in any backends.');
    }
    if ($alias = $this->connector->loadAlias($ticket_id, $connector_id)) {
      $ticket_id = $alias;
    }
    if ($title = $this->connector->ticketDetails($ticket_id, $connector_id)) {
      try {
        try {
          $start_date_time = new \DateTime($start);
        }
        catch (\Exception $ex) {
          $output->writeln(sprintf('<error>Time duration is not correct: %s</error>', $ex->getMessage()));
        }

        $record = [
          'tid' => $ticket_id,
          'start' => $start_date_time->getTimestamp(),
          'end' => (int) $start_date_time->getTimestamp() + $seconds,
          'connector_id' => ':connector_id',
        ];
        $params = [':connector_id' => $connector_id];
        if ($comment = $input->getArgument('comment')) {
          $record['comment'] = ':comment';
          $params[':comment'] = $comment;
        }
        $slot_id = $this->repository->insert($record, $params);
        $output->writeln(sprintf('<bg=blue;fg=white;options=bold>[%s]</> Added entry for <info>%d</info>: %s on [%s]  for <info>%s</info> [slot:<comment>%d</comment>].',
          $start_date_time->format('Y-m-d h:i'),
          $ticket_id,
          $title->getTitle(),
          $start_date_time->format('Y-m-d'),
          Formatter::formatDuration($seconds),
          $slot_id
        ));
      }
      catch (\Exception $e) {
        $output->writeln(sprintf('<error>Error creating slot: %s</error>', $e->getMessage()));
      }
    }
    else {
      $output->writeln('<error>Error: no such ticket id or access denied</error>');
    }
  }

}
