<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\Monitoring\CryptoMonitor;
use App\Services\Monitoring\WeatherMonitor;
use App\Services\Monitoring\WebsiteMonitor;
use App\Services\Monitoring\StockMonitor;
use App\Services\Monitoring\CurrencyMonitor;

class CheckAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The alert type to check (optional, null = check all).
     *
     * @var string|null
     */
    protected ?string $alertType;

    /**
     * Create a new job instance.
     *
     * @param string|null $alertType
     */
    public function __construct(?string $alertType = null)
    {
        $this->alertType = $alertType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting alert check job" . ($this->alertType ? " for type: {$this->alertType}" : " for all types"));

        $startTime = microtime(true);

        try {
            // Create monitor instances
            $monitors = [
                'crypto' => new CryptoMonitor(),
                'weather' => new WeatherMonitor(),
                'website' => new WebsiteMonitor(),
                'stock' => new StockMonitor(),
                'currency' => new CurrencyMonitor(),
            ];

            // Check specific type or all types
            if ($this->alertType && isset($monitors[$this->alertType])) {
                $monitors[$this->alertType]->checkAlerts();
            } else {
                // Check all monitors
                foreach ($monitors as $type => $monitor) {
                    try {
                        Log::info("Checking {$type} alerts...");
                        $monitor->checkAlerts();
                    } catch (\Exception $e) {
                        Log::error("Error checking {$type} alerts: " . $e->getMessage());
                        // Continue with other monitors even if one fails
                    }
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("Alert check job completed in {$duration} seconds");

        } catch (\Exception $e) {
            Log::error("Alert check job failed: " . $e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Alert check job failed after {$this->tries} attempts: " . $exception->getMessage());

        // You could send an admin notification here
        // Mail::to('admin@alert.az')->send(new AlertCheckFailed($exception));
    }
}