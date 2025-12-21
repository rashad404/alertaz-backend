<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignContactLog;
use App\Models\Contact;
use App\Models\SMSMessage;
use App\Models\User;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    public function handle(TemplateRenderer $templateRenderer): void
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

        // Render message
        $message = $templateRenderer->render(
            $this->campaign->message_template,
            $this->contact
        );
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

        if ($mockMode) {
            $messageStatus = 'sent';
            $deliveryStatus = 'delivered';
        } else {
            $result = $this->sendSMS($this->contact->phone, $message, $this->campaign->sender, $user);

            if ($result['success']) {
                $messageStatus = 'sent';
                $externalId = $result['external_id'] ?? null;
                $deliveryStatus = 'pending';
                $user->deductBalance($cost);
            } else {
                $messageStatus = 'failed';
                $deliveryStatus = 'failed';
                $errorMessage = $result['error'] ?? 'Unknown error';
                $this->campaign->increment('failed_count');
            }
        }

        // Create SMS message record
        SMSMessage::create([
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
     * Send SMS via QuickSMS
     */
    protected function sendSMS(string $phone, string $message, string $sender, User $user): array
    {
        try {
            $login = config('services.quicksms.login');
            $password = config('services.quicksms.password');

            if (!$login || !$password) {
                throw new \Exception('QuickSMS credentials not configured');
            }

            $url = 'https://www.poctgoyercini.com/api_http/sendsms.asp';
            $params = http_build_query([
                'user' => $login,
                'password' => $password,
                'gsm' => $phone,
                'text' => $message,
                'sender' => $sender,
            ]);

            $response = file_get_contents($url . '?' . $params);

            if ($response && strpos($response, 'OK') === 0) {
                $parts = explode(':', $response);
                $externalId = $parts[1] ?? null;

                return [
                    'success' => true,
                    'external_id' => $externalId,
                ];
            } else {
                Log::warning("QuickSMS send failed: {$response}");
                return [
                    'success' => false,
                    'error' => $response,
                ];
            }

        } catch (\Exception $e) {
            Log::error("SMS send error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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
