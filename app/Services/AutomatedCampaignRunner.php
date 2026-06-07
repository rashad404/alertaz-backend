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

        // Run through the unified CampaignExecutor: it targets the correct table
        // (customers, or services filtered by service_type_id), applies the segment
        // filter with the same engine as the preview, renders + sends, records
        // cooldown, and schedules the next run / completes. The old
        // SegmentQueryBuilder + SendCampaignMessage path defaulted to the customer
        // table and silently sent nothing for service/email campaigns.
        $result = app(CampaignExecutor::class)->execute($campaign);

        Log::info("Campaign {$campaign->id}: executed", $result);

        return array_merge(['campaign_id' => $campaign->id, 'success' => true], $result);
    }
}
