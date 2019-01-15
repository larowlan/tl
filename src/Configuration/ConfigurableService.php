<?php

namespace Larowlan\Tl\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Defines an interface for services that need configuration.
 */
interface ConfigurableService {

  /**
   * Gets configuration for the service.
   *
   * @param \Symfony\Component\Config\Definition\Builder\NodeDefinition $root_node
   *   Root node.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   Container.
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container);

  /**
   * Ask preboot questions.
   *
   * @param \Symfony\Component\Console\Helper\QuestionHelper $helper
   *   Helper.
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   * @param array $config
   *   Config.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   Container.
   *
   * @return array
   *   Updated config.
   */
  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container);

  /**
   * Ask post boot questions.
   *
   * @param \Symfony\Component\Console\Helper\QuestionHelper $helper
   *   Helper.
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   * @param array $config
   *   Config.
   *
   * @return array
   *   Updated config.
   */
  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config);

  /**
   * Get default config.
   *
   * @param mixed $config
   *   Config.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   Container.
   *
   * @return array
   *   Config.
   */
  public static function getDefaults($config, ContainerBuilder $container);

}
