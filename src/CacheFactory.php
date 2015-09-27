<?php
/**
 * @file
 * Contains CacheFactory.php
 */

namespace Larowlan\Tl;


use Doctrine\Common\Cache\FilesystemCache;

class CacheFactory {

  public static function create() {
    $dir = sys_get_temp_dir() . '/tl';
    if (!file_exists($dir)) {
      mkdir($dir);
    }
    return new FilesystemCache($dir, 'tl');
  }
}
