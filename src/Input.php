<?php

namespace Larowlan\Tl;

/**
 * Input utility.
 *
 * Parses user input into PHP data types.
 */
class Input {

  /**
   * Time unit suffix to seconds.
   */
  protected const TIME_UNITS = [
    's' => 1,
    'm' => 60,
    'h' => 3600,
  ];

  /**
   * Parses a user supplied interval.
   *
   * @param string $rawInterval
   *   An interval.
   *
   * @return int
   *   Interval in seconds.
   *
   * @throws \InvalidArgumentException
   *   When user input could not be parsed.
   */
  public static function parseInterval(string $rawInterval) {
    // Convert a string like "  1h    3h  " to ['1h', '3h'].
    $intervals = array_filter(array_map('trim', explode(' ', trim($rawInterval))));

    $total = 0;
    foreach ($intervals as $interval) {
      $interval = trim($interval);
      if (empty($interval)) {
        continue;
      }

      $firstChar = substr($interval, 0, 1);

      // Minutes.
      if ($firstChar === ':') {
        preg_match('/^\:(?<minute>\d{1,5})$/', $interval, $matches);
        if (!isset($matches['minute'])) {
          throw new \InvalidArgumentException('Could not parse minutes.');
        }
        $minute = $matches['minute'];

        // Pad single character. Single digit minutes should be prefixed with a
        // zero.
        // For example :5 === 50 minutes.
        $minute = str_pad($minute, 2, '0', \STR_PAD_RIGHT);

        $total += ($minute * 60);

        continue;
      }

      // Fractions of an hour.
      if (preg_match('/^\d{0,10}\.\d{1,10}$/', $interval, $matches)) {
        $total += (int) round((float) ($matches[0]) * 3600);
        continue;
      }

      // Short durations. E.g 10s, 1m, 1h, and concatenations 1h30m, '1h 30m'.
      // First, check without capture groups, in case user added extra junk
      // before or after.
      if (preg_match('/^(\d{1,10}[smh])+$/', $interval)) {
        // Re-match with capture groups.
        preg_match_all('/(?<num>\d{1,10})(?<suffix>[smh])+/U', $interval, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
          $number = (int) $match['num'];
          $suffix = $match['suffix'];
          // Regex guarantees index to exist.
          $total += $number * static::TIME_UNITS[$suffix];
        }

        continue;
      }

      throw new \InvalidArgumentException('Unable to parse interval.');
    }

    return $total;
  }

}
