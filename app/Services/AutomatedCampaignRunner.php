<?php

namespace App\Services;

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignContactLog;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

class AutomatedCampaignRunner
{
    protected SegmentQueryBuilder $queryBuilder;

    public function __construct(SegmentQueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Run all automated campaigns that are due
     *
     * @return array Results for each campaign run
     */
    public function runDueCampaigns(): array
    {
        $campaigns = Campaign::dueToRun()->get();

        $results = [];
        foreach ($campaigns as $campaign) {
            try {
                $results[] = $this->runCampaign($campaign);
            } catch (\Exception $e) {
                Log::error("Automated campaign {$campaign->id} failed: " . $e->getMessage());
                $results[] = [
                    'campaign_id' => $campaign->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Run a single automated campaign
     *
     * Dispatches jobs to queue for each eligible contact.
     * Jobs are processed asynchronously by queue workers with rate limiting.
     *
     * @param Campaign $campaign
     * @return array
     */
    public function runCampaign(Campaign $campaign): array
    {
        // Check if campaign has ended
        if ($campaign->hasEnded()) {
            $campaign->update(['status' => Campaign::STATUS_COMPLETED]);
            return [
                'campaign_id' => $campaign->id,
                'success' => true,
                'dispatched' => 0,
                'message' => 'Campaign ended',
            ];
        }

        $dispatchedCount = 0;
        $skippedCount = 0;

        // Use chunk to avoid loading all contacts into memory
        $this->queryBuilder->getMatchesQuery(
            $campaign->client_id,
            $campaign->segment_filter
        )->chunk(1000, function ($contacts) use ($campaign, &$dispatchedCount, &$skippedCount) {
            foreach ($contacts as $contact) {
                // Check cooldown before dispatching
                if (CampaignContactLog::isInCooldown(
                    $campaign->id,
                    $contact->id,
                    $campaign->cooldown_days
                )) {
                    $skippedCount++;
                    continue;
                }

                // Dispatch job to queue
                SendCampaignMessage::dispatch($campaign, $contact);
                $dispatchedCount++;
            }
        });

        // Schedule next run
        $campaign->scheduleNextRun();

        Log::info("Campaign {$campaign->id}: Dispatched {$dispatchedCount} jobs, skipped {$skippedCount} (cooldown)");

        return [
            'campaign_id' => $campaign->id,
            'success' => true,
            'dispatched' => $dispatchedCount,
            'skipped' => $skippedCount,
            'run_count' => $campaign->run_count,
        ];
    }
}
