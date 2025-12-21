<?php

namespace App\Jobs;

use App\Exceptions\TemplateRenderException;
use App\Models\Campaign;
use App\Models\CampaignContactLog;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\QuickSmsService;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Set high because rate limiter releases count as attempts.
     */
    public int $tries = 100;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     * This is what actually limits retries on real errors.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Backoff intervals in seconds for retries.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Campaign $campaign,
        public Contact $contact
    ) {}

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new RateLimited('sms')];
    }

    /**
     * Execute the job.
     */
    public function handle(TemplateRenderer $templateRenderer, QuickSmsService $smsService): void
    {
        // Skip if campaign is no longer active
        if (!in_array($this->campaign->status, [Campaign::STATUS_ACTIVE, Campaign::STATUS_SENDING])) {
            Log::info("Skipping SMS for campaign {$this->campaign->id}: campaign not active");
            return;
        }

        // Skip if contact already received message (cooldown check)
        if (CampaignContactLog::isInCooldown(
            $this->campaign->id,
            $this->contact->id,
            $this->campaign->cooldown_days
        )) {
            Log::info("Skipping SMS for contact {$this->contact->id}: in cooldown period");
            return;
        }

        // Check test mode
        $globalTestMode = config('services.quicksms.test_mode', false);
        $mockMode = $globalTestMode || $this->campaign->is_test;

        // Get user for balance
        $user = User::find($this->campaign->client->user_id);

        // Render message with strict mode (throws exception on unresolved variables)
        try {
            $message = $templateRenderer->renderStrict(
                $this->campaign->message_template,
                $this->contact
            );
        } catch (TemplateRenderException $e) {
            // Template rendering failed - permanent failure for this contact
            Log::warning("Template rendering failed for campaign {$this->campaign->id}", [
                'contact_id' => $this->contact->id,
                'phone' => $this->contact->phone,
                'error' => $e->getMessage(),
                'unresolved_variables' => $e->getUnresolvedVariables(),
            ]);

            // Record as failed (permanent - don't retry)
            $this->recordFailure(
                $user,
                $templateRenderer,
                $this->campaign->message_template, // Use template as-is
                $mockMode,
                'Template error: ' . $e->getMessage(),
                null
            );
            return;
        }

        $message = $templateRenderer->sanitizeForSMS($message);

        // Calculate cost
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * config('app.sms_cost_per_message', 0.04);

        // Check balance (skip in mock mode)
        if (!$mockMode && $user->balance < $cost) {
            Log::warning("Insufficient balance for campaign {$this->campaign->id}, user {$user->id}");
            // Pause campaign due to insufficient balance
            $this->campaign->pause();
            return;
        }

        // Send SMS
        $messageStatus = 'pending';
        $deliveryStatus = null;
        $externalId = null;
        $errorMessage = null;
        $errorCode = null;

        if ($mockMode) {
            $messageStatus = 'sent';
            $deliveryStatus = 'delivered';
        } else {
            // Check if Unicode is needed
            $unicode = $smsService->requiresUnicode($message);

            $result = $smsService->sendSMS($this->contact->phone, $message, $this->campaign->sender, $unicode);

            if ($result['success']) {
                $messageStatus = 'sent';
                $externalId = $result['transaction_id'] ?? null;
                $deliveryStatus = 'pending';
                $user->deductBalance($cost);
            } else {
                $errorCode = $result['error_code'] ?? -500;
                $errorMessage = $result['error_message'] ?? 'Unknown error';
                $errorType = $smsService->categorizeError($errorCode);

                Log::warning("Campaign SMS failed", [
                    'campaign_id' => $this->campaign->id,
                    'contact_id' => $this->contact->id,
                    'phone' => $this->contact->phone,
                    'error_code' => $errorCode,
                    'error_type' => $errorType,
                    'error' => $errorMessage,
                ]);

                // Handle based on error type
                switch ($errorType) {
                    case 'permanent':
                        // Invalid phone, blacklisted, invalid sender - never retry
                        $messageStatus = 'failed';
                        $deliveryStatus = 'failed';
                        $this->campaign->increment('failed_count');
                        break;

                    case 'auth':
                        // Auth error - pause campaign, needs admin action
                        $this->campaign->pause();
                        Log::error("Campaign {$this->campaign->id} paused due to auth error: {$errorMessage}");
                        // Record and return (no retry)
                        $this->recordFailure($user, $templateRenderer, $message, $mockMode, $errorMessage, $errorCode);
                        return;

                    case 'balance':
                        // Provider balance error - pause campaign
                        $this->campaign->pause();
                        Log::error("Campaign {$this->campaign->id} paused due to provider balance error");
                        // Record and return (no retry)
                        $this->recordFailure($user, $templateRenderer, $message, $mockMode, $errorMessage, $errorCode);
                        return;

                    case 'temporary':
                    default:
                        // Server error, timeout - retry with backoff
                        throw new RuntimeException("SMS send failed (temporary): {$errorMessage}");
                }
            }
        }

        // Create SMS message record
        SmsMessage::create([
            'user_id' => $this->campaign->created_by,
            'source' => 'campaign',
            'client_id' => $this->campaign->client_id,
            'campaign_id' => $this->campaign->id,
            'contact_id' => $this->contact->id,
            'phone' => $this->contact->phone,
            'message' => $message,
            'sender' => $this->campaign->sender,
            'cost' => $cost,
            'status' => $messageStatus,
            'is_test' => $mockMode,
            'provider_transaction_id' => $externalId,
            'error_message' => $errorMessage,
            'sent_at' => $messageStatus === 'sent' ? now() : null,
            'delivered_at' => $deliveryStatus === 'delivered' ? now() : null,
        ]);

        if ($messageStatus === 'sent') {
            $this->campaign->increment('sent_count');

            // Record in contact log for deduplication
            CampaignContactLog::recordSend($this->campaign->id, $this->contact->id);

            Log::info("SMS sent to {$this->contact->phone} for campaign {$this->campaign->id}");
        }
    }

    /**
     * Record a failed message (for cases where we don't want to retry)
     */
    private function recordFailure(
        User $user,
        TemplateRenderer $templateRenderer,
        string $message,
        bool $mockMode,
        string $errorMessage,
        ?int $errorCode
    ): void {
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * config('app.sms_cost_per_message', 0.04);

        SmsMessage::create([
            'user_id' => $this->campaign->created_by,
            'source' => 'campaign',
            'client_id' => $this->campaign->client_id,
            'campaign_id' => $this->campaign->id,
            'contact_id' => $this->contact->id,
            'phone' => $this->contact->phone,
            'message' => $message,
            'sender' => $this->campaign->sender,
            'cost' => $cost,
            'status' => 'failed',
            'is_test' => $mockMode,
            'error_message' => $errorMessage,
        ]);

        $this->campaign->increment('failed_count');
    }

    /**
     * Handle a job failure (after all retries exhausted).
     * Note: We don't increment failed_count here because:
     * 1. SMS API failures are already tracked when they occur
     * 2. Job failures (exceptions) are bugs that need fixing, not SMS delivery failures
     * 3. If job is manually retried and succeeds, the counter would be wrong
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send SMS to {$this->contact->phone} for campaign {$this->campaign->id}: " . $exception->getMessage());
    }
}
