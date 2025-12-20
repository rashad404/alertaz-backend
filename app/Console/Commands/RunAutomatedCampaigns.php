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
    protected $description = 'Run all due automated/triggered campaigns';

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

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($results as $result) {
            $campaignId = $result['campaign_id'];

            if ($result['success']) {
                $sent = $result['sent'] ?? 0;
                $failed = $result['failed'] ?? 0;
                $skipped = $result['skipped'] ?? 0;
                $mockMode = $result['mock_mode'] ?? false;

                $totalSent += $sent;
                $totalFailed += $failed;

                $mode = $mockMode ? ' [TEST MODE]' : '';
                $this->line("Campaign #{$campaignId}: Sent {$sent}, Failed {$failed}, Skipped {$skipped}{$mode}");
            } else {
                $error = $result['error'] ?? 'Unknown error';
                $this->error("Campaign #{$campaignId}: Failed - {$error}");
            }
        }

        $this->info("Finished running " . count($results) . " campaign(s). Total: {$totalSent} sent, {$totalFailed} failed.");

        return Command::SUCCESS;
    }
}
