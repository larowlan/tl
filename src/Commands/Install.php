<?php

namespace Larowlan\Tl\Commands;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Larowlan\Tl\Repository\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Install extends Command implements PreinstallCommand {

  protected $connection;
  protected $directory;
  protected $schema;

  /**
   *
   */
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
      ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Output the commands to be run')
      ->addOption('skip-post', 's', InputOption::VALUE_NONE, 'Skip post install commands')
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
    $debug = $input->getOption('debug');
    $skip = $input->getOption('skip-post');
    if ($statements = $difference->toSql($sm->getDatabasePlatform())) {
      foreach ($statements as $statement) {
        $hash = hash('sha256', $statement);
        $post = $this->postInstall($hash);
        if ($debug) {
          $output->writeln($statement);
          if ($post) {
            $output->writeln($post);
          }
          continue;
        }
        $this->connection->exec($statement);
        if (!$skip && $post) {
          $this->connection->exec($post);
        }
        $count++;
      }
      $output->writeln(sprintf('Executed <info>%d</info> queries', $count));
    }
    else {
      $output->writeln('<comment>Schema is already up to date.</comment>');
    }
    return 0;
  }

  /**
   * Migration tasks.
   *
   * @param string $hash
   *   Statement hash
   *
   * @return string
   */
  protected function postInstall(string $hash) :?string {
    if ($hash === '8f5382517658027fcea333e593055c1133fe6f1576b3211391481ba9a8a51e57') {
      return "INSERT INTO chunks (sid, start, end) SELECT id, start, end FROM slots";
    }
    return NULL;
  }

}
