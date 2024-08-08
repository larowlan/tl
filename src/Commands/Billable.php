<?php

namespace Larowlan\Tl\Commands;

use Larowlan\Tl\Configuration\ConfigurableService;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\DateHelper;
use Larowlan\Tl\DurationFormatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class Billable extends Command implements ConfigurableService {

  const WEEK = 'week';
  const DAY = 'day';
  const MONTH = 'month';
  const FORTNIGHT = 'fortnight';

  const YEAR = 'year';

  const FINYEAR = 'financial';
  const SATURDAY = 6;
  const SUNDAY = 0;

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

  /**
   * Array of targets, keyed by Y-m.
   *
   * @var int[]
   */
  protected $targets;

  /**
   * Config directory.
   *
   * @var string
   */
  protected $directory;

  /**
   *
   */
  public function __construct(Connector $connector, Repository $repository, array $config, $directory) {
    $this->connector = $connector;
    $this->repository = $repository;
    $config = static::getDefaults($config, new ContainerBuilder());
    $this->billablePercentage = $config['billable_percentage'];
    $this->hoursPerDay = $config['hours_per_day'];
    $this->targets = $config['days_per_month'];
    $this->directory = $directory;
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
      ->addArgument('period', InputArgument::OPTIONAL, 'One of day|week|month|fortnight|year|financial', static::WEEK)
      ->addOption('start', 's', InputOption::VALUE_OPTIONAL, 'A date offset', NULL)
      ->addOption('project', 'p', InputOption::VALUE_NONE, 'Group by project', NULL)
      ->addOption('set-target-days', 't', InputOption::VALUE_OPTIONAL, 'Set target days for month', NULL)
      ->addUsage('tl billable day')
      ->addUsage('tl billable day -s Jul-31')
      ->addUsage('tl billable week')
      ->addUsage('tl billable fortnight')
      ->addUsage('tl billable week --start=Jul-31')
      ->addUsage('tl billable month')
      ->addUsage('tl billable month --set-target-days=12 # Sets target to 12 days in month.')
      ->addUsage('tl billable month -t 12 # Sets target to 12 days in month.')
      ->addUsage('tl billable month -t 1,2,3,4,5,8,9,10 # Set target (working) days of month.')
      ->addUsage('tl billable month -t 1,2:6,8:4,9,10 # Set target (working) days of month including custom hours per day using a ":".')
      ->addUsage('tl billable month -s Aug');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $period = $input->getArgument('period');
    $start = $input->getOption('start');
    $project = $input->getOption('project');
    $target = $input->getOption('set-target-days');
    $start = $start ? new \DateTime($start) : NULL;
    if ($target) {
      $target = $this->writeTarget($target, $output, $start);
    }
    if (!in_array($period, [
      static::MONTH,
      static::DAY,
      static::WEEK,
      static::FORTNIGHT,
      static::FINYEAR,
      static::YEAR,
    ], TRUE)) {
      $output->writeln('<error>Period must be one of day|week|month|fortnight|year|financial</error>');
      $output->writeln('E.g. <comment>tl billable week</comment>');
      return 1;
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

      case static::YEAR:
        $date = DateHelper::startOfYear($start);
        $end = clone $date;
        $end->modify('+1 year')->modify('-1 second');
        break;

      case static::FINYEAR:
        $date = DateHelper::startOfFinancialYear($start);
        $end = clone $date;
        $end->modify('+1 year')->modify('-1 second');
        break;

      default:
        $date = DateHelper::startOfDay($start);
        $end = clone $date;
        $end->modify('+1 day');
    }
    $output->writeln(sprintf('Showing data from <info>%s</info> to <info>%s</info>', $date->format('Y-m-d'), $end->format('Y-m-d')));
    $billable = 0;
    $non_billable = 0;
    $unknown = 0;
    $unknowns = [];
    $projects = [];
    $billable_projects = [];
    $non_billable_projects = [];
    foreach ($this->repository->totalByTicket($date->getTimestamp(), $end->getTimestamp()) as $connector_id => $items) {
      foreach ($items as $tid => $duration) {
        $details = $this->connector->ticketDetails($tid, $connector_id);
        if ($details) {
          if (!isset($projects[$connector_id][$details->getProjectId()])) {
            $projects[$connector_id][$details->getProjectId()] = 0;
          }
          if ($details->isBillable()) {
            $billable += $duration;
            $projects[$connector_id][$details->getProjectId()] += $duration;
            $billable_projects[$connector_id][$details->getProjectId()] = $details->getProjectId();
          }
          else {
            $non_billable += $duration;
            $projects[$connector_id][$details->getProjectId()] += $duration;
            $non_billable_projects[$connector_id][$details->getProjectId()] = $details->getProjectId();
          }
        }
        else {
          $unknown += $duration;
          $unknowns[] = $tid;
        }
      }
    }
    $table = new Table($output);
    if (!$project) {
      $headers = ['Type', 'Hours', 'Percent'];
      if ($period === static::MONTH) {
        $headers[] = 'Tracking';
      }
      $table->setHeaders($headers);
    }
    else {
      $table->setHeaders(['Type', 'Project', 'Hours', 'Percent']);
    }
    $total = $billable + $non_billable + $unknown;
    $tag = 'info';
    if (($total == 0) || ($billable / $total < $this->billablePercentage)) {
      $tag = 'error';
    }
    if ($project) {
      $project_names = $this->connector->projectNames();
      $rows[] = ['Billable', '', '', ''];
      foreach ($billable_projects as $connector_id => $connector_projects) {
        foreach ($connector_projects as $project_id) {
          $project_name = isset($project_names[$connector_id][$project_id]) ? $project_names[$connector_id][$project_id] : "Project ID $project_id";
          $rows[] = [
            '',
            $project_name,
            DurationFormatter::formatDuration($projects[$connector_id][$project_id]),
            '',
          ];
        }
      }
      $rows[] = new TableSeparator();
      $rows[] = [
        'Billable',
        '',
        DurationFormatter::formatDuration($billable),
        "<$tag>" . ($total ? round(100 * $billable / $total, 2) : 0) . "%</$tag>",
      ];
      $rows[] = new TableSeparator();
      $rows[] = ['Non-Billable', '', '', ''];
      foreach ($non_billable_projects as $connector_id => $connector_projects) {
        foreach ($connector_projects as $project_id) {
          $project_name = isset($project_names[$connector_id][$project_id]) ? $project_names[$connector_id][$project_id] : "Project ID $project_id";
          $rows[] = [
            '',
            $project_name,
            DurationFormatter::formatDuration($projects[$connector_id][$project_id]),
            '',
          ];
        }
      }
      $rows[] = new TableSeparator();
      $rows[] = [
        'Non-billable',
        '',
        DurationFormatter::formatDuration($non_billable),
        round(100 * $non_billable / $total, 2) . '%',
      ];
      if ($unknown) {
        $rows[] = new TableSeparator();
        $rows[] = ['Unknown', '', '', ''];
        $rows[] = ['', 'Unknown<comment>*</comment>', DurationFormatter::formatDuration($unknown), round(100 * $unknown / $total, 2) . '%'];
        $rows[] = ['', '<comment>* Deleted or access denied tickets:</comment> ' . implode(',', $unknowns), '', ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = ['', 'Total', DurationFormatter::formatDuration($total), ''];
    }
    else {
      $rows[] = [
        'Billable',
        DurationFormatter::formatDuration($billable),
        "<$tag>" . ($total ? round(100 * $billable / $total, 2) : 0) . "%</$tag>",
      ];
      $rows[] = [
        'Non-billable',
        DurationFormatter::formatDuration($non_billable),
        ($total ? round(100 * $non_billable / $total, 2) : 0) . '%',
      ];
      if ($unknown) {
        $rows[] = ['Unknown<comment>*</comment>', DurationFormatter::formatDuration($unknown), round(100 * $unknown / $total, 2) . '%'];
        $rows[] = ['<comment>* Deleted or access denied tickets:</comment> ' . implode(',', $unknowns), '', ''];
      }
      $rows[] = new TableSeparator();
      $rows[] = ['Total', DurationFormatter::formatDuration($total), ''];
    }

    if ($period === static::MONTH) {
      $rows[] = new TableSeparator();
      $rows[] = ['', 'STATS', ''];
      $rows[] = new TableSeparator();
      $reference_point = $start ?: new \DateTime();
      $no_weekdays_in_month = $this->getWeekdaysInMonth($reference_point->format('m'), $reference_point->format('Y'));
      $days_passed = $this->getWeekdaysPassedThisMonth($output, $reference_point);
      $total_hrs = $this->getTotalMonthHours($reference_point->format('m'), $reference_point->format('Y'));

      $total_billable_hrs = $total_hrs * $this->billablePercentage;
      $total_non_billable_hrs = $total_hrs - $total_billable_hrs;
      $billable_hrs = $billable / 60 / 60;
      $non_billable_hrs = $non_billable / 60 / 60;
      $completed_hrs = $billable_hrs + $non_billable_hrs;

      $expected = 0;
      if ($no_weekdays_in_month > 0) {
        $expected = $days_passed / $no_weekdays_in_month;
      }
      $fraction_of_month = $days_passed / $no_weekdays_in_month;
      if (date('G') < 15 && $reference_point->format('Y-m-t') > date('Y-m-d')) {
        // If it's before 3pm, days_passed doesn't include the current day, add
        // it back.
        $fraction_of_month = (1 + $days_passed) / $no_weekdays_in_month;
      }
      $rows[] = $this->formatProgressRow('No. Days', $days_passed, $no_weekdays_in_month);
      $rows[] = $this->formatProgressRow('Billable Hrs', $billable_hrs, $total_billable_hrs, $fraction_of_month, $expected);
      $rows[] = $this->formatProgressRow('Non-billable Hrs', $non_billable_hrs, $total_non_billable_hrs, $fraction_of_month);
      $rows[] = $this->formatProgressRow('Total Hrs', $completed_hrs, $total_hrs, $fraction_of_month, $expected);
    }

    $table->setRows($rows);
    $table->render();

    return 0;
  }

  /**
   *
   */
  protected function getTotalMonthHours($m, $y) {
    $target_key = sprintf('%s_%s', $y, $m);
    // If we have no customisations it's just number of days times hours per
    // day.
    if (!isset($this->targets[$target_key])) {
      return $this->getWeekdaysInMonth($m, $y) * $this->hoursPerDay;
    }

    // You can also specify 1 number for how many days you work that month.
    if (strpos($this->targets[$target_key], ',') === FALSE) {
      return $this->targets[$target_key] * $this->hoursPerDay;
    }

    // We have a custom target.
    $total_hrs = 0;
    foreach (explode(',', $this->targets[$target_key]) as $day) {
      if (strpos($day, ':') !== FALSE) {
        [, $hrs_per_day] = explode(':', $day);
      }
      else {
        $hrs_per_day = $this->hoursPerDay;
      }
      $total_hrs += $hrs_per_day;
    }
    return $total_hrs;
  }

  /**
   * Generates a progress row based on numerator, denominator and caption.
   *
   * @param string $caption
   *   Row caption.
   * @param float $numerator
   *   Numerator for progress.
   * @param float $denominator
   *   Denominator for progress.
   * @param float $fraction_of_month
   *   Percentage of the month passed.
   * @param null $expected
   *   Expected progress.
   *
   * @return array
   *   Formatted row.
   */
  protected function formatProgressRow($caption, $numerator, $denominator, float $fraction_of_month = NULL, $expected = NULL) {
    if ($denominator < 0) {
      return [$caption, "$numerator/$denominator", '0%'];
    }
    $difference = sprintf('-%s', $denominator - $numerator);
    if ($numerator > $denominator) {
      $difference = sprintf('-%s', $numerator - $denominator);
    }
    $available = NULL;
    if ($fraction_of_month) {
      $available = sprintf('%8.2f', -1 * (($denominator * $fraction_of_month) - $numerator));
    }
    if ($expected && ($numerator / $denominator) < $expected) {
      return [
        $caption,
        "$numerator/$denominator ($difference)",
        '<error>' . round(100 * $numerator / $denominator, 2) . '%' . '</error>',
        $available,
      ];
    }
    return [
      $caption,
      "$numerator/$denominator ($difference)",
      round(100 * $numerator / $denominator, 2) . '%',
      $available,
    ];
  }

  /**
   *
   */
  protected function getWeekdaysInMonth($m, $y) {
    $target_key = sprintf('%s_%s', $y, $m);
    if (isset($this->targets[$target_key])) {
      $target = $this->targets[$target_key];
      if (strpos($target, ',') !== FALSE) {
        return count(explode(',', $target));
      }
      return $target;
    }
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

  /**
   *
   */
  protected function getWeekdaysPassedThisMonth($output, \DateTime $reference_point) {
    $days_passed = $reference_point->format('d');
    if ($reference_point->format('Y-m-t') < date('Y-m-d')) {
      // Past month.
      $days_passed = $reference_point->format('t');
    }
    $weekdays = 0;
    $target_key = sprintf('%s_%s', $reference_point->format('Y'), $reference_point->format('m'));
    if (isset($this->targets[$target_key])) {
      $target = $this->targets[$target_key];
      if (strpos($target, ',') !== FALSE) {
        $passed = array_filter(explode(',', $target), function ($item) use ($days_passed) {
          if (strpos($item, ':') !== FALSE) {
            [$item, $partial] = explode(':', $item);
          }
          return $item <= $days_passed;
        });
        $weekdays = count($passed);
      }
    }
    if (!$weekdays) {
      for ($i = 0; $i < $days_passed; $i++) {
        $day_of_week = DateHelper::startOfMonth($reference_point)
          ->modify(sprintf('+%d days', $i))
          ->format('w');
        if ($day_of_week != self::SUNDAY && $day_of_week != self::SATURDAY) {
          $weekdays++;
        }
      }
    }
    // Don't include the current day before 3pm?
    if (date('G') < 15 && $reference_point->format('Y-m-t') > date('Y-m-d')) {
      $weekdays -= 1;
    }
    return $weekdays;
  }

  /**
   * {@inheritdoc}
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container) {
    $root_node->children()
      ->scalarNode('billable_percentage')
      ->defaultValue(0.8)
      ->end()
      ->scalarNode('hours_per_day')
      ->defaultValue(8)
      ->end()
      ->arrayNode('days_per_month')
      ->prototype('scalar')
      ->end()
      ->end();
    return $root_node;
  }

  /**
   * {@inheritdoc}
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container) {
    $default_percentage = isset($config['billable_percentage']) ? $config['billable_percentage'] : 0.8;
    $default_hours_per_day = isset($config['hours_per_day']) ? $config['hours_per_day'] : 8;
    $config = ['billable_percentage' => '', 'hours_per_day' => ''] + $config;
    $question = new Question(sprintf('Target billable percentage: <comment>[%s]</comment>', $default_percentage), $default_percentage);
    $config['billable_percentage'] = $helper->ask($input, $output, $question) ?: $default_percentage;
    $question = new Question(sprintf('Target hours per day: <comment>[%s]</comment>', $default_hours_per_day), $default_hours_per_day);
    $config['hours_per_day'] = $helper->ask($input, $output, $question) ?: $default_hours_per_day;
    $config['days_per_month'] = isset($config['days_per_month']) ? $config['days_per_month'] : [];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config) {
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults($config, ContainerBuilder $container) {
    return $config + [
      'billable_percentage' => 0.8,
      'hours_per_day' => 8,
      'days_per_month' => [],
    ];
  }

  /**
   * Write number of target days for month.
   *
   * @param int $target
   *   Target days.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   * @param \DateTime $date
   *   (optional) Date to use as reference.
   *
   * @return null|int
   *   The target or NULL if it was invalid.
   */
  protected function writeTarget($target, OutputInterface $output, \DateTime $date = NULL) {
    if (strpos($target, ',') !== FALSE) {
      $days = explode(',', $target);
      $invalid = array_filter($days, function ($item) {
        return !is_numeric($item);
      });
      if ($invalid) {
        $output->writeln('<error>You must use a number for each target</error>');
        return NULL;
      }
    }
    elseif (!is_numeric($target)) {
      $output->writeln('<error>You must use a number for the target</error>');
      return NULL;
    }
    $file = $this->directory . '/.tl.yml';
    if (file_exists($file)) {
      $date = $date ?: new \DateTime();
      $config = Yaml::parse(file_get_contents($file));
      $output->writeln(sprintf('<info>Wrote target for month: %s</info>', $target));
      $config['days_per_month'][$date->format('Y-m')] = $target;
      file_put_contents($file, Yaml::dump($config));
    }
    return $target;
  }

}
