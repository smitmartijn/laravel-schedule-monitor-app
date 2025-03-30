<?php

namespace Smitmartijn\ScheduleMonitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool sendHeartbeat(\Illuminate\Console\Scheduling\Event $event, string $status = 'success', ?float $runtime = null)
 * @method static bool testHeartbeat(string $jobName, string $status = 'success', ?float $runtime = null)
 * @method static int getGracePeriod(\Illuminate\Console\Scheduling\Event $event)
 * @method static \Smitmartijn\ScheduleMonitor\Http\Client getClient()
 *
 * @see \Smitmartijn\ScheduleMonitor\ScheduleMonitor
 */
class ScheduleMonitor extends Facade
{
  /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor()
  {
    return \Smitmartijn\ScheduleMonitor\ScheduleMonitor::class;
  }
}
