<?php
/**
 * @file
 * Contains \Larowlan\Tl\Application.php
 */

namespace Larowlan\Tl;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * Main application.
 */
class Application extends BaseApplication {

  /**
   * DI container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Error in valid schema.
   */
  const SCHEMA_ERROR = 1;

  /**
   * Schema not installed.
   */
  const INSTALL_ERROR = 2;

  /**
   * Application not configured.
   */
  const CONFIGURATION_ERROR = 3;

  /**
   * Constructor.
   *
   * @param string $name
   *   App name.
   * @param string $version
   *   App version.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   DI Container.
   */
  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN', ContainerBuilder $container) {
    parent::__construct($name, $version);
    $this->container = $container;
    $this->container->setParameter('version', $this->getVersion());
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    $name = $this->getCommandName($input);
    if ($name !== 'install' && $name !== 'configure') {
      if ($result = $this->setupCheck($output)) {
        return $result;
      }
    }
    elseif ($name == 'install') {
      if ($config_error = $this->checkConfig($output)) {
        return $config_error;
      }
    }
    foreach ($this->container->findTaggedServiceIds('command') as $service_id => $tags) {
      $this->add($this->container->get($service_id));
    }
    return parent::doRun($input, $output);
  }

  /**
   * Checks configuration is complete.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output pipe.
   *
   * @return int
   *   0 if A-OK, else an error code.
   *
   * @throws \Doctrine\DBAL\DBALException
   *   In case of DB error.
   */
  protected function setupCheck(OutputInterface $output) {
    if ($config_error = $this->checkConfig($output)) {
      return $config_error;
    }
    if ($schema_error = $this->checkSchema($output)) {
      return $schema_error;
    }
    return 0;
  }

  /**
   * Checks schema.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  private function checkSchema(OutputInterface $output) {
    // Check schema table and find if current version is installed.
    $sm = $this->container->get('connection')->getSchemaManager();
    $schema = $this->container->get('schema')->getSchema();

    $existing = new Schema($sm->listTables(), []);
    $comparator = new Comparator();
    $difference = $comparator->compare($existing, $schema);
    if ($statements = $difference->toSql($sm->getDatabasePlatform())) {
      $output->writeln('<error>Schema Error</error> Schema version is out of date: please run the <info>install</info> command.');
      return static::SCHEMA_ERROR;
    }
    return 0;
  }

  /**
   * Checks config.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  private function checkConfig(OutputInterface $output) {
    try {
      $processor = $this->container->get('config.processor');
      $configuration = $this->container->get('config.configuration');
      $home = $this->container->getParameter('directory');
      $file = $home . '/.tl.yml';
      if (!file_exists($file)) {
        throw new InvalidConfigurationException(sprintf("File '%s' not found", $file));
      }
      $config = Yaml::parse(
        file_get_contents($file)
      );

      $processedConfiguration = $processor->processConfiguration(
        $configuration,
        [$config]
      );
      $this->container->setParameter('config', $processedConfiguration);
    }
    catch (InvalidConfigurationException $e) {
      $output->writeln('<error>Not configured</error> Application is not confiured: please run the <info>configure</info> command.');
      $output->writeln($e->getMessage());
      return static::CONFIGURATION_ERROR;
    }
    return 0;
  }

}
