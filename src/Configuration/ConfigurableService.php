<?php
/**
 * @file
 * Contains \Larowlan\Tl\Configuration\ConfigurableService.
 */

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
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *
   * @return
   */
  public static function getConfiguration(NodeDefinition $root_node, ContainerBuilder $container);

  public static function askPreBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config, ContainerBuilder $container);

  public function askPostBootQuestions(QuestionHelper $helper, InputInterface $input, OutputInterface $output, array $config);

  public static function getDefaults($config, ContainerBuilder $container);

}
