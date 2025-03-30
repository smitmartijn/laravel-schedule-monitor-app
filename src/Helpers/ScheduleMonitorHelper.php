<?php

namespace Smitmartijn\ScheduleMonitor\Helpers;

use Illuminate\Console\Scheduling\Event;
use ReflectionClass;

class ScheduleMonitorHelper
{
  /**
   * Check if the command is an artisan command.
   *
   * @param string $command
   * @return bool
   */
  static public function isArtisanCommand(string $command): bool
  {
    // Check for the 'php artisan' in the command string
    // This will match both direct artisan commands and those with full PHP paths
    return preg_match('/(?:\'|")?(\/.*?php\d*\.?\d*|php\d*\.?\d*|php)(?:\'|")?\s+(?:\'|")?artisan(?:\'|")?/', $command) === 1;
  }

  /**
   * Clean up artisan command to extract just the command name.
   *
   * @param string $command
   * @return string
   */
  static public function cleanArtisanCommand(string $command): string
  {
    // First, check if it's an artisan command
    if (! static::isArtisanCommand($command)) {
      return $command;
    }

    // Extract the actual command part (after 'artisan')
    $command = trim($command);
    if (str_starts_with($command, "'") || str_starts_with($command, '"')) {
      preg_match_all('/(?:[^\s"\']++|"[^"]*+"|\'[^\']*+\')+/', $command, $matches);
      $parts = $matches[0] ?? [];

      // Remove quotes from each part
      $parts = array_map(function ($part) {
        return trim($part, "'\"");
      }, $parts);

      // Find the 'artisan' part and keep everything from there
      $artisanIndex = array_search('artisan', $parts);
      if ($artisanIndex !== false) {
        $artisanAndAfter = array_slice($parts, $artisanIndex);
        return 'php artisan ' . implode(' ', array_slice($artisanAndAfter, 1));
      }
    }

    // Handle unquoted commands with php path variations
    if (preg_match('/^(\/.*?php\d*\.?\d*|php\d*\.?\d*)\s+artisan\s+(.+)$/', $command, $matches)) {
      return 'php artisan ' . $matches[2];
    }

    return $command;
  }

  /**
   * Get the name of the event.
   *
   * @param  \Illuminate\Console\Scheduling\Event  $event
   * @return string
   */
  static public function getEventName(Event $event): string
  {
    // For command events, use the command name
    if (property_exists($event, 'command') && $event->command) {
      return $event->command;
    }

    // For job events and callback events, use reflection
    try {
      $reflection = new ReflectionClass($event);

      // Check for job property
      if ($reflection->hasProperty('job')) {
        $jobProperty = $reflection->getProperty('job');
        $jobProperty->setAccessible(true);
        $job = $jobProperty->getValue($event);

        if (is_string($job)) {
          return $job;
        } elseif (is_object($job)) {
          return get_class($job);
        }
      }

      // Check for callback property
      if ($reflection->hasProperty('callback')) {
        $callbackProperty = $reflection->getProperty('callback');
        $callbackProperty->setAccessible(true);
        $callback = $callbackProperty->getValue($event);

        if (is_array($callback) && is_object($callback[0])) {
          return get_class($callback[0]);
        }
      }

      // Check for description property - might contain class name for job callbacks
      if ($reflection->hasProperty('description')) {
        $descProperty = $reflection->getProperty('description');
        $descProperty->setAccessible(true);
        $description = $descProperty->getValue($event);

        if ($description && is_string($description) && class_exists($description)) {
          return $description;
        }
      }
    } catch (\Exception $e) {
      // If reflection fails, fall back to generic name
    }

    // Otherwise, use a generic name with a unique identifier
    return 'scheduled-closure-' . spl_object_hash($event);
  }
}
