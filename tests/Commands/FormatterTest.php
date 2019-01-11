<?php
/**
 * @file
 * Contains FormatterTest.php
 */

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Formatter;
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
  public function testFormatDuration($start, $end, $expected) {
    $duration = $end - $start;
    $this->assertEquals($expected, Formatter::formatDuration($duration));
  }

  /**
   * Data provider for testFormatDuration
   */
  public function providerFormatDuration() {
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

}
