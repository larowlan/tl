<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Tag.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use GuzzleHttp\Exception\ConnectException;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Tag extends Command {

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  const DEFAULT_TAG = 'Development:9';

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
      ->setName('tag')
      ->setDescription('Tag time entries')
      ->setHelp('Tag time entries. <comment>Usage:</comment> <info>tl tag [optional slot id]</info>')
      ->addUsage('tl tag')
      ->addUsage('tl tag --retag')
      ->addUsage('tl tag 6')
      ->addArgument('slot_id', InputArgument::OPTIONAL, 'Slot ID', FALSE)
      ->addOption('retag', NULL, InputOption::VALUE_NONE);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($slot_id = $input->getArgument('slot_id')) {
      $this->tagOne($input, $output, $slot_id);
    }
    else {
      $this->tagAll($input, $output);
    }

  }

  /**
   * Tag all entries.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function tagAll(InputInterface $input, OutputInterface $output) {
    $helper = $this->getHelper('question');
    $last = FALSE;
    try {
      $entries = $this->repository->review(Review::ALL, TRUE);
      $categories = $this->connector->fetchCategories();
    }
    catch (ConnectException $e) {
      $output->writeln('<error>You are offline, please try again later.</error>');
      return;
    }
    foreach ($entries as $entry) {
      if ($entry->category && !$input->getOption('retag')) {
        continue;
      }
      $title = $this->connector->ticketDetails($entry->tid);
      $question = new ChoiceQuestion(
        sprintf('Enter tag for slot <comment>%d</comment> [<info>%d</info>]: %s [<info>%s h</info>] [%s] %s',
          $entry->id,
          $entry->tid,
          $title->getTitle(),
          $entry->duration,
          $last ?: static::DEFAULT_TAG,
          $entry->comment ? '- "' . $entry->comment . '"': ''
        ),
        $categories,
        $last ?: static::DEFAULT_TAG
      );
      $tag_id = $helper->ask($input, $output, $question);
      $tag = $categories[$tag_id];
      list(, $tag) = explode(':', $tag);
      $this->repository->tag($tag, $entry->id);
      $last = $tag_id;
    }
    if (!$last) {
      $output->writeln('<error>All items already tagged, use --retag to retag</error>');
    }
  }

  /**
   * Tag single entry.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $slot_id
   */
  protected function tagOne(InputInterface $input, OutputInterface $output, $slot_id) {
    if ($entry = $this->repository->slot($slot_id)) {
      $helper = $this->getHelper('question');
      try {
        $title = $this->connector->ticketDetails($entry->tid);
        $categories = $this->connector->fetchCategories();
      } catch (ConnectException $e) {
        $output->writeln('<error>You are offline, please try again later.</error>');
        return;
      }
      $question = new ChoiceQuestion(
        sprintf('Enter tag for slot <comment>%d</comment> [<info>%d</info>]: %s [<info>%s h</info>] [%s] %s',
          $entry->id,
          $entry->tid,
          $title->getTitle(),
          Formatter::formatDuration($entry->end - $entry->start),
          static::DEFAULT_TAG,
          $entry->comment ? '- "' . $entry->comment . '"': ''
        ),
        $categories,
        static::DEFAULT_TAG
      );
      $tag_id = $helper->ask($input, $output, $question);
      $tag = $categories[$tag_id];
      list(, $tag) = explode(':', $tag);
      $this->repository->tag($tag, $entry->id);
    }
    else {
      $output->writeln('<error>No such slot - please check your slot ID</error>');
    }
  }

}
