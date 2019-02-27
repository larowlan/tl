<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Reviewer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Review extends Command {

  /**
   * @var \Larowlan\Tl\Reviewer
   */
  protected $reviewer;

  const ALL = '19780101';

  /**
   *
   */
  public function __construct(Reviewer $reviewer) {
    $this->reviewer = $reviewer;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('review')
      ->addOption('exact', 'e', InputOption::VALUE_NONE, 'Show exact times too (without rounding)')
      ->setDescription('Reviews time entries to be sent to the backend')
      ->setHelp('Review unsent entries. <comment>Usage:</comment> <info>tl review</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Find any untagged items needing review, use an arbitrarily early date.
    $review = $this->reviewer->getSummary(static::ALL, FALSE, $input->getOption('exact'));
    $table = new Table($output);
    $table->setHeaders(Reviewer::headers($input->getOption('exact')));
    $table->setRows($review);
    $table->render();
  }

}
