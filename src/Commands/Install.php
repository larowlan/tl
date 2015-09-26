<?php
/**
 * @file
 * Contains Install.php
 */

namespace Larowlan\Tl\Commands;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Larowlan\Tl\Repository\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command {

  protected $connection;
  protected $directory;
  protected $schema;

  public function __construct(Connection $connection, $directory, Schema $schema) {
    $this->connection = $connection;
    $this->directory = $directory;
    $this->schema = $schema;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('install')
      ->setDescription('Installs the sqlite schema');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $sm = $this->connection->getSchemaManager();
    $schema = $this->schema->getSchema();
    $existing = new DoctrineSchema($sm->listTables(), []);
    $comparator = new Comparator();
    $difference = $comparator->compare($existing, $schema);
    $count = 0;
    if ($statements = $difference->toSql($sm->getDatabasePlatform())) {
      foreach ($statements as $statement) {
        $this->connection->exec($statement);
        $count++;
      }
      $output->writeln(sprintf('Executed <info>%d</info> queries', $count));
    }
    else {
      $output->writeln('<comment>Schema is already up to date.</comment>');
    }
  }

}
