<?php

namespace Smitmartijn\ScheduleMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Smitmartijn\ScheduleMonitor\Http\Client;

class SendHeartbeatJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /**
   * The name of the scheduled job.
   *
   * @var string
   */
  protected $jobName;

  /**
   * The status of the job execution.
   *
   * @var string
   */
  protected $status;

  /**
   * The runtime of the job in seconds.
   *
   * @var float|null
   */
  protected $runtime;

  /**
   * Create a new job instance.
   *
   * @param string $jobName
   * @param string $status
   * @param float|null $runtime
   * @return void
   */
  public function __construct(string $jobName, string $status = 'success', ?float $runtime = null)
  {
    $this->jobName = $jobName;
    $this->status = $status;
    $this->runtime = $runtime;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    try {
      $client = app('schedule-monitor.http-client');
      $client->sendHeartbeat([
        'job' => $this->jobName,
        'status' => $this->status,
        'runtime' => $this->runtime,
      ]);
    } catch (\Exception $e) {
      // Log the error but don't fail the job
      report($e);
    }
  }
}
