# Laravel Schedule Monitor

Monitor your Laravel scheduled tasks with automatic heartbeats and receive alerts when jobs fail to run.

**Disclaimer**: I built this for WhatPulse, and don't intend on productizing it beyond my use cases. PRs are welcome, though!

## Installation

You can install the package via composer:

```bash
composer require smitmartijn/laravel-schedule-monitor
```

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --provider="Smitmartijn\ScheduleMonitor\ScheduleMonitorServiceProvider" --tag="config"
```

## Configuration

After publishing the configuration file, you'll find it at `config/schedule-monitor.php`. You'll need to set these environment variables in your `.env` file:

```
SCHEDULE_MONITOR_API_URL=https://your-monitor-app.pages.dev
SCHEDULE_MONITOR_API_TOKEN=your-api-token
```

Make sure the API token matches the one you set in your monitoring application.

## Usage

### Syncing Your Scheduled Tasks

Before the monitoring can begin, you need to sync your scheduled tasks with the monitoring application. Run the following command:

```bash
php artisan schedule:monitor:sync
```

This will scan all your scheduled tasks and send them to the monitoring application. It's recommended to run this command in your deployment process so that any changes to your scheduled tasks are automatically synced.

### Testing Heartbeats

You can send a test heartbeat for a job to verify the monitoring connection is working properly:

```bash
php artisan schedule:monitor:test-heartbeat "php artisan your:command"
```

This will send a test heartbeat for the specified job. The job name should match exactly what appears in the monitoring app after running the sync command.

Options:
- `--status=success|failure` - Set the job status (default: success)
- `--runtime=0.5` - Set a custom runtime in seconds (default: 0.1)

Example:
```bash
# Test a job with failure status
php artisan schedule:monitor:test-heartbeat "php artisan emails:send" --status=failure
```

### Customizing Monitoring

By default, all scheduled tasks will be monitored. If you want to exclude specific tasks from monitoring, you can use the `withoutMonitoring` method in your scheduler:

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // This job will be monitored
    $schedule->command('emails:send')->daily();

    // This job will NOT be monitored
    $schedule->command('horizon:snapshot')
        ->everyFiveMinutes()
        ->withoutMonitoring();
}
```

### Setting Custom Grace Periods

You can set a custom grace period for individual tasks using the `graceMinutes` method:

```php
$schedule->command('import:data')
    ->daily()
    ->graceMinutes(60); // Allow up to 60 minutes delay before marking as missed
```

If not specified, the default grace period from the configuration will be used.

## How It Works

1. When you run the `schedule:monitor:sync` command, all eligible scheduled tasks are sent to the monitoring application.
2. When a task finishes running, the package automatically sends a heartbeat to the monitoring application.
3. The monitoring application checks if tasks are running on schedule and raises alerts when they miss their expected run time.

## Monitoring Multiple Applications

You can use the same monitoring application for multiple Laravel applications. Each application will need its own API token, and its jobs will be tracked separately.

## Troubleshooting

### Heartbeats Not Being Sent

Make sure:
1. The Laravel application can reach the monitoring API URL
2. The API tokens match on both sides
3. The scheduled task isn't excluded by the ignore patterns in the config

### Check Connectivity

You can test the connection to the monitoring service by running:

```bash
php artisan schedule:monitor:sync
```

If this command completes successfully, the connection is working.

## Security

If you discover any security issues, please use the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.