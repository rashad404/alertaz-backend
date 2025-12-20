<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignContactLog;
use App\Models\SMSMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AutomatedCampaignRunner
{
    protected SegmentQueryBuilder $queryBuilder;
    protected TemplateRenderer $templateRenderer;
    protected float $smsPrice = 0.05;

    public function __construct(
        SegmentQueryBuilder $queryBuilder,
        TemplateRenderer $templateRenderer
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->templateRenderer = $templateRenderer;
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
                'sent' => 0,
                'skipped' => 0,
                'message' => 'Campaign ended',
            ];
        }

        // Get all matching contacts based on segment filter
        $allMatches = $this->queryBuilder->getMatches(
            $campaign->client_id,
            $campaign->segment_filter
        );

        // Filter out contacts in cooldown period
        $eligibleContacts = $this->filterCooldownContacts($allMatches, $campaign);

        if ($eligibleContacts->isEmpty()) {
            // Schedule next run even if no eligible contacts
            $campaign->scheduleNextRun();
            return [
                'campaign_id' => $campaign->id,
                'success' => true,
                'sent' => 0,
                'skipped' => $allMatches->count(),
                'message' => 'No eligible contacts (all in cooldown)',
            ];
        }

        // Check test mode
        $globalTestMode = config('services.quicksms.test_mode', false);
        $mockMode = $globalTestMode || $campaign->is_test;

        // Get user for balance
        $user = User::find($campaign->client->user_id);

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = $allMatches->count() - $eligibleContacts->count();

        foreach ($eligibleContacts as $contact) {
            try {
                // Render message
                $message = $this->templateRenderer->render(
                    $campaign->message_template,
                    $contact
                );
                $message = $this->templateRenderer->sanitizeForSMS($message);

                // Calculate cost
                $segments = $this->templateRenderer->calculateSMSSegments($message);
                $cost = $segments * $this->smsPrice;

                // Check balance
                if (!$mockMode && $user->balance < $cost) {
                    Log::warning("Insufficient balance for automated campaign {$campaign->id}. Pausing.");
                    $campaign->pause();
                    break;
                }

                // Send SMS
                $messageStatus = 'pending';
                $deliveryStatus = null;
                $externalId = null;

                if ($mockMode) {
                    $messageStatus = 'sent';
                    $deliveryStatus = 'delivered';
                } else {
                    $result = $this->sendSMS($contact->phone, $message, $campaign->sender, $user);

                    if ($result['success']) {
                        $messageStatus = 'sent';
                        $externalId = $result['external_id'] ?? null;
                        $deliveryStatus = 'pending';
                        $user->deductBalance($cost);
                    } else {
                        $messageStatus = 'failed';
                        $deliveryStatus = 'failed';
                        $failedCount++;
                    }
                }

                // Create SMS message record
                SMSMessage::create([
                    'user_id' => $campaign->created_by,
                    'source' => 'campaign',
                    'client_id' => $campaign->client_id,
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone,
                    'message' => $message,
                    'sender' => $campaign->sender,
                    'cost' => $cost,
                    'status' => $messageStatus,
                    'is_test' => $mockMode,
                    'provider_transaction_id' => $externalId,
                    'sent_at' => $messageStatus === 'sent' ? now() : null,
                    'delivered_at' => $deliveryStatus === 'delivered' ? now() : null,
                ]);

                if ($messageStatus === 'sent') {
                    $sentCount++;

                    // Record in contact log for deduplication
                    CampaignContactLog::recordSend($campaign->id, $contact->id);
                }

            } catch (\Exception $e) {
                Log::error("Failed to send automated message to {$contact->phone}: " . $e->getMessage());
                $failedCount++;
            }
        }

        // Update campaign stats
        $campaign->increment('sent_count', $sentCount);
        $campaign->increment('failed_count', $failedCount);

        // Schedule next run
        $campaign->scheduleNextRun();

        return [
            'campaign_id' => $campaign->id,
            'success' => true,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'skipped' => $skippedCount,
            'mock_mode' => $mockMode,
            'run_count' => $campaign->run_count,
        ];
    }

    /**
     * Filter out contacts that are in cooldown period
     *
     * @param Collection $contacts
     * @param Campaign $campaign
     * @return Collection
     */
    protected function filterCooldownContacts(Collection $contacts, Campaign $campaign): Collection
    {
        return $contacts->filter(function ($contact) use ($campaign) {
            return !CampaignContactLog::isInCooldown(
                $campaign->id,
                $contact->id,
                $campaign->cooldown_days
            );
        });
    }

    /**
     * Send SMS via QuickSMS
     *
     * @param string $phone
     * @param string $message
     * @param string $sender
     * @param User $user
     * @return array
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
}
