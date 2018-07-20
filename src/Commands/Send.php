<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Send.php
 */

namespace Larowlan\Tl\Commands;

use GuzzleHttp\Exception\ClientException;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Repository\Repository;
use Larowlan\Tl\Reviewer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Send extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   * @var \Larowlan\Tl\Reviewer
   */
  protected $reviewer;

  const ALL = '19780101';

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(Connector $connector, Repository $repository, Reviewer $reviewer, LoggerInterface $logger) {
    $this->connector = $connector;
    $this->repository = $repository;
    $this->reviewer = $reviewer;
    parent::__construct();
    $this->logger = $logger;
  }

  /**
   * Outputs and logs progress.
   *
   * @param \Symfony\Component\Console\Helper\ProgressBar $progress
   *   Progress.
   * @param string $message
   *   Message.
   *
   * @return $this
   */
  protected function progress(ProgressBar $progress, $message) {
    $this->logger->info(strip_tags($message));
    $progress->setMessage($message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('send')
      ->setDescription('Sends time entries to back-end')
      ->setHelp('Sends entries. <comment>Usage:</comment> <info>tl send</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Check for any open time logs first.
    if ($open = $this->repository->getActive()) {
      $details = $this->connector->ticketDetails($open->tid);
      $output->writeLn(sprintf('<error>Please stop open time logs first. %s [<info>%d</info>]</error>',
        $details->getTitle(),
        $open->tid
      ));
      return;
    }

    // Find any untagged items needing review, use an arbitrarily early date.
    $review = $this->reviewer->getSummary(static::ALL, TRUE);
    if (count($review) > 2) {
      // Incomplete records exist.
      $output->writeln('<error>Please tag/comment on the following entries:</error>');
      $table = new Table($output);
      $table->setHeaders(Reviewer::headers());
      $table->setRows($review);
      $table->render();
      return;
    }
    $entry_ids = $return = [];
    $entries = $this->repository->send();
    $progress = new ProgressBar($output, count($entries));
    ProgressBar::setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% - <info>%message%</info>');
    $progress->setFormat('custom');
    $progress->setProgressCharacter("\xF0\x9F\x8D\xBA");
    $errors = FALSE;
    foreach ($entries as $entry) {
      try {
        if ((float) $entry->duration == 0) {
          // Nothing to send, but mark sent so it doesn't show up tomorrow.
          $this->repository->store([$entry->tid => 0]);
          $this->progress($progress, sprintf('Marked entry for <info>%d</info> as sent, < 15 minutes', $entry->tid));
          $progress->advance();
          continue;
        }
        if ($saved = $this->connector->sendEntry($entry)) {
          $entry_ids[$entry->tid] = $saved;
          // A real entry, give some output.
          $this->progress($progress, sprintf('Stored entry for <info>%d</info>, remote id <comment>%d</comment>', $entry->tid, $entry_ids[$entry->tid]));
          $progress->advance();
        }
      }
      catch (ClientException $e) {
        $this->progress($progress, '<error>' . $e->getMessage() . '</error>');
        $errors = TRUE;
      }
    }
    $progress->setMessage('Done');
    $progress->finish();
    $output->writeln('');
    $output->writeln("Stored remote entries \xF0\x9F\x8E\x89");
    if ($errors) {
      $output->writeln('<error>Errors occurred during sending, run "tl log" for more information.');
    }
    $this->repository->store($entry_ids);
  }

}
