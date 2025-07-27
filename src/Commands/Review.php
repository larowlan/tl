<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Reviewer;
use Larowlan\Tl\SummaryJsonFormatter;
use Larowlan\Tl\SummaryTableFormatter;
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
    $this->setName('review')
      ->addOption(
        'exact',
        'e',
        InputOption::VALUE_NONE,
        'Show exact times too (without rounding)'
      )
      ->addOption(
        'format',
        'f',
        InputOption::VALUE_REQUIRED,
        'Output format [text,json]',
        'text',
      )
      ->setDescription('Reviews time entries to be sent to the backend')
      ->setHelp('Review unsent entries. <comment>Usage:</comment> <info>tl review</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Find any untagged items needing summary, use an arbitrarily early date.
    $exact = $input->getOption('exact') ?? FALSE;
    $summary = $this->reviewer->getSummary(static::ALL);
    if ($input->getOption('format') == 'json') {
      $output->writeln(SummaryJsonFormatter::formatJson($summary), OutputInterface::OUTPUT_RAW);
      return self::SUCCESS;
    }
    $rows = SummaryTableFormatter::formatTableRows($summary, $exact);
    $table = new Table($output);
    $table->setHeaders(SummaryTableFormatter::getHeaders($exact));
    $table->setRows($rows);
    $table->render();
    return self::SUCCESS;
  }

}
