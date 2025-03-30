<?php

namespace Smitmartijn\ScheduleMonitor;

use Illuminate\Console\Scheduling\Event;
use Smitmartijn\ScheduleMonitor\Http\Client;
use Smitmartijn\ScheduleMonitor\Jobs\SendHeartbeatJob;
use Smitmartijn\ScheduleMonitor\Helpers\ScheduleMonitorHelper;
use ReflectionClass;

class ScheduleMonitor
{
  /**
   * The package configuration.
   *
   * @var array
   */
  protected $config;

  /**
   * The HTTP client instance.
   *
   * @var \Smitmartijn\ScheduleMonitor\Http\Client
   */
  protected $client;

  /**
   * Create a new ScheduleMonitor instance.
   *
   * @param  array  $config
   * @return void
   */
  public function __construct(array $config, ?Http\Client $client = null)
  {
    $this->config = $config;
    $this->client = $client ?: app('schedule-monitor.http-client');
  }

  /**
   * Send a heartbeat for the given event.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @param  string  $status
   * @param  float|null  $runtime
   * @return bool
   */
  public function sendHeartbeat(Event $event, string $status = 'success', ?float $runtime = null): bool
  {
    if (! $this->shouldMonitorEvent($event)) {
      return false;
    }

    $jobName = ScheduleMonitorHelper::getEventName($event);

    // If it's an artisan command, clean it up
    if (ScheduleMonitorHelper::isArtisanCommand($jobName)) {
      $jobName = ScheduleMonitorHelper::cleanArtisanCommand($jobName);
    }

    // Dispatch a job to send the heartbeat asynchronously
    // This ensures that heartbeat sending failures don't affect the original job
    if (isset($this->config['use_queue']) && $this->config['use_queue']) {
      dispatch(new SendHeartbeatJob($jobName, $status, $runtime))
        ->onQueue($this->config['heartbeat_queue'] ?? 'default');
      return true;
    }

    // Otherwise send heartbeat synchronously but don't let exceptions bubble up
    try {
      $response = $this->client->sendHeartbeat([
        'job' => $jobName,
        'status' => $status,
        'runtime' => $runtime,
      ]);

      return $response->successful();
    } catch (\Exception $e) {
      report($e);

      return false;
    }
  }

  /**
   * Determine if the event should be monitored.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return bool
   */
  protected function shouldMonitorEvent(Event $event): bool
  {
    // Check if monitoring is explicitly disabled for this event
    try {
      $reflection = new ReflectionClass($event);
      if ($reflection->hasProperty('monitoringEnabled')) {
        $property = $reflection->getProperty('monitoringEnabled');
        $property->setAccessible(true);
        if ($property->getValue($event) === false) {
          return false;
        }
      }
    } catch (\Exception $e) {
      // If we can't check, assume it should be monitored
    }

    // Check if the event matches any of the ignore patterns
    foreach ($this->config['ignore_patterns'] ?? [] as $pattern) {
      if (fnmatch($pattern, ScheduleMonitorHelper::getEventName($event))) {
        return false;
      }
    }

    return true;
  }


  /**
   * Get the grace period for the given event in minutes.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return int
   */
  public function getGracePeriod(Event $event): int
  {
    // Check if the event has a custom grace period
    try {
      $reflection = new ReflectionClass($event);
      if ($reflection->hasProperty('graceMinutes')) {
        $property = $reflection->getProperty('graceMinutes');
        $property->setAccessible(true);
        $value = $property->getValue($event);
        if ($value !== null) {
          return (int) $value;
        }
      }
    } catch (\Exception $e) {
      // If we can't access the property, use the default
    }

    // Otherwise, use the default grace period from config
    return $this->config['default_grace_period'] ?? 15;
  }

  /**
   * Get the HTTP client instance.
   *
   * @return \Smitmartijn\ScheduleMonitor\Http\Client
   */
  public function getClient(): Client
  {
    return $this->client;
  }

  /**
   * Send a test heartbeat for a specific job.
   *
   * @param  string  $jobName
   * @param  string  $status
   * @param  float|null  $runtime
   * @return bool
   */
  public function testHeartbeat(string $jobName, string $status = 'success', ?float $runtime = null): bool
  {
    try {
      $response = $this->client->sendHeartbeat([
        'job' => $jobName,
        'status' => $status,
        'runtime' => $runtime,
      ]);

      return $response->successful();
    } catch (\Exception $e) {
      report($e);

      return false;
    }
  }
}
