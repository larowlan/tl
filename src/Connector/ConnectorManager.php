<?php

namespace Larowlan\Tl\Connector;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines a class for managing connectors.
 */
interface ConnectorManager extends Connector {

  /**
   * Evaluate backend for a given ticket.
   *
   * @param mixed $id
   *   Ticket ID.
   * @param \Symfony\Component\Console\Input\InputInterface|null $input
   *   Input.
   * @param \Symfony\Component\Console\Output\OutputInterface|null $output
   *   Output.
   *
   * @return string|false
   *   Connector ID.
   */
  public function spotConnector($id, InputInterface $input, OutputInterface $output);

}
