<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Billable.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\DateHelper;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Billable extends Command {

  const WEEK = 'week';
  const DAY = 'day';
  const MONTH = 'month';

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

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
      ->setName('billable')
      ->setDescription('Shows billable breakdown for a given date')
      ->setHelp('Shows billable percentage for a given date range. <comment>Usage:</comment> <info>tl billable [day|week|month]</info>')
      ->addArgument('period', InputArgument::OPTIONAL, 'One of day|week|month', static::WEEK)
      ->addOption('start', 's', InputOption::VALUE_OPTIONAL, 'A date offset', NULL)
      ->addOption('project', 'p', InputOption::VALUE_NONE, 'Group by project', NULL)
      ->addUsage('tl billable day')
      ->addUsage('tl billable day -s Jul-31')
      ->addUsage('tl billable week')
      ->addUsage('tl billable week --start=Jul-31')
      ->addUsage('tl billable month')
      ->addUsage('tl billable month -s Aug');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $period = $input->getArgument('period');
    $start = $input->getOption('start');
    $project = $input->getOption('project');
    $start = $start ? new \DateTime($start) : NULL;
    if (!in_array($period, [
      static::MONTH,
      static::DAY,
      static::WEEK
    ], TRUE)) {
      $output->writeln('<error>Period must be one of day|week|month</error>');
      $output->writeln('E.g. <comment>tl billable week</comment>');
      return;
    }
    switch ($period) {
      case static::WEEK:
        $date = DateHelper::startOfWeek($start);
        $end = clone $date;
        $end->modify('+7 days');
        break;

      case static::MONTH:
        $date = DateHelper::startOfMonth($start);
        $end = clone $date;
        $end->modify('+1 month');
        $end = DateHelper::startOfMonth($end);
        $end->modify('-1 second');
        break;

      default:
        $date = DateHelper::startOfDay($start);
        $end = clone $date;
        $end->modify('+1 day');
    }
    $billable = 0;
    $non_billable = 0;
    $unknown = 0;
    $unknowns = [];
    $projects = [];
    $billable_projects = [];
    $non_billable_projects = [];
    foreach ($this->repository->totalByTicket($date->getTimestamp(), $end->getTimestamp()) as $tid => $duration) {
      $details = $this->connector->ticketDetails($tid);
      if ($details) {
        if (!isset($projects[$details->getProjectId()])) {
          $projects[$details->getProjectId()] = 0;
        }
        if ($details->isBillable()) {
          $billable += $duration;
          $projects[$details->getProjectId()] += $duration;
          $billable_projects[$details->getProjectId()] = $details->getProjectId();
        }
        else {
          $non_billable += $duration;
          $projects[$details->getProjectId()] += $duration;
          $non_billable_projects[$details->getProjectId()] = $details->getProjectId();
        }
      }
      else {
        $unknown += $duration;
        $unknowns[] = $tid;
      }
    }
    $table = new Table($output);
    if (!$project) {
      $table->setHeaders(['Type', 'Hours', 'Percent']);
    }
    else {
      $table->setHeaders(['Type', 'Project', 'Hours', 'Percent']);
    }
    $total = $billable + $non_billable + $unknown;
    $tag = 'info';
    // @todo make this configurable.
    if ($billable / $total < 0.8) {
      $tag = 'error';
    }
    if ($project) {
      $project_names = $this->connector->projectNames();
      $rows[] = new TableSeparator();
      $rows[] = ['Billable', '', '', ''];
      foreach ($billable_projects as $project_id) {
        $project_name = isset($project_names[$project_id]) ? $project_names[$project_id] : "Project ID $project_id";
        $rows[] = ['', $project_name, Formatter::formatDuration($projects[$project_id]), ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = [
        'Billable',
        Formatter::formatDuration($billable),
        "<$tag>" . round(100 * $billable / $total, 2) . "%</$tag>"
      ];
      $rows[] = new TableSeparator();
      $rows[] = ['Non-Billable', '', '', ''];
      foreach ($non_billable_projects as $project_id) {
        $project_name = isset($project_names[$project_id]) ? $project_names[$project_id] : "Project ID $project_id";
        $rows[] = ['', $project_name, Formatter::formatDuration($projects[$project_id]), ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = [
        'Non-billable',
        Formatter::formatDuration($non_billable),
        round(100 * $non_billable / $total, 2) . '%'
      ];
      if ($unknown) {
        $rows[] = new TableSeparator();
        $rows[] = ['Unknown', '', '', ''];
        $rows[] = ['', 'Unknown<comment>*</comment>', Formatter::formatDuration($unknown), round(100 * $unknown / $total, 2) . '%'];
        $rows[] = ['', '<comment>* Deleted or access denied tickets:</comment> ' . implode(',', $unknowns), '', ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = ['', 'Total', Formatter::formatDuration($total), '100%'];
    }
    else {
      $rows[] = [
        'Billable',
        Formatter::formatDuration($billable),
        "<$tag>" . round(100 * $billable / $total, 2) . "%</$tag>"
      ];
      $rows[] = [
        'Non-billable',
        Formatter::formatDuration($non_billable),
        round(100 * $non_billable / $total, 2) . '%'
      ];
      if ($unknown) {
        $rows[] = ['Unknown<comment>*</comment>', Formatter::formatDuration($unknown), round(100 * $unknown / $total, 2) . '%'];
        $rows[] = ['<comment>* Deleted or access denied tickets:</comment> ' . implode(',', $unknowns), '', ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = ['Total', Formatter::formatDuration($total), '100%'];
    }

    $table->setRows($rows);
    $table->render();
  }

}
