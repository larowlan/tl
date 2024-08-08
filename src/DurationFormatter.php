<?php

namespace Larowlan\Tl;

/**
 * Utility formatter.
 */
class DurationFormatter {

  /**
   * The ISO8601 format.
   */
  const FORMAT_ISO8601 = "iso8601";

  /**
   * Formats a duration.
   *
   * @param int $duration
   *   Duration in seconds.
   * @param string|null $format
   *   (optional) The output format.
   *
   * @return string
   *   Formatted duration.
   */
  public static function formatDuration(int $duration, ?string $format = NULL): string {
    if ($duration < 60) {
      // Less than one minute.
      $seconds = str_pad($duration, 2, '0', STR_PAD_LEFT);
      return self::FORMAT_ISO8601 === $format ? "PT{$seconds}S" : "{$seconds} secs";
    }
    elseif ($duration < 3600) {
      // Less than one hour.
      $minutes = str_pad(floor($duration / 60), 2, '0', STR_PAD_LEFT);
      $seconds = str_pad(($duration - ($minutes * 60)), 2, '0', STR_PAD_LEFT);
      return self::FORMAT_ISO8601 === $format ? "PT{$minutes}M{$seconds}S" : "$minutes:$seconds m";
    }
    else {
      // Over one hour.
      $hours = floor($duration / 3600);
      $minutes = str_pad(floor((($duration - ($hours * 3600)) / 60)), 2, '0', STR_PAD_LEFT);
      $seconds = str_pad(($duration - ($hours * 3600) - ($minutes * 60)), 2, '0', STR_PAD_LEFT);
      return self::FORMAT_ISO8601 === $format ? "PT{$hours}H{$minutes}M{$seconds}S" : "$hours:$minutes:$seconds";
    }
  }

  /**
   * Formats a duration in ISO8601 format.
   *
   * @param int $duration
   *   The duration in seconds.
   *
   * @return string
   *   The formatted string.
   */
  public static function formatDurationISO8601(int $duration): string {
    return self::formatDuration($duration, self::FORMAT_ISO8601);
  }

}
