<?php

namespace Smitmartijn\ScheduleMonitor;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\ServiceProvider;
use Smitmartijn\ScheduleMonitor\Commands\SyncCommand;
use Smitmartijn\ScheduleMonitor\Commands\TestHeartbeatCommand;
use Smitmartijn\ScheduleMonitor\EventSubscribers\ScheduleMonitorEventSubscriber;

class ScheduleMonitorServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   *
   * @return void
   */
  public function register()
  {
    $this->mergeConfigFrom(
      __DIR__ . '/../config/schedule-monitor.php',
      'schedule-monitor'
    );

    // Register the HTTP Client with a namespaced binding
    $this->app->bind('schedule-monitor.http-client', function ($app) {
      return new Http\Client(
        $app->make('config')->get('schedule-monitor')
      );
    });

    // Register the main ScheduleMonitor class
    $this->app->singleton(ScheduleMonitor::class, function ($app) {
      return new ScheduleMonitor(
        $app->make('config')->get('schedule-monitor'),
        $app->make('schedule-monitor.http-client')
      );
    });
  }

  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot()
  {
    if ($this->app->runningInConsole()) {
      $this->publishes([
        __DIR__ . '/../config/schedule-monitor.php' => config_path('schedule-monitor.php'),
      ], 'config');

      $this->commands([
        SyncCommand::class,
        TestHeartbeatCommand::class,
      ]);
    }

    // Register the event subscriber
    EventFacade::subscribe(ScheduleMonitorEventSubscriber::class);

    // Add the withoutMonitoring macro to the Event class
    Event::macro('withoutMonitoring', function () {
      $this->monitoringEnabled = false;

      return $this;
    });

    // Initialize custom property on all scheduler events
    $this->app->resolving(Schedule::class, function ($schedule) {
      $events = $schedule->events();

      foreach ($events as $event) {
        $event->monitoringEnabled = true;
      }
    });
  }
}
