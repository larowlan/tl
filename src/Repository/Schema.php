<?php

namespace Larowlan\Tl\Repository;

use Doctrine\DBAL\Schema\Schema as DoctrineSchema;

/**
 * Defines db schema.
 */
class Schema {

  /**
   * Schema version.
   */
  const version = 3;

  /**
   * Gets schema.
   *
   * @return \Doctrine\DBAL\Schema\Schema
   *   Schema object.
   */
  public function getSchema() {
    $schema = new DoctrineSchema();
    $slots = $schema->createTable('slots');
    $slots->addColumn('id', 'integer')
      ->setAutoincrement(TRUE);
    $slots->addColumn('tid', 'bigint', ['unsigned' => TRUE]);
    $slots->addColumn('teid', 'bigint', ['unsigned' => TRUE])->setNotnull(FALSE);
    $slots->addColumn('comment', 'string', ['length' => 255])->setNotnull(FALSE);
    $slots->addColumn('category', 'string', ['length' => 255])->setNotnull(FALSE);
    $slots->addColumn('connector_id', 'string', ['length' => 50])->setDefault('connector.redmine')->setNotnull(FALSE);
    $slots->setPrimaryKey(['id']);
    $slots->addIndex(['tid']);
    $slots->addIndex(['teid']);

    $chunks = $schema->createTable('chunks');
    $chunks->addColumn('id', 'integer')
      ->setAutoincrement(TRUE);
    $chunks->addColumn('sid', 'bigint', ['unsigned' => TRUE]);
    $chunks->addColumn('start', 'bigint', ['unsigned' => TRUE]);
    $chunks->addColumn('end', 'bigint', ['unsigned' => TRUE])->setNotnull(FALSE);
    $chunks->setPrimaryKey(['id']);
    $chunks->addIndex(['start']);
    $chunks->addIndex(['end']);
    $chunks->addIndex(['sid']);

    $aliases = $schema->createTable('aliases');
    $aliases->addColumn('tid', 'bigint', ['unsigned' => TRUE]);
    $aliases->addColumn('alias', 'string', ['length' => 255]);
    $aliases->addIndex(['alias']);
    $aliases->addIndex(['tid']);
    return $schema;
  }

}
