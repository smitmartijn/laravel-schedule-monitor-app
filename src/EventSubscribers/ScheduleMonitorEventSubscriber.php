<?php

namespace Smitmartijn\ScheduleMonitor\EventSubscribers;

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Contracts\Foundation\Application;
use Smitmartijn\ScheduleMonitor\ScheduleMonitor;

class ScheduleMonitorEventSubscriber
{
  /**
   * The application instance.
   *
   * @var \Illuminate\Contracts\Foundation\Application
   */
  protected $app;

  /**
   * Create a new event subscriber instance.
   *
   * @param  \Illuminate\Contracts\Foundation\Application  $app
   * @return void
   */
  public function __construct(Application $app)
  {
    $this->app = $app;
  }

  /**
   * Register the listeners for the subscriber.
   *
   * @param  \Illuminate\Events\Dispatcher  $events
   * @return void
   */
  public function subscribe($events)
  {
    $events->listen(
      ScheduledTaskFinished::class,
      [self::class, 'handleTaskFinished']
    );
  }

  /**
   * Handle the scheduled task finished event.
   *
   * @param  \Illuminate\Console\Events\ScheduledTaskFinished  $event
   * @return void
   */
  public function handleTaskFinished(ScheduledTaskFinished $event)
  {
    $monitor = $this->app->make(ScheduleMonitor::class);

    $exitCode = $event->task->exitCode;
    $runtime = $event->runtime;
    $status = $exitCode === 0 ? 'success' : 'failure';

    try {
      $monitor->sendHeartbeat($event->task, $status, $runtime);
    } catch (\Exception $e) {
      // Log error but don't rethrow
      report($e);
    }
  }
}
