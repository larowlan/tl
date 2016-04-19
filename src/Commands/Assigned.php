<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Assigned.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Larowlan\Tl\Connector\Connector;
use Larowlan\Tl\Formatter;
use Larowlan\Tl\Repository\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Assigned extends Command {

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
      ->setName('assigned')
      ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Specify the user Id for which to retrieve the assigned tickets')
      ->setDescription('Shows asssigned stories')
      ->setHelp('Shows assigned stories. <comment>Usage:</comment> <info>tl assigned</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($data = $this->connector->assigned($input->getOption('user') ?: 'me')) {
      $table = new Table($output);
      $table->setHeaders(['JobId', 'Title']);
      $rows = [];
      $first = TRUE;
      foreach ($data as $project => $tickets) {
        if (!$first) {
          $rows[] = new TableSeparator();
        }
        $rows[] = ['', '<comment>' . $project . '</comment>'];
        $rows[] = new TableSeparator();
        foreach ($tickets as $id => $title) {
          $rows[] = [
            $id,
            $title,
          ];
        }
        $first = FALSE;
      }
      $table->setRows($rows);
      $table->render();
      return;
    }
    $output->writeln('<error>No assigned tickets.</error>');
  }

}
