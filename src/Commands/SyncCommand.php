<?php

namespace Smitmartijn\ScheduleMonitor\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Smitmartijn\ScheduleMonitor\Facades\ScheduleMonitor;
use Smitmartijn\ScheduleMonitor\Helpers\ScheduleMonitorHelper;
use ReflectionClass;

class SyncCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'schedule:monitor:sync';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Sync scheduled tasks with the monitoring service';

  /**
   * Execute the console command.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return int
   */
  public function handle(Schedule $schedule)
  {
    $this->info('Syncing scheduled tasks...');

    $events = $schedule->events();
    $jobsToSync = [];

    foreach ($events as $event) {
      try {
        // Debug event properties
        $properties = $this->debugEventProperties($event);
        if ($this->getOutput()->isVerbose()) {
          $this->line('Event properties: ' . json_encode($properties));
        }

        if (!$this->shouldSyncEvent($event)) {
          $this->line("Skipping <comment>{ScheduleMonitorHelper::getEventName($event)}</comment> (excluded from monitoring)");
          continue;
        }

        $name = ScheduleMonitorHelper::getEventName($event);
        $description = $this->getEventDescription($event);

        // For artisan commands, clean up the command string and set description if empty
        if (isset($properties['command']) && ScheduleMonitorHelper::isArtisanCommand($properties['command'])) {
          $name = ScheduleMonitorHelper::cleanArtisanCommand($properties['command']);
          if (empty($description)) {
            $description = "Artisan command: " . $name;
          }
        }

        $jobsToSync[] = [
          'name' => $name,
          'description' => $description,
          'schedule' => $this->getEventExpression($event),
          'graceMinutes' => ScheduleMonitor::getGracePeriod($event),
          'isMonitored' => true,
        ];

        $this->line("Found scheduled task: <info>{$name}</info>");
      } catch (\Exception $e) {
        $this->error("Error processing event: " . $e->getMessage());
        if ($this->output->isVerbose()) {
          $this->line("Stack trace: " . $e->getTraceAsString());
        }
      }
    }

    if (empty($jobsToSync)) {
      $this->warn('No scheduled tasks found to sync');
      return 0;
    }

    $response = ScheduleMonitor::getClient()->syncJobs(['jobs' => $jobsToSync]);

    if ($response->successful()) {
      $syncData = $response->json();
      $stats = $syncData['stats'] ?? [];

      $this->info("Successfully synced {$syncData['jobCount']} scheduled tasks");
      $this->line("Synced at: <info>{$syncData['syncedAt']}</info>");

      if (!empty($stats)) {
        $this->line("");
        $this->line("Sync summary:");
        $this->line("- <info>{$stats['created']}</info> new jobs added");
        $this->line("- <info>{$stats['updated']}</info> existing jobs updated");
        $this->line("- <info>{$stats['removed']}</info> jobs removed");
      }
      return 0;
    }

    $this->error('Failed to sync scheduled tasks');
    $this->error($response->status() . ': ' . $response->body());
    return 1;
  }

  /**
   * Determine if the event should be synced.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return bool
   */
  protected function shouldSyncEvent($event): bool
  {
    // Skip events that have monitoring disabled
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

    // Skip events that match the ignore patterns
    $ignorePatterns = config('schedule-monitor.ignore_patterns', []);
    foreach ($ignorePatterns as $pattern) {
      if (fnmatch($pattern, ScheduleMonitorHelper::getEventName($event))) {
        return false;
      }
    }

    return true;
  }

  /**
   * Get the description of the event.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return string|null
   */
  protected function getEventDescription($event): ?string
  {
    if (property_exists($event, 'description') && $event->description) {
      return $event->description;
    }

    try {
      $reflection = new ReflectionClass($event);
      if ($reflection->hasProperty('description')) {
        $property = $reflection->getProperty('description');
        $property->setAccessible(true);
        return $property->getValue($event);
      }
    } catch (\Exception $e) {
      // If we can't access the description, return null
    }

    return null;
  }

  /**
   * Get the cron expression for the event.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return string
   */
  protected function getEventExpression($event): string
  {
    return $event->expression;
  }

  /**
   * Safely debug the event properties.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return array
   */
  protected function debugEventProperties($event): array
  {
    $properties = [];

    // Safely check for command
    if (property_exists($event, 'command') && $event->command) {
      $properties['command'] = $event->command;
    }

    // Safely check for job, callback, and other properties using reflection
    try {
      $reflection = new ReflectionClass($event);

      // Check for job property
      if ($reflection->hasProperty('job')) {
        $jobProperty = $reflection->getProperty('job');
        $jobProperty->setAccessible(true);
        $job = $jobProperty->getValue($event);

        if ($job !== null) {
          if (is_string($job)) {
            $properties['job'] = $job;
          } elseif (is_object($job)) {
            $properties['job'] = get_class($job);
          } else {
            $properties['job'] = gettype($job);
          }
        }
      }

      // Check for callback property
      if ($reflection->hasProperty('callback')) {
        $callbackProperty = $reflection->getProperty('callback');
        $callbackProperty->setAccessible(true);
        $callback = $callbackProperty->getValue($event);

        if ($callback !== null) {
          if (is_array($callback)) {
            if (is_object($callback[0])) {
              $properties['callback'] = 'array with object: ' . get_class($callback[0]);
            } else {
              $properties['callback'] = 'array with ' . gettype($callback[0]);
            }
          } elseif (is_string($callback)) {
            $properties['callback'] = $callback;
          } elseif (is_callable($callback)) {
            $properties['callback'] = 'callable';
          } else {
            $properties['callback'] = gettype($callback);
          }
        }
      }

      // Check for description property
      if ($reflection->hasProperty('description')) {
        $descProperty = $reflection->getProperty('description');
        $descProperty->setAccessible(true);
        $description = $descProperty->getValue($event);

        if ($description !== null) {
          $properties['description'] = $description;
        }
      }
    } catch (\Exception $e) {
      $properties['reflection_error'] = $e->getMessage();
    }

    // Get expression - this should be public
    $properties['expression'] = $event->expression;

    return $properties;
  }
}
