<?php

namespace Smitmartijn\ScheduleMonitor\EventSubscribers;

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
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

  /** @var array<int, true> */
  protected $finishedTasks = [];

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
    $events->listen(
      ScheduledTaskFailed::class,
      [self::class, 'handleTaskFailed']
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
    if ($exitCode !== 0) {
      $this->finishedTasks[spl_object_id($event->task)] = true;
    }
    $runtime = $event->runtime;
    $status = $exitCode === 0 ? 'success' : 'failure';

    try {
      $monitor->sendHeartbeat($event->task, $status, $runtime);
    } catch (\Throwable $e) {
      // Log error but don't rethrow
      report($e);
    }
  }

  /**
   * Report exceptions that occur before Laravel emits ScheduledTaskFinished.
   */
  public function handleTaskFailed(ScheduledTaskFailed $event)
  {
    // Non-zero command exits emit Finished and then Failed. Avoid reporting
    // those twice while still catching exceptions raised before Finished.
    $taskId = spl_object_id($event->task);
    if (isset($this->finishedTasks[$taskId])) {
      unset($this->finishedTasks[$taskId]);
      return;
    }

    try {
      $this->app->make(ScheduleMonitor::class)
        ->sendHeartbeat($event->task, 'failure');
    } catch (\Throwable $e) {
      report($e);
    }
  }
}
