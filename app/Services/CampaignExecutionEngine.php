<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SmsMessage;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignExecutionEngine
{
    protected SegmentQueryBuilder $queryBuilder;
    protected TemplateRenderer $templateRenderer;

    public function __construct(
        SegmentQueryBuilder $queryBuilder,
        TemplateRenderer $templateRenderer
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Check if global test mode is enabled
     *
     * @return bool
     */
    protected function isGlobalTestMode(): bool
    {
        return config('services.quicksms.test_mode', false);
    }

    /**
     * Execute campaign (send SMS to all matching contacts)
     *
     * @param Campaign $campaign
     * @param bool $mockMode
     * @return array
     */
    public function execute(Campaign $campaign, bool $mockMode = false): array
    {
        // Check global test mode - overrides everything
        $globalTestMode = $this->isGlobalTestMode();
        if ($globalTestMode) {
            $mockMode = true;
        }

        // Validate campaign can be executed
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            throw new \Exception('Campaign cannot be executed. Status: ' . $campaign->status);
        }

        // Mark campaign as sending
        $campaign->markAsSending();

        try {
            // Get all matching contacts
            $contacts = $this->queryBuilder->getMatches(
                $campaign->client_id,
                $campaign->segment_filter,
                $campaign->target_count
            );

            $sentCount = 0;
            $deliveredCount = 0;
            $failedCount = 0;
            $totalCost = 0;

            // Get user for balance deduction
            $user = User::find($campaign->client->user_id);

            foreach ($contacts as $contact) {
                try {
                    // Render message template
                    $message = $this->templateRenderer->render(
                        $campaign->message_template,
                        $contact
                    );

                    // Sanitize message
                    $message = $this->templateRenderer->sanitizeForSMS($message);

                    // Calculate cost
                    $segments = $this->templateRenderer->calculateSMSSegments($message);
                    $cost = $segments * config('app.sms_cost_per_message', 0.04);

                    // Check user balance
                    if (!$mockMode && $user->balance < $cost) {
                        Log::warning("Insufficient balance for campaign {$campaign->id}. Stopping execution.");
                        break;
                    }

                    // Send SMS (or simulate in mock mode)
                    $messageStatus = 'pending';
                    $deliveryStatus = null;
                    $externalId = null;

                    if ($mockMode) {
                        // Mock mode: don't actually send, just simulate
                        $messageStatus = 'sent';
                        $deliveryStatus = 'delivered';
                        $deliveredCount++;
                    } else {
                        // Real mode: send SMS via QuickSMS
                        $result = $this->sendSMS(
                            $contact->phone,
                            $message,
                            $campaign->sender,
                            $user
                        );

                        if ($result['success']) {
                            $messageStatus = 'sent';
                            $externalId = $result['external_id'] ?? null;
                            $deliveryStatus = 'pending';

                            // Deduct cost from user balance
                            $user->deductBalance($cost);
                            $totalCost += $cost;
                        } else {
                            $messageStatus = 'failed';
                            $deliveryStatus = 'failed';
                            $failedCount++;
                        }
                    }

                    // Create SMS message record
                    SmsMessage::create([
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
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to send message to {$contact->phone}: " . $e->getMessage());
                    $failedCount++;

                    // Create failed message record
                    SmsMessage::create([
                        'user_id' => $campaign->created_by,
                        'source' => 'campaign',
                        'client_id' => $campaign->client_id,
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'phone' => $contact->phone,
                        'message' => '',
                        'sender' => $campaign->sender,
                        'cost' => 0,
                        'status' => 'failed',
                        'is_test' => $mockMode,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            // Update campaign stats
            $campaign->sent_count = $sentCount;
            $campaign->delivered_count = $deliveredCount;
            $campaign->failed_count = $failedCount;
            $campaign->total_cost = $totalCost;

            // Mark campaign as completed
            $campaign->markAsCompleted();

            return [
                'success' => true,
                'sent_count' => $sentCount,
                'delivered_count' => $deliveredCount,
                'failed_count' => $failedCount,
                'total_cost' => $totalCost,
                'mock_mode' => $mockMode,
                'global_test_mode' => $globalTestMode,
            ];

        } catch (\Exception $e) {
            // Mark campaign as failed
            $campaign->markAsFailed();

            Log::error("Campaign {$campaign->id} execution failed: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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
            // QuickSMS API credentials
            $login = config('services.quicksms.login');
            $password = config('services.quicksms.password');

            if (!$login || !$password) {
                throw new \Exception('QuickSMS credentials not configured');
            }

            // Send SMS
            $url = 'https://www.poctgoyercini.com/api_http/sendsms.asp';
            $params = http_build_query([
                'user' => $login,
                'password' => $password,
                'gsm' => $phone,
                'text' => $message,
                'sender' => $sender,
            ]);

            $response = file_get_contents($url . '?' . $params);

            // Parse response
            if ($response && strpos($response, 'OK') === 0) {
                // Extract message ID
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
     * Execute campaign in test/mock mode
     *
     * @param Campaign $campaign
     * @return array
     */
    public function executeTest(Campaign $campaign): array
    {
        return $this->execute($campaign, true);
    }

    /**
     * Validate campaign before execution
     *
     * @param Campaign $campaign
     * @param bool $skipBalanceCheck Skip balance check for test campaigns
     * @return array Returns validation errors, empty if valid
     */
    public function validateCampaign(Campaign $campaign, bool $skipBalanceCheck = false): array
    {
        $errors = [];

        // Check campaign status
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            $errors[] = 'Campaign status must be draft or scheduled';
        }

        // Check target count
        if ($campaign->target_count === 0) {
            $errors[] = 'No contacts match the segment filter';
        }

        // Check message template
        if (empty($campaign->message_template)) {
            $errors[] = 'Message template is required';
        }

        // Check sender
        if (empty($campaign->sender)) {
            $errors[] = 'Sender is required';
        }

        // Validate template variables
        $contacts = $this->queryBuilder->getMatches(
            $campaign->client_id,
            $campaign->segment_filter,
            1
        );

        if ($contacts->isNotEmpty()) {
            $sampleContact = $contacts->first();
            $availableKeys = array_keys($sampleContact->attributes ?? []);
            $undefinedVars = $this->templateRenderer->validateTemplate(
                $campaign->message_template,
                $availableKeys
            );

            if (!empty($undefinedVars)) {
                $errors[] = 'Template uses undefined variables: ' . implode(', ', $undefinedVars);
            }
        }

        // Check user balance (skip for test campaigns)
        if (!$skipBalanceCheck) {
            $user = User::find($campaign->client->user_id);
            $estimate = $this->templateRenderer->estimateCost(
                $campaign->message_template,
                $campaign->target_count
            );

            if ($user->balance < $estimate['estimated_cost']) {
                $errors[] = "Insufficient balance. Required: {$estimate['estimated_cost']}, Available: {$user->balance}";
            }
        }

        return $errors;
    }

    /**
     * Preview campaign (show sample rendered messages)
     *
     * @param Campaign $campaign
     * @param int $limit
     * @return array
     */
    public function previewMessages(Campaign $campaign, int $limit = 5): array
    {
        // Get fresh total count
        $totalCount = $this->queryBuilder->countMatches(
            $campaign->client_id,
            $campaign->segment_filter
        );

        $contacts = $this->queryBuilder->getMatches(
            $campaign->client_id,
            $campaign->segment_filter,
            $limit
        );

        $previews = [];

        foreach ($contacts as $contact) {
            $message = $this->templateRenderer->render(
                $campaign->message_template,
                $contact
            );

            $message = $this->templateRenderer->sanitizeForSMS($message);

            $previews[] = [
                'phone' => $contact->phone,
                'message' => $message,
                'segments' => $this->templateRenderer->calculateSMSSegments($message),
                'attributes' => $contact->attributes,
            ];
        }

        return [
            'total_count' => $totalCount,
            'previews' => $previews,
        ];
    }
}
