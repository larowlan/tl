#!/usr/bin/env php
<?php
// Main executable.
require __DIR__ .'/../vendor/autoload.php';

use Larowlan\Tl\Application;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Australia/Brisbane');
}
$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__)));
$loader->load('services.yml');
$home = $_SERVER['HOME'];
$container->setParameter('directory', $home);
$container->set('container', $container);

$application = new Application('Time logger ⏰🪵✨', '@package_version@', $container);
$application->setDefaultCommand('list');
$application->run();
