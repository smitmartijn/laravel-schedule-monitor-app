<?php

return [
  /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the API endpoint and authentication for the
    | monitoring service.
    |
    */

  // The URL to the monitoring API
  'api_url' => env('SCHEDULE_MONITOR_API_URL', 'https://your-monitor-app.pages.dev'),

  // The API token used for authentication
  'api_token' => env('SCHEDULE_MONITOR_API_TOKEN'),

  /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP client used to communicate with the monitoring API.
    |
    */

  // Request timeout in seconds
  'timeout' => env('SCHEDULE_MONITOR_TIMEOUT', 5),

  // Connection timeout in seconds
  'connect_timeout' => env('SCHEDULE_MONITOR_CONNECT_TIMEOUT', 2),

  /*
    |--------------------------------------------------------------------------
    | Monitoring Settings
    |--------------------------------------------------------------------------
    |
    | General settings for job monitoring.
    |
    */

  // Default grace period in minutes before a job is considered late
  'default_grace_period' => env('SCHEDULE_MONITOR_DEFAULT_GRACE', 15),

  // Patterns for job names to ignore (supports * wildcards)
  'ignore_patterns' => [
    // Examples:
    // 'horizon:*',      // Ignore all Horizon commands
    // 'schedule:run',   // Ignore the scheduler itself
    // 'telescope:*',    // Ignore Telescope commands
  ],

  /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how heartbeats are sent to the monitoring service.
    |
    */

  // Whether to use queue for sending heartbeats (recommended)
  'use_queue' => env('SCHEDULE_MONITOR_USE_QUEUE', true),

  // Queue to use for sending heartbeats
  'heartbeat_queue' => env('SCHEDULE_MONITOR_QUEUE', 'default'),
];
