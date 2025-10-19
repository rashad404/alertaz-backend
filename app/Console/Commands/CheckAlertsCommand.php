<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckAlerts;
use App\Jobs\CheckUserAlert;
use App\Models\PersonalAlert;

class CheckAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check
                            {--type= : Check specific alert type (crypto, weather, website, stock, currency)}
                            {--user= : Check alerts for specific user ID}
                            {--alert= : Check specific alert ID}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and process personal alerts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $userId = $this->option('user');
        $alertId = $this->option('alert');
        $sync = $this->option('sync');

        // Check specific alert
        if ($alertId) {
            $alert = PersonalAlert::find($alertId);
            if (!$alert) {
                $this->error("Alert with ID {$alertId} not found.");
                return Command::FAILURE;
            }

            $this->info("Checking alert {$alertId}...");

            if ($sync) {
                $job = new CheckUserAlert($alert);
                $job->handle();
            } else {
                CheckUserAlert::dispatch($alert);
            }

            $this->info("Alert check initiated.");
            return Command::SUCCESS;
        }

        // Check alerts for specific user
        if ($userId) {
            $alerts = PersonalAlert::where('user_id', $userId)
                ->where('is_active', true)
                ->get();

            if ($alerts->isEmpty()) {
                $this->warn("No active alerts found for user {$userId}.");
                return Command::SUCCESS;
            }

            $this->info("Checking {$alerts->count()} alerts for user {$userId}...");

            foreach ($alerts as $alert) {
                if ($sync) {
                    $job = new CheckUserAlert($alert);
                    $job->handle();
                } else {
                    CheckUserAlert::dispatch($alert);
                }
            }

            $this->info("Alert checks initiated.");
            return Command::SUCCESS;
        }

        // Check all alerts or specific type
        $this->info($type
            ? "Checking all {$type} alerts..."
            : "Checking all alerts...");

        if ($sync) {
            $job = new CheckAlerts($type);
            $job->handle();
        } else {
            CheckAlerts::dispatch($type);
        }

        $this->info("Alert check job " . ($sync ? "completed" : "queued") . " successfully.");
        return Command::SUCCESS;
    }
}