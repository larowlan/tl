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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
      ->setDescription('Shows asssigned stories')
      ->setHelp('Shows assigned stories. <comment>Usage:</comment> <info>tl assigned</info>');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($data = $this->connector->assigned()) {
      $table = new Table($output);
      $table->setHeaders(['JobId', 'Title']);
      $rows = [];
      foreach ($data as $id => $title) {
        $rows[] = [
          $id,
          $title,
        ];
      }
      $table->setRows($rows);
      $table->render();
      return;
    }
    $output->writeln('<error>No assigned tickets.</error>');
  }

}
