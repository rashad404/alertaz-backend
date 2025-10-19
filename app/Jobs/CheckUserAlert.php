<?php

namespace App\Jobs;

use App\Models\PersonalAlert;
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

class CheckUserAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The personal alert to check.
     *
     * @var PersonalAlert
     */
    protected PersonalAlert $alert;

    /**
     * Create a new job instance.
     *
     * @param PersonalAlert $alert
     */
    public function __construct(PersonalAlert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if alert is not active
        if (!$this->alert->is_active) {
            Log::info("Skipping inactive alert {$this->alert->id}");
            return;
        }

        // Skip if not time to check yet
        if ($this->alert->last_checked_at &&
            $this->alert->last_checked_at->addSeconds($this->alert->check_frequency) > now()) {
            Log::info("Skipping alert {$this->alert->id}, not time to check yet");
            return;
        }

        Log::info("Checking alert {$this->alert->id} for user {$this->alert->user_id}");

        try {
            // Get the appropriate monitor based on alert type
            $monitor = $this->getMonitor($this->alert->alertType->slug);

            if (!$monitor) {
                Log::error("No monitor found for alert type: {$this->alert->alertType->slug}");
                return;
            }

            // Process the alert using the monitor's base class method
            $reflector = new \ReflectionClass($monitor);
            $method = $reflector->getMethod('processAlert');
            $method->setAccessible(true);
            $method->invoke($monitor, $this->alert);

            Log::info("Successfully checked alert {$this->alert->id}");

        } catch (\Exception $e) {
            Log::error("Error checking alert {$this->alert->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the appropriate monitor for the alert type.
     */
    private function getMonitor(string $type)
    {
        return match ($type) {
            'crypto' => new CryptoMonitor(),
            'weather' => new WeatherMonitor(),
            'website' => new WebsiteMonitor(),
            'stock' => new StockMonitor(),
            'currency' => new CurrencyMonitor(),
            default => null,
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to check alert {$this->alert->id}: " . $exception->getMessage());

        // Optionally update the alert with error status
        $this->alert->update([
            'last_error' => $exception->getMessage(),
            'last_error_at' => now(),
        ]);
    }
}