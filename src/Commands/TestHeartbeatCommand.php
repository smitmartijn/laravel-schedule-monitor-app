<?php

namespace Smitmartijn\ScheduleMonitor\Commands;

use Illuminate\Console\Command;
use Smitmartijn\ScheduleMonitor\Facades\ScheduleMonitor;

class TestHeartbeatCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'schedule:monitor:test-heartbeat
                            {job : The name of the job to test}
                            {--status=success : The status of the job (success or failure)}
                            {--runtime=0.1 : The runtime of the job in seconds}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Send a test heartbeat for a specific job to the monitoring service';

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $jobName = $this->argument('job');
    $status = $this->option('status');
    $runtime = (float) $this->option('runtime');

    $this->info("Sending test heartbeat for job: {$jobName}");
    $this->line("Status: {$status}");
    $this->line("Runtime: {$runtime} seconds");

    try {
      $result = ScheduleMonitor::testHeartbeat($jobName, $status, $runtime);

      if ($result) {
        $this->info('Heartbeat sent successfully!');
        $this->comment('Note: Check your monitoring dashboard to verify it was received.');
        return Command::SUCCESS;
      }

      $this->error('Failed to send heartbeat.');
      return Command::FAILURE;
    } catch (\Exception $e) {
      $this->error('Exception when sending heartbeat: ' . $e->getMessage());

      if ($this->getOutput()->isVerbose()) {
        $this->error($e->getTraceAsString());
      }

      return Command::FAILURE;
    }
  }
}
