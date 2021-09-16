<?php

namespace Larowlan\Tl;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Larowlan\Tl\Commands\ContainerAwareCommand;
use Larowlan\Tl\Commands\LogAwareCommand;
use Larowlan\Tl\Commands\PreinstallCommand;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
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
  public function __construct($name, $version, ContainerBuilder $container) {
    parent::__construct($name, $version);
    $this->container = $container;
    $this->container->setParameter('version', $this->getVersion());
    $this->container->setParameter('configurable_service_ids', array_keys($this->container->findTaggedServiceIds('configurable')));
    // Logger.
    $log = new Logger('tl');
    $logger_file = $this->container->getParameter('directory') . '/.tl.log';
    $log->pushHandler(new StreamHandler($logger_file, Logger::INFO));
    $this->container->set('logger', $log);
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    $name = $this->getCommandName($input);
    $installing = FALSE;
    if ($name !== 'install' && $name !== 'configure') {
      if ($result = $this->setupCheck($output)) {
        return $result;
      }
    }
    elseif ($name == 'install') {
      $installing = TRUE;
      if ($config_error = $this->checkConfig($output)) {
        return $config_error;
      }
    }
    elseif ($name == 'configure') {
      $installing = TRUE;
    }
    foreach ($this->container->findTaggedServiceIds('command') as $service_id => $tags) {
      if (!$installing || in_array(PreinstallCommand::class, class_implements($this->container->getDefinition($service_id)->getClass()), TRUE)) {
        // Don't add any commands until install is complete.
        $service = $this->container->get($service_id);
        if ($service instanceof ContainerAwareCommand) {
          // Some pre-install commands need the container to find configurable
          // services.
          $service->setContainerBuilder($this->container);
        }
        $this->add($service);
      }
    }
    $command = $this->find($name);
    if ($command instanceof LogAwareCommand) {
      // Use the log aware output handler.
      $output = new LogAwareOutput($output, $this->container->get('logger'));
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
   *   Output.
   *
   * @return int
   *   Schema status.
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
   *   Output.
   *
   * @return int
   *   Config status.
   */
  private function checkConfig(OutputInterface $output) {
    try {
      $processor = $this->container->get('config.processor');
      /** @var \Larowlan\Tl\Configuration\ConfigurationCollector|ConfigurationInterface|\Larowlan\Tl\Configuration\LoggerConfiguration $configuration */
      $configuration = $this->container->get('config.configuration');
      $configuration->setContainerBuilder($this->container);
      $needs_config_ids = $this->container->getParameter('configurable_service_ids');
      $needs_config = [];
      foreach ($needs_config_ids as $id) {
        $needs_config[$id] = $this->container->getDefinition($id)->getClass();
      }
      $configuration->setConfigurableServices($needs_config);
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
