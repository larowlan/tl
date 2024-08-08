<?php

namespace Larowlan\Tl\Commands;

use GuzzleHttp\Exception\ConnectException;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\DurationFormatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 *
 */
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
      return $this->tagOne($input, $output, $slot_id);
    }
    else {
      return $this->tagAll($input, $output);
    }
  }

  /**
   * Tag all entries.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function tagAll(InputInterface $input, OutputInterface $output): int {
    $helper = $this->getHelper('question');
    $last = FALSE;
    try {
      $entries = $this->repository->review(Review::ALL, TRUE);
      $grouped_categories = $this->connector->fetchCategories();
    }
    catch (ConnectException $e) {
      $output->writeln('<error>You are offline, please try again later.</error>');
      return 1;
    }
    /** @var \Larowlan\Tl\Slot $entry */
    foreach ($entries as $entry) {
      $categories = $grouped_categories[$entry->getConnectorId()];
      if ($entry->getCategory() && !$input->getOption('retag')) {
        continue;
      }
      if (count($categories) === 1) {
        $tag = reset($categories);
        [, $tag] = explode(':', $tag);
        $this->repository->tag($tag, $entry->getId());
        continue;
      }
      $title = $this->connector->ticketDetails($entry->getTicketId(), $entry->getConnectorId());
      $default = reset($categories);
      $question = new ChoiceQuestion(
        sprintf('Enter tag for slot <comment>%d</comment> [<info>%d</info>]: %s [<info>%s h</info>] [%s] %s',
          $entry->getId(),
          $entry->getTicketId(),
          $title->getTitle(),
          $entry->getDuration(FALSE, TRUE) / 3600,
          $last ?: $default,
          $entry->getComment() ? '- "' . $entry->getComment() . '"' : ''
        ),
        $categories,
        $last ?: $default
      );
      $tag_id = $helper->ask($input, $output, $question);
      $tag = $categories[$tag_id];
      [, $tag] = explode(':', $tag);
      $this->repository->tag($tag, $entry->getId());
      $last = $tag_id;
    }
    if (!$last) {
      $output->writeln('<error>All items already tagged, use --retag to retag</error>');
      return 1;
    }
    return 0;
  }

  /**
   * Tag single entry.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $slot_id
   *
   * @return int
   */
  protected function tagOne(InputInterface $input, OutputInterface $output, $slot_id): int {
    if ($entry = $this->repository->slot($slot_id)) {
      $helper = $this->getHelper('question');
      try {
        $title = $this->connector->ticketDetails($entry->getTicketId(), $entry->getConnectorId());
        $grouped_categories = $this->connector->fetchCategories();
        $categories = $grouped_categories[$entry->getConnectorId()];
      }
      catch (ConnectException $e) {
        $output->writeln('<error>You are offline, please try again later.</error>');
        return 1;
      }
      if (count($categories) === 1) {
        $tag = reset($categories);
        [, $tag] = explode(':', $tag);
        $this->repository->tag($tag, $entry->getId());
        return 1;
      }
      $default = reset($categories);
      $question = new ChoiceQuestion(
        sprintf('Enter tag for slot <comment>%d</comment> [<info>%d</info>]: %s [<info>%s h</info>] [%s] %s',
          $entry->getId(),
          $entry->getTicketId(),
          $title->getTitle(),
          DurationFormatter::formatDuration($entry->getDuration()),
          $default,
          $entry->getComment() ? '- "' . $entry->getComment() . '"' : ''
        ),
        $categories,
        $default
      );
      $tag_id = $helper->ask($input, $output, $question);
      $tag = $categories[$tag_id];
      [, $tag] = explode(':', $tag);
      $this->repository->tag($tag, $entry->getId());
    }
    else {
      $output->writeln('<error>No such slot - please check your slot ID</error>');
      return 1;
    }
    return 0;
  }

}
