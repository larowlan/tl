<?php
/**
 * @file
 * Contains ConnectionFactory.php
 */

namespace Larowlan\Tl\Repository;


use Doctrine\DBAL\DriverManager;

class ConnectionFactory {

  public static function createConnection($directory, $db_name) {
    $url = 'sqlite:///' . $directory . '/' . $db_name;
    $connection = DriverManager::getConnection([
      'url' => $url,
      'path' => $directory . '/' . $db_name,
    ]);
    return $connection;
  }
}
