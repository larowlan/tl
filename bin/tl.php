#!/usr/bin/env php
<?php
// Main executable.
require __DIR__ .'/../vendor/autoload.php';

use Larowlan\Tl\Commands\Configure;
use Symfony\Component\Console\Application;

$application = new Application('Time logger', '0.0.1');
$application->add(new Configure());
$application->run();
