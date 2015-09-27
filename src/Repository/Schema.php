<?php
/**
 * @file
 * Contains Schema.php
 */

namespace Larowlan\Tl\Repository;

use Doctrine\DBAL\Schema\Schema as DoctrineSchema;

class Schema {

  const version = 1;

  /**
   * @return DoctrineSchema;
   */
  public function getSchema() {
    $schema = new DoctrineSchema();
    $slots = $schema->createTable('slots');
    $slots->addColumn('id', 'integer')
      ->setAutoincrement(TRUE);
    $slots->addColumn('tid', 'bigint', ['unsigned' => TRUE]);
    $slots->addColumn('start', 'bigint', ['unsigned' => TRUE]);
    $slots->addColumn('end', 'bigint', ['unsigned' => TRUE])->setNotnull(FALSE);
    $slots->addColumn('teid', 'bigint', ['unsigned' => TRUE])->setNotnull(FALSE);
    $slots->addColumn('comment', 'string', ['length' => 255])->setNotnull(FALSE);
    $slots->addColumn('category', 'string', ['length' => 255])->setNotnull(FALSE);
    $slots->setPrimaryKey(['id']);
    $slots->addIndex(['start']);
    $slots->addIndex(['end']);
    $slots->addIndex(['tid']);
    $slots->addIndex(['teid']);
    return $schema;
  }
}
