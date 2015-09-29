<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Tag.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
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

  const DEFAULT_TAG = 9;

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
      ->setHelp('Tag time entries. <comment>Usage:</comment> <info>tl tag</info>')
      ->addUsage('tl tag')
      ->addUsage('tl tag --retag')
      ->addOption('retag', NULL, InputOption::VALUE_NONE);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $entries = $this->repository->review(Review::ALL, TRUE);
    $helper = $this->getHelper('question');
    $last = FALSE;
    $categories = $this->connector->fetchCategories();
    foreach ($entries as $entry) {
      if ($entry->category && !$input->getOption('retag')) {
        continue;
      }
      $title = $this->connector->ticketDetails($entry->tid);
      $question = new ChoiceQuestion(
        sprintf('Enter tag for slot <comment>%d</comment> [<info>%d</info>]: %s [<info>%s h</info>] [%d]',
          $entry->id,
          $entry->tid,
          $title['title'],
          $entry->duration,
          $last ?: static::DEFAULT_TAG
        ),
        $categories,
        $last ?: static::DEFAULT_TAG
      );
      $tag = $helper->ask($input, $output, $question);
      list(, $tag) = explode(':', $tag);
      $this->repository->tag($tag, $entry->id);
      $last = $tag;
    }
    if (!$last) {
      $output->writeln('<error>All items already tagged, use --retag to retag</error>');
    }
  }

}
