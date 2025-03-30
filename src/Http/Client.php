<?php

namespace Smitmartijn\ScheduleMonitor\Http;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class Client
{
  /**
   * The HTTP client instance.
   *
   * @var \Illuminate\Http\Client\Factory
   */
  protected $http;

  /**
   * The base URL for the monitoring API.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * The API token for authentication.
   *
   * @var string
   */
  protected $apiToken;

  /**
   * Create a new client instance.
   *
   * @param  array  $config
   * @return void
   */
  public function __construct(array $config)
  {
    $this->http = new Factory();
    $this->baseUrl = rtrim($config['api_url'] ?? '', '/');
    $this->apiToken = $config['api_token'] ?? '';

    // Configure the HTTP client with default options
    $this->http->withOptions([
      'timeout' => $config['timeout'] ?? 5,
      'connect_timeout' => $config['connect_timeout'] ?? 2,
    ]);
  }

  /**
   * Send a heartbeat for a job.
   *
   * @param  array  $data
   * @return \Illuminate\Http\Client\Response
   */
  public function sendHeartbeat(array $data): Response
  {
    return $this->post('/api/heartbeat', $data);
  }

  /**
   * Sync the scheduled jobs.
   *
   * @param  array  $data
   * @return \Illuminate\Http\Client\Response
   */
  public function syncJobs(array $data): Response
  {
    return $this->post('/api/sync', $data);
  }

  /**
   * Get the status of all jobs.
   *
   * @return \Illuminate\Http\Client\Response
   */
  public function getStatus(): Response
  {
    return $this->get('/api/status');
  }

  /**
   * Send a POST request to the API.
   *
   * @param  string  $endpoint
   * @param  array  $data
   * @return \Illuminate\Http\Client\Response
   */
  protected function post(string $endpoint, array $data): Response
  {
    return $this->http->withToken($this->apiToken)
      ->post($this->baseUrl . $endpoint, $data);
  }

  /**
   * Send a GET request to the API.
   *
   * @param  string  $endpoint
   * @return \Illuminate\Http\Client\Response
   */
  protected function get(string $endpoint): Response
  {
    return $this->http->withToken($this->apiToken)
      ->get($this->baseUrl . $endpoint);
  }
}
