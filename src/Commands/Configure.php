<?php

namespace Larowlan\Tl\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a class for configuring the app.
 */
class Configure extends Command implements ContainerAwareCommand {

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;
  protected $directory;
  protected $configurableServiceIds;

  /**
   *
   */
  public function __construct($directory, array $configurable_service_ids) {
    $this->directory = $directory;
    $this->configurableServiceIds = $configurable_service_ids;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('configure')
      ->setDescription('Configure your time logger');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $helper = $this->getHelper('question');
    $file = $this->directory . '/.tl.yml';
    if (file_exists($file)) {
      $config = Yaml::parse(file_get_contents($file));
      $output->writeln(sprintf('<info>Found existing file %s</info>', $file));
    }
    else {
      $output->writeln('<info>Creating new file</info>');
      $config = [];
    }
    foreach ($this->configurableServiceIds as $service_id) {
      $service_definition = $this->container->getDefinition($service_id);
      /** @var \Larowlan\Tl\Configuration\ConfigurableService $service_class */
      $service_class = $service_definition->getClass();
      $config = $service_class::getDefaults($config, $this->container);
      $config = $service_class::askPreBootQuestions($helper, $input, $output, $config, $this->container);
    }
    $this->container->setParameter('config', $config);
    // Now we can attempt boot.
    foreach ($this->configurableServiceIds as $service_id) {
      /** @var \Larowlan\Tl\Configuration\ConfigurableService $service */
      $service = $this->container->get($service_id);
      $config = $service->askPostBootQuestions($helper, $input, $output, $config);
    }
    $this->container->setParameter('config', $config);
    file_put_contents($file, Yaml::dump($config));
    $output->writeln(sprintf('<info>Wrote configuration to file %s</info>', $file));
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setContainerBuilder(ContainerBuilder $container) {
    $this->container = $container;
  }

}
