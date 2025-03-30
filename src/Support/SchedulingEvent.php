<?php

namespace Smitmartijn\ScheduleMonitor\Support;

use Illuminate\Console\Scheduling\Event;

class SchedulingEvent
{
  /**
   * Add methods to customize grace period for scheduled events.
   */
  public static function registerMacros()
  {
    // Add method to set a custom grace period
    Event::macro('graceMinutes', function (int $minutes) {
      $this->graceMinutes = $minutes;

      return $this;
    });

    // Add method to disable monitoring for this event
    Event::macro('withoutMonitoring', function () {
      $this->monitoringEnabled = false;

      return $this;
    });
  }
}
