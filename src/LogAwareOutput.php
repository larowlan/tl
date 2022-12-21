<?php

namespace Larowlan\Tl;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines a class for decorating standard output with log functionality.
 */
class LogAwareOutput implements OutputInterface {

  /**
   * Decorated output.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  /**
   * Constructs a new LogAwareOutput.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   * @param \Psr\Log\LoggerInterface $log
   *   Logger.
   */
  public function __construct(OutputInterface $output, LoggerInterface $log) {
    $this->output = $output;
    $this->log = $log;
  }

  /**
   * {@inheritdoc}
   */
  public function writeln($messages, $type = OutputInterface::OUTPUT_NORMAL) {
    $messages = (array) $messages;
    foreach ($messages as $message) {
      $this->log->info(strip_tags($message));
    }
    $this->output->writeln($messages, $type);
  }

  /**
   * {@inheritdoc}
   */
  public function write($messages, $newline = FALSE, $type = self::OUTPUT_NORMAL) {
    $messages = (array) $messages;
    foreach ($messages as $message) {
      $this->log->info(strip_tags($message));
    }
    $this->output->write($messages, $newline, $type);
  }

  /**
   * {@inheritdoc}
   */
  public function setVerbosity($level) {
    $this->output->setVerbosity($level);
  }

  /**
   * {@inheritdoc}
   */
  public function getVerbosity(): int {
    return $this->output->getVerbosity();
  }

  /**
   * {@inheritdoc}
   */
  public function isQuiet(): bool {
    return self::VERBOSITY_QUIET === $this->getVerbosity();
  }

  /**
   * {@inheritdoc}
   */
  public function isVerbose(): bool {
    return self::VERBOSITY_VERBOSE <= $this->getVerbosity();
  }

  /**
   * {@inheritdoc}
   */
  public function isVeryVerbose(): bool {
    return self::VERBOSITY_VERY_VERBOSE <= $this->getVerbosity();
  }

  /**
   * {@inheritdoc}
   */
  public function isDebug(): bool {
    return self::VERBOSITY_DEBUG <= $this->getVerbosity();
  }

  /**
   * {@inheritdoc}
   */
  public function setDecorated($decorated) {
    $this->output->setDecorated($decorated);
  }

  /**
   * {@inheritdoc}
   */
  public function isDecorated(): bool {
    return $this->output->isDecorated();
  }

  /**
   * {@inheritdoc}
   */
  public function setFormatter(OutputFormatterInterface $formatter) {
    $this->output->setFormatter($formatter);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatter(): OutputFormatterInterface {
    return $this->output->getFormatter();
  }

}
