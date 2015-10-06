<?php

/**
 * @file
 * Contains \Larowlan\Tl\Commands\Configure.php
 */
namespace Larowlan\Tl\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a class for configuring the app.
 */
class Configure extends Command implements PreinstallCommand {

  protected $directory;

  public function __construct($directory) {
    $this->directory = $directory;
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
      $config = ['url' => '', 'api_key' => ''];
    }
    $default_url = isset($config['url']) ? $config['url'] : 'https://redmine.previousnext.com.au';
    $default_key = isset($config['api_key']) ? $config['api_key'] : '';
    // Reset.
    $config = [];
    $question = new Question(sprintf('Enter your redmine URL: <comment>[%s]</comment>', $default_url), $default_url);
    $config['url'] = $helper->ask($input, $output, $question) ?: $default_url;
    if (strpos($config['url'], 'https') !== 0) {
      $output->writeln('<comment>It is recommended to use https, POSTING over http is not supported</comment>');
    }
    $question = new Question(sprintf('Enter your redmine API Key: <comment>[%s]</comment>', $default_key), $default_key);
    $config['api_key'] = $helper->ask($input, $output, $question) ?: $default_key;
    file_put_contents($file, Yaml::dump($config));
    $output->writeln(sprintf('<info>Wrote configuration to file %s</info>', $file));
  }

}
