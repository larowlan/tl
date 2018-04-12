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

  public function __construct(Connector $connector, Repository $repository, Reviewer $reviewer) {
    $this->connector = $connector;
    $this->repository = $repository;
    $this->reviewer = $reviewer;
    parent::__construct();
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
    $progress = new ProgressBar($output, $entries);
    $progress->setProgressCharacter("\xF0\x9F\x8D\xBA");
    $lines = [];
    foreach ($entries as $entry) {
      try {
        if ((float) $entry->duration == 0) {
          // Nothing to send, but mark sent so it doesn't show up tomorrow.
          $this->repository->store([$entry->tid => 0]);
          $lines[] = sprintf('Marked entry for <info>%d</info> as sent, < 15 minutes', $entry->tid);
          $progress->advance();
          continue;
        }
        if ($saved = $this->connector->sendEntry($entry)) {
          $entry_ids[$entry->tid] = $saved;
          // A real entry, give some output.
          $lines[] = sprintf('Stored entry for <info>%d</info>, remote id <comment>%d</comment>', $entry->tid, $entry_ids[$entry->tid]);
          $progress->advance();
        }
      }
      catch (ClientException $e) {
        $lines[] = '<error>' . $e->getMessage() . '</error>';
      }
    }
    $progress->finish();
    $output->writeln('');
    foreach ($lines as $line) {
      $output->writeln($line);
    }
    $output->writeln("Done \xF0\x9F\x8D\xBA \xF0\x9F\x8D\xBA \xF0\x9F\x8D\xBA");
    $this->repository->store($entry_ids);
  }

}
