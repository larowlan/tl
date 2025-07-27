<?php

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\DurationFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Larowlan\Tl\Formatter
 */
class FormatterTest extends TestCase {

  /**
   * Tests formatter for times.
   *
   * @dataProvider providerFormatDuration
   * @covers ::formatDuration
   */
  public function testFormatDuration($start, $end, $expected): void {
    $duration = $end - $start;
    $this->assertEquals($expected, DurationFormatter::formatDuration($duration));
  }

  /**
   * Data provider for testFormatDuration.
   */
  public static function providerFormatDuration(): array {
    return [
      '> 3 hours' => [1449693579, 1449706179, '3:30:00'],
      '> an hour' => [1449693579, 1449697854, '1:11:15'],
      '> an hour, leading minutes' => [1449693579, 1449697254, '1:01:15'],
      '> an hour, leading seconds' => [1449693579, 1449697244, '1:01:05'],
      '< an hour' => [1449693579, 1449693579 + 3599, '59:59 m'],
      '< an hour, leading seconds' => [1449693579, 1449693579 + 3549, '59:09 m'],
      '< an hour, leading minutes' => [1449693579, 1449693579 + 549, '09:09 m'],
      '< an hour, trailing minutes' => [1449693579, 1449693579 + 1800, '30:00 m'],
      '< a minute' => [1449693579, 1449693579 + 59, '59 secs'],
      '< a minute, leading seconds' => [1449693579, 1449693579 + 9, '09 secs'],
    ];
  }

  /**
   * Tests ISO8601 formatter for times.
   *
   * @dataProvider providerFormatDurationISO8601
   * @covers ::formatDurationISO8601
   */
  public function testFormatDurationISO8601($start, $end, $expected): void {
    $duration = $end - $start;
    $this->assertEquals($expected, DurationFormatter::formatDurationISO8601($duration));
  }

  /**
   * Data provider for testFormatDuration.
   */
  public static function providerFormatDurationISO8601(): array {
    return [
      '> 3 hours' => [1449693579, 1449706179, 'PT3H30M00S'],
      '> an hour' => [1449693579, 1449697854, 'PT1H11M15S'],
      '> an hour, leading minutes' => [1449693579, 1449697254, 'PT1H01M15S'],
      '> an hour, leading seconds' => [1449693579, 1449697244, 'PT1H01M05S'],
      '< an hour' => [1449693579, 1449693579 + 3599, 'PT59M59S'],
      '< an hour, leading seconds' => [1449693579, 1449693579 + 3549, 'PT59M09S'],
      '< an hour, leading minutes' => [1449693579, 1449693579 + 549, 'PT09M09S'],
      '< an hour, trailing minutes' => [1449693579, 1449693579 + 1800, 'PT30M00S'],
      '< a minute' => [1449693579, 1449693579 + 59, 'PT59S'],
      '< a minute, leading seconds' => [1449693579, 1449693579 + 9, 'PT09S'],
    ];
  }

}
