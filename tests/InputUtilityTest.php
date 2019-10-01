<?php

declare(strict_types = 1);

namespace Larowlan\Tl\Tests\Commands;

use Larowlan\Tl\Input;
use Larowlan\Tl\Tests\TlTestBase;

/**
 * Tests input utility class.
 *
 * @coversDefaultClass \Larowlan\Tl\Input
 */
class InputUtilityTest extends TlTestBase {

  /**
   * @covers ::parseInterval
   *
   * @dataProvider providerTestInterval
   */
  public function testInterval($string, ?string $assertValue, ?string $exceptionMessage = NULL): void {
    if (!isset($assertValue)) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage($exceptionMessage);
    }

    $return = Input::parseInterval($string);

    if (isset($assertValue)) {
      $this->assertEquals($assertValue, $return);
    }
  }

  /**
   * Data for testing.
   *
   * @see ::testInterval
   */
  public function providerTestInterval(): array {
    $data = [];

    // Invalid.
    $data['invalid junk around'] = ['daslkdjhagsjd', NULL, 'Unable to parse interval'];
    $data['invalid numbers'] = ['d4124haj', NULL, 'Unable to parse interval'];
    $data['invalid numbers spaces'] = ['d41 24haj', NULL, 'Unable to parse interval'];

    // Input coercion.
    $data['whitespace before'] = [' :15', 900];
    $data['whitespace after'] = [':15 ', 900];
    $data['whitespace around'] = [' :15 ', 900];
    $data['whitespace between'] = [' :10  :20 ', 1800];
    $data['whitespace junk between'] = [' :10 blah  :20 ', NULL, 'Unable to parse interval'];

    // Test minutes.
    $data['minutes - shortened'] = [':3', 1800];
    $data['minutes - minute'] = [':05', 300];
    $data['minutes - double'] = [':15', 900];
    $data['minutes - long'] = [':100', 6000];
    $data['minutes - suffix junk'] = [':100m', NULL, 'Could not parse minutes.'];

    // Test fractions.
    $data['fraction - double digit'] = ['.25', 900];
    $data['fraction - single digit'] = ['.5', 1800];
    $data['fraction - no decimal 1'] = ['1', 3600];
    $data['fraction - no decimal 2'] = ['2', 7200];
    $data['fraction - whole hour'] = ['1.0', 3600];
    $data['fraction - multi hour'] = ['1.5', 5400];
    $data['fraction - rounded'] = ['.55', 1980];
    $data['fraction - long'] = ['.75555', 2720];
    $data['fraction - suffix junk'] = ['.55m', NULL, 'Unable to parse interval'];

    // Friendly durations.
    $data['friendly - simple seconds'] = ['40s', 40];
    $data['friendly - simple minutes'] = ['40m', 40 * 60];
    $data['friendly - simple hours'] = ['40h', 40 * 3600];
    $data['friendly - hundreds minutes'] = ['400m', 400 * 60];
    $data['friendly - multiple concat'] = ['40s1m', 100];
    $data['friendly - whitespace'] = ['40s 1m', 100];
    $data['friendly - whitespace and concat'] = ['40s 1m 1s1s', 102];
    $data['friendly - disorderly multiple'] = ['1h2h1h1m40s', 3600 + 7200 + 3600 + 60 + 40];
    $data['friendly - junk on side'] = ['ak40s1ma', NULL, 'Unable to parse interval'];

    // Other
    $data['combination'] = ['900s :15 .25', 2700];

    return $data;
  }

}
