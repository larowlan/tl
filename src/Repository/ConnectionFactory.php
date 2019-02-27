<?php

namespace Larowlan\Tl\Repository;

use Doctrine\DBAL\DriverManager;

/**
 * Database connection factory.
 */
class ConnectionFactory {

  /**
   * Creates a database connection.
   *
   * @param string $directory
   *   Directory.
   * @param string $db_name
   *   Db name.
   *
   * @return \Doctrine\DBAL\Connection
   *   New connection.
   */
  public static function createConnection($directory, $db_name) {
    $url = 'sqlite:///' . $directory . '/' . $db_name;
    $connection = DriverManager::getConnection([
      'url' => $url,
      'path' => $directory . '/' . $db_name,
    ]);
    return $connection;
  }

}
