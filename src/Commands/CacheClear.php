<?php

namespace Larowlan\Tl\Commands;

use Doctrine\Common\Cache\FlushableCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines a class for clearing the cache.
 */
class CacheClear extends Command {

  protected $cache;

  /**
   *
   */
  public function __construct(FlushableCache $cache) {
    $this->cache = $cache;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('cache-clear')
      ->setDescription('Clear your cached back-end data');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->cache->flushAll();
    $output->writeln('<info>Caches cleared</info>');
    return 0;
  }

}
