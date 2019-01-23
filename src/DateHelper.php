<?php

namespace Larowlan\Tl;

/**
 * Defines a class for date helper functions.
 */
class DateHelper {

  /**
   * Gets the start of the month.
   *
   * @param \DateTime|null $start_date
   *   Date to get offset from.
   *
   * @return \DateTime
   *   Start of the month.
   */
  public static function startOfMonth($start_date = NULL) {
    $date = $start_date ?: new \DateTime();
    $date->setDate($date->format('Y'), $date->format('m'), 1)->setTime(0, 0, 0);
    return $date;
  }

  /**
   * Gets the start of the week.
   *
   * @param \DateTime|null $start_date
   *   Date to get offset from.
   *
   * @return \DateTime
   *   Start of the week.
   */
  public static function startOfWeek($start_date = NULL) {
    $date = $start_date ? $start_date->modify(('Sunday' === $start_date->format('l')) ? 'Monday last week' : 'Monday this week') : new \DateTime('this week');
    return $date->setTime(0, 0, 0);
  }

  /**
   * Gets the start of the day.
   *
   * @param \DateTime|null $start_date
   *   Date to get offset from.
   *
   * @return \DateTime
   *   Start of the day.
   */
  public static function startOfDay($start_date = NULL) {
    $date = $start_date ?: new \DateTime();
    return $date->setTime(0, 0, 0);
  }

}
