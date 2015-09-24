<?php
/**
 * @file
 * Contains \Larowlan\Tl\Commands\Start.php
 */

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Start extends Command {

  protected $connection;

  public function __construct(Connection $connection) {
    $this->connection = $connection;
    parent::__construct();
  }


  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('start')
      ->setDescription('Starts a time entry')
      ->addArgument('issue_number', InputArgument::REQUIRED, 'Issue number to start work on');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $issue = $input->getArgument('issue_number');
    $sql = "SELECT * FROM time_entries WHERE issue_number = :issue_number";
    $stmt = $this->connection->prepare($sql);
    $stmt->bindValue("issue_number", $issue);
    $stmt->execute();
    $row = $stmt->fetch();
    $output->writeln(sprintf('Started new entry for <info>%s.</info>', $issue));
  }

}
