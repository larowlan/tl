<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\DurationFormatter;
use Larowlan\Tl\Reviewer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Total extends Command {

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
      ->setName('total')
      ->setDescription('Displays total time logged to be sent to back-end')
      ->setHelp('Shows total of unsent entries. <comment>Usage:</comment> <info>tl total</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $review = $this->reviewer->getSummary(static::ALL);
    $output->writeln(sprintf('<comment>Total:</comment> %s', DurationFormatter::formatDuration($review->getExactTotal())));
    return 0;
  }

}
