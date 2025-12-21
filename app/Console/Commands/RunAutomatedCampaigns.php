<?php

namespace App\Console\Commands;

use App\Services\AutomatedCampaignRunner;
use Illuminate\Console\Command;

class RunAutomatedCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:run-automated';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all due automated/triggered campaigns (dispatches jobs to queue)';

    /**
     * Execute the console command.
     */
    public function handle(AutomatedCampaignRunner $runner): int
    {
        $this->info('Running automated campaigns...');

        $results = $runner->runDueCampaigns();

        if (empty($results)) {
            $this->info('No campaigns due to run.');
            return Command::SUCCESS;
        }

        $totalDispatched = 0;
        $totalSkipped = 0;

        foreach ($results as $result) {
            $campaignId = $result['campaign_id'];

            if ($result['success']) {
                $dispatched = $result['dispatched'] ?? 0;
                $skipped = $result['skipped'] ?? 0;
                $message = $result['message'] ?? null;

                $totalDispatched += $dispatched;
                $totalSkipped += $skipped;

                if ($message) {
                    $this->line("Campaign #{$campaignId}: {$message}");
                } else {
                    $this->line("Campaign #{$campaignId}: Dispatched {$dispatched} jobs, Skipped {$skipped} (cooldown)");
                }
            } else {
                $error = $result['error'] ?? 'Unknown error';
                $this->error("Campaign #{$campaignId}: Failed - {$error}");
            }
        }

        $this->info("Finished running " . count($results) . " campaign(s). Total: {$totalDispatched} jobs dispatched, {$totalSkipped} skipped.");

        if ($totalDispatched > 0) {
            $this->comment("Jobs will be processed by queue worker with rate limit: " . config('app.sms_rate_limit_per_second', 10) . " SMS/second");
        }

        return Command::SUCCESS;
    }
}
