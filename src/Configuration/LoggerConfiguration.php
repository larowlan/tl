<?php
/**
 * @file
 * Contains LoggerConfiguration.php
 */

namespace Larowlan\Tl\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class LoggerConfiguration implements ConfigurationInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder() {
    $tree = new TreeBuilder();
    $root = $tree->root('redmine');
    $root->children()
      ->scalarNode('api_key')
        ->isRequired()
      ->end()
      ->scalarNode('url')
        ->defaultValue('https://redmine.previousnext.com.au')
        ->isRequired()
      ->end()
    ->end();
    return $tree;
  }

}
