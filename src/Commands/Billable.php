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
  const FORTNIGHT = 'fortnight';

  /**
   * @var \Larowlan\Tl\Connector\Connector
   */
  protected $connector;

  /**
   * @var \Larowlan\Tl\Repository\Repository
   */
  protected $repository;

  /**
   * The percentage of billable hours required.
   *
   * @var float
   */
  protected $billablePercentage;

  /**
   * The number of hours you work per day.
   *
   * @var int
   */
  protected $hoursPerDay;

  public function __construct(Connector $connector, Repository $repository, array $config) {
    $this->connector = $connector;
    $this->repository = $repository;
    $this->billablePercentage = $config['billable_percentage'];
    $this->hoursPerDay = $config['hours_per_day'];
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('billable')
      ->setDescription('Shows billable breakdown for a given date')
      ->setHelp('Shows billable percentage for a given date range. <comment>Usage:</comment> <info>tl billable [day|week|month|fortnight]</info>')
      ->addArgument('period', InputArgument::OPTIONAL, 'One of day|week|month|fortnight', static::WEEK)
      ->addOption('start', 's', InputOption::VALUE_OPTIONAL, 'A date offset', NULL)
      ->addOption('project', 'p', InputOption::VALUE_NONE, 'Group by project', NULL)
      ->addUsage('tl billable day')
      ->addUsage('tl billable day -s Jul-31')
      ->addUsage('tl billable week')
      ->addUsage('tl billable fortnight')
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
      static::WEEK,
      static::FORTNIGHT,
    ], TRUE)) {
      $output->writeln('<error>Period must be one of day|week|month|fortnight</error>');
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

      case static::FORTNIGHT:
        $date = DateHelper::startOfWeek($start);
        $date->modify('-7 days');
        $end = clone $date;
        $end->modify('+14 days');
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
    if ($billable / $total < $this->billablePercentage) {
      $tag = 'error';
    }
    if ($project) {
      $project_names = $this->connector->projectNames();
      $rows[] = ['Billable', '', '', ''];
      foreach ($billable_projects as $project_id) {
        $project_name = isset($project_names[$project_id]) ? $project_names[$project_id] : "Project ID $project_id";
        $rows[] = ['', $project_name, Formatter::formatDuration($projects[$project_id]), ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = [
        'Billable',
        '',
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
        '',
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

    if ($period === static::MONTH) {
      $rows[] = new TableSeparator();
      $rows[] = ['', 'STATS', ''];
      $rows[] = new TableSeparator();
      $no_weekdays_in_month = $this->getWeekdaysInMonth(date('m'), date('Y'));
      $days_passed = $this->getWeekdaysPassedThisMonth();

      $hrs_per_day = $this->hoursPerDay;
      $total_hrs = $no_weekdays_in_month * $hrs_per_day;
      $total_billable_hrs = $total_hrs * $this->billablePercentage;
      $billable_hrs = $billable / 60 / 60;
      $non_billable_hrs = $non_billable / 60 / 60;
      $completed_hrs = $billable_hrs + $non_billable_hrs;

      $rows[] = ['No. Days', "$days_passed/$no_weekdays_in_month", round(100 * $days_passed / $no_weekdays_in_month, 2) . '%'];
      $rows[] = ['Billable Hrs', "$billable_hrs/$total_billable_hrs", round(100 * $billable_hrs / $total_billable_hrs, 2) . '%'];
      $rows[] = ['Total Hrs', "$completed_hrs/$total_hrs", round(100 * $completed_hrs / $total_hrs, 2) . '%'];
    }

    $table->setRows($rows);
    $table->render();
  }

  protected function getWeekdaysInMonth($m, $y) {
    $lastday = date("t", mktime(0, 0, 0, $m, 1, $y));
    $weekdays = 0;
    for ($d = 29; $d <= $lastday; $d++) {
      $wd = date("w", mktime(0, 0, 0, $m, $d, $y));
      if ($wd > 0 && $wd < 6) {
        $weekdays++;
      }
    }
    return $weekdays + 20;
  }

  protected function getWeekdaysPassedThisMonth() {
    $days_passed = date('d');
    $weekends_passed = $days_passed / 7;
    $days_passed -= ($weekends_passed * 2);

    // Don't include the current day before 3pm?
    if (date('G') < 15) {
      $days_passed -= 1;
    }
    return $days_passed;
  }

}
