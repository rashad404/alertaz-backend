<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\CampaignExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a campaign once (manual "Execute"/send-now). Delegates to CampaignExecutor,
 * which renders + sends per recipient and then schedules the next run or completes.
 */
class ExecuteCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Do not retry a whole campaign send. */
    public int $tries = 1;

    /** Allow time for large recipient sets. */
    public int $timeout = 600;

    public function __construct(public Campaign $campaign)
    {
    }

    public function handle(CampaignExecutor $executor): void
    {
        $executor->execute($this->campaign);
    }

    /**
     * If the job dies (including fatal errors), never leave the campaign stuck
     * in the "sending" state — mark it failed so it can be retried/activated.
     */
    public function failed(?Throwable $e): void
    {
        $this->campaign->markAsFailed();
        Log::error("ExecuteCampaignJob failed for campaign {$this->campaign->id}: " . ($e?->getMessage() ?? 'unknown error'));
    }
}
