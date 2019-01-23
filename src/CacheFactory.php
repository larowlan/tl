<?php

namespace Larowlan\Tl;

use Doctrine\Common\Cache\FilesystemCache;

/**
 * Factory object for caches.
 */
class CacheFactory {

  /**
   * Creates a new cache bin.
   *
   * @return \Doctrine\Common\Cache\FilesystemCache
   *   Cache bin.
   */
  public static function create() {
    $dir = sys_get_temp_dir() . '/tl';
    if (!file_exists($dir)) {
      mkdir($dir);
    }
    return new FilesystemCache($dir, 'tl');
  }

}
