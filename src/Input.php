<?php

namespace Larowlan\Tl;

/**
 * Input utility.
 *
 * Parses user input into PHP data types.
 */
class Input {

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
    $intervals = explode(' ', trim($rawInterval));

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
        if (strlen($minute) === 1) {
          // For example :5 === 50 minutes.
          $minute .= '0';
        }

        $total += ($minute * 60);

        continue;
      }

      // Fractions of an hour.
      elseif (preg_match('/^\d{0,10}\.\d{1,10}$/', $interval, $matches)) {
        $total += (int) round((float) ($matches[0]) * 3600);
        continue;
      }

      // Short durations. E.g 10s, 1m, 1h, and concatenations 1h30m, '1h 30m'.
      // First, check without capture groups, in case user added extra junk
      // before or after.
      elseif (preg_match('/^(\d{1,10}[smhd])+$/', $interval)) {
        // Re-match with capture groups.
        preg_match_all('/(?<num>\d{1,10})(?<suffix>[smhd])+/U', $interval, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
          $number = (int) $match['num'];
          switch ($match['suffix']) {
            case 's':
              $total += $number;
              break;

            case 'm':
              $total += $number * 60;
              break;

            case 'h':
              $total += $number * 3600;
              break;

            case 'd':
              $total += $number * 86400;
              break;

          }
        }

        continue;
      }

      throw new \InvalidArgumentException('Unable to parse interval.');
    }

    return $total;
  }

}
