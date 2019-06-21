<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Connector\Manager;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 *
 */
class TagAll extends Command {

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
      ->setName('tag-all')
      ->setDescription('Tag time entries')
      ->setHelp('Tag all time entries. <comment>Usage:</comment> <info>tl tag_all</info>')
      ->addUsage('tl tag-all');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $entries = $this->repository->review(Review::ALL, TRUE);
    $connector_ids = array_unique(array_map(function ($entry) {
      return $entry->connector_id;
    }, $entries));
    $helper = $this->getHelper('question');
    $categories = $this->connector->fetchCategories();
    $tags = [];
    foreach ($connector_ids as $connector_id) {
      $question = new ChoiceQuestion(
        sprintf('Select tag to use for %s tickets', Manager::formatConnectorId($connector_id)),
        $categories[$connector_id]
      );
      $tag_id = $helper->ask($input, $output, $question);
      $tag = $categories[$connector_id][$tag_id];
      list(, $tag) = explode(':', $tag);
      $tags[$connector_id] = $tag;
    }
    $tagged = FALSE;
    /** @var \Larowlan\Tl\Slot $entry */
    foreach ($entries as $entry) {
      if ($entry->getCategory()) {
        continue;
      }
      $this->repository->tag($tags[$entry->getConnectorId()], $entry->getId());
      $tagged = TRUE;
    }
    if (!$tagged) {
      $output->writeln('<error>All items already tagged, use --retag to retag</error>');
    }
  }

}
