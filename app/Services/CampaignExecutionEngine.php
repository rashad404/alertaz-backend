<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SmsMessage;
use App\Models\EmailMessage;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignExecutionEngine
{
    protected SegmentQueryBuilder $queryBuilder;
    protected TemplateRenderer $templateRenderer;
    protected ?EmailService $emailService = null;

    public function __construct(
        SegmentQueryBuilder $queryBuilder,
        TemplateRenderer $templateRenderer
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Get email service (lazy loaded)
     */
    protected function getEmailService(): EmailService
    {
        if ($this->emailService === null) {
            $this->emailService = app(EmailService::class);
        }
        return $this->emailService;
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
     * Execute campaign (send messages to all matching contacts based on channel)
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

            // SMS stats
            $smsSentCount = 0;
            $smsDeliveredCount = 0;
            $smsFailedCount = 0;
            $smsTotalCost = 0;

            // Email stats
            $emailSentCount = 0;
            $emailDeliveredCount = 0;
            $emailFailedCount = 0;
            $emailTotalCost = 0;

            // Get user for balance deduction
            $user = User::find($campaign->client->user_id);

            foreach ($contacts as $contact) {
                // Send SMS if required and contact has phone
                if ($campaign->requiresPhone() && $contact->canReceiveSms()) {
                    $smsResult = $this->sendSmsToContact($campaign, $contact, $user, $mockMode);
                    if ($smsResult['success']) {
                        $smsSentCount++;
                        $smsTotalCost += $smsResult['cost'];
                        if ($smsResult['delivered']) {
                            $smsDeliveredCount++;
                        }
                    } else {
                        $smsFailedCount++;
                    }
                    // Check balance after each send
                    if (!$mockMode && $smsResult['insufficient_balance']) {
                        Log::warning("Insufficient balance for campaign {$campaign->id}. Stopping SMS execution.");
                        break;
                    }
                }

                // Send Email if required and contact has email
                if ($campaign->requiresEmail() && $contact->canReceiveEmail()) {
                    $emailResult = $this->sendEmailToContact($campaign, $contact, $user, $mockMode);
                    if ($emailResult['success']) {
                        $emailSentCount++;
                        $emailTotalCost += $emailResult['cost'];
                        if ($emailResult['delivered']) {
                            $emailDeliveredCount++;
                        }
                    } else {
                        $emailFailedCount++;
                    }
                    // Check balance after each send
                    if (!$mockMode && $emailResult['insufficient_balance']) {
                        Log::warning("Insufficient balance for campaign {$campaign->id}. Stopping Email execution.");
                        break;
                    }
                }
            }

            // Update campaign stats
            $campaign->sent_count = $smsSentCount;
            $campaign->delivered_count = $smsDeliveredCount;
            $campaign->failed_count = $smsFailedCount;
            $campaign->total_cost = $smsTotalCost;
            $campaign->email_sent_count = $emailSentCount;
            $campaign->email_delivered_count = $emailDeliveredCount;
            $campaign->email_failed_count = $emailFailedCount;
            $campaign->email_total_cost = $emailTotalCost;

            // Mark campaign as completed
            $campaign->markAsCompleted();

            return [
                'success' => true,
                'sms_sent_count' => $smsSentCount,
                'sms_delivered_count' => $smsDeliveredCount,
                'sms_failed_count' => $smsFailedCount,
                'sms_total_cost' => $smsTotalCost,
                'email_sent_count' => $emailSentCount,
                'email_delivered_count' => $emailDeliveredCount,
                'email_failed_count' => $emailFailedCount,
                'email_total_cost' => $emailTotalCost,
                // Legacy fields for backwards compatibility
                'sent_count' => $smsSentCount + $emailSentCount,
                'delivered_count' => $smsDeliveredCount + $emailDeliveredCount,
                'failed_count' => $smsFailedCount + $emailFailedCount,
                'total_cost' => $smsTotalCost + $emailTotalCost,
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
     * Send SMS to a single contact
     *
     * @param Campaign $campaign
     * @param Contact $contact
     * @param User $user
     * @param bool $mockMode
     * @return array
     */
    protected function sendSmsToContact(Campaign $campaign, Contact $contact, User $user, bool $mockMode): array
    {
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
                return [
                    'success' => false,
                    'delivered' => false,
                    'cost' => 0,
                    'insufficient_balance' => true,
                ];
            }

            // Send SMS (or simulate in mock mode)
            $messageStatus = 'pending';
            $deliveryStatus = null;
            $externalId = null;
            $delivered = false;

            if ($mockMode) {
                // Mock mode: don't actually send, just simulate
                $messageStatus = 'sent';
                $deliveryStatus = 'delivered';
                $delivered = true;
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
                } else {
                    $messageStatus = 'failed';
                    $deliveryStatus = 'failed';
                    $cost = 0;
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

            return [
                'success' => $messageStatus === 'sent',
                'delivered' => $delivered,
                'cost' => $messageStatus === 'sent' ? $cost : 0,
                'insufficient_balance' => false,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send SMS to {$contact->phone}: " . $e->getMessage());

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

            return [
                'success' => false,
                'delivered' => false,
                'cost' => 0,
                'insufficient_balance' => false,
            ];
        }
    }

    /**
     * Send email to a single contact
     *
     * @param Campaign $campaign
     * @param Contact $contact
     * @param User $user
     * @param bool $mockMode
     * @return array
     */
    protected function sendEmailToContact(Campaign $campaign, Contact $contact, User $user, bool $mockMode): array
    {
        try {
            // Render email subject and body templates
            $subject = $this->templateRenderer->render(
                $campaign->email_subject_template ?? '',
                $contact
            );

            $body = $this->templateRenderer->render(
                $campaign->email_body_template ?? '',
                $contact
            );

            // Calculate cost
            $cost = config('app.email_cost_per_message', 0.01);

            // Check user balance
            if (!$mockMode && $user->balance < $cost) {
                return [
                    'success' => false,
                    'delivered' => false,
                    'cost' => 0,
                    'insufficient_balance' => true,
                ];
            }

            // Send email (or simulate in mock mode)
            $messageStatus = 'pending';
            $deliveryStatus = null;
            $externalId = null;
            $delivered = false;

            if ($mockMode) {
                // Mock mode: don't actually send, just simulate
                $messageStatus = 'sent';
                $deliveryStatus = 'delivered';
                $delivered = true;
            } else {
                // Real mode: send email
                $emailService = $this->getEmailService();
                $result = $emailService->send([
                    'to' => $contact->email,
                    'subject' => $subject,
                    'body_html' => $body,
                    'from_name' => $campaign->sender,
                ]);

                if ($result['success']) {
                    $messageStatus = 'sent';
                    $externalId = $result['message_id'] ?? null;
                    $deliveryStatus = 'pending';

                    // Deduct cost from user balance
                    $user->deductBalance($cost);
                } else {
                    $messageStatus = 'failed';
                    $deliveryStatus = 'failed';
                    $cost = 0;
                }
            }

            // Create email message record
            EmailMessage::create([
                'user_id' => $campaign->created_by,
                'source' => 'campaign',
                'client_id' => $campaign->client_id,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'to_email' => $contact->email,
                'subject' => $subject,
                'body_html' => $body,
                'from_name' => $campaign->sender,
                'cost' => $cost,
                'status' => $messageStatus,
                'is_test' => $mockMode,
                'provider_message_id' => $externalId,
                'sent_at' => $messageStatus === 'sent' ? now() : null,
                'delivered_at' => $deliveryStatus === 'delivered' ? now() : null,
            ]);

            return [
                'success' => $messageStatus === 'sent',
                'delivered' => $delivered,
                'cost' => $messageStatus === 'sent' ? $cost : 0,
                'insufficient_balance' => false,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send email to {$contact->email}: " . $e->getMessage());

            // Create failed email message record
            EmailMessage::create([
                'user_id' => $campaign->created_by,
                'source' => 'campaign',
                'client_id' => $campaign->client_id,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'to_email' => $contact->email,
                'subject' => '',
                'body_html' => '',
                'from_name' => $campaign->sender,
                'cost' => 0,
                'status' => 'failed',
                'is_test' => $mockMode,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'delivered' => false,
                'cost' => 0,
                'insufficient_balance' => false,
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

        // Validate SMS-specific fields
        if ($campaign->requiresPhone()) {
            // Check message template for SMS
            if (empty($campaign->message_template)) {
                $errors[] = 'SMS message template is required';
            }

            // Check sender
            if (empty($campaign->sender)) {
                $errors[] = 'Sender is required for SMS campaigns';
            }
        }

        // Validate Email-specific fields
        if ($campaign->requiresEmail()) {
            // Check email subject template
            if (empty($campaign->email_subject_template)) {
                $errors[] = 'Email subject template is required';
            }

            // Check email body template
            if (empty($campaign->email_body_template)) {
                $errors[] = 'Email body template is required';
            }
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

            // Validate SMS template variables
            if ($campaign->requiresPhone() && !empty($campaign->message_template)) {
                $undefinedVars = $this->templateRenderer->validateTemplate(
                    $campaign->message_template,
                    $availableKeys
                );

                if (!empty($undefinedVars)) {
                    $errors[] = 'SMS template uses undefined variables: ' . implode(', ', $undefinedVars);
                }
            }

            // Validate Email template variables
            if ($campaign->requiresEmail()) {
                if (!empty($campaign->email_subject_template)) {
                    $undefinedVars = $this->templateRenderer->validateTemplate(
                        $campaign->email_subject_template,
                        $availableKeys
                    );
                    if (!empty($undefinedVars)) {
                        $errors[] = 'Email subject uses undefined variables: ' . implode(', ', $undefinedVars);
                    }
                }

                if (!empty($campaign->email_body_template)) {
                    $undefinedVars = $this->templateRenderer->validateTemplate(
                        $campaign->email_body_template,
                        $availableKeys
                    );
                    if (!empty($undefinedVars)) {
                        $errors[] = 'Email body uses undefined variables: ' . implode(', ', $undefinedVars);
                    }
                }
            }
        }

        // Check user balance (skip for test campaigns)
        if (!$skipBalanceCheck) {
            $user = User::find($campaign->client->user_id);
            $costEstimate = $campaign->estimateTotalCost();

            if ($user->balance < $costEstimate['estimated_total_cost']) {
                $errors[] = "Insufficient balance. Required: {$costEstimate['estimated_total_cost']}, Available: {$user->balance}";
            }
        }

        return $errors;
    }

    /**
     * Get contacts planned for next campaign run (matching - cooldown)
     *
     * @param Campaign $campaign
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPlannedMessages(Campaign $campaign, int $page = 1, int $perPage = 10): array
    {
        $cooldownDays = $campaign->cooldown_days ?? 0;

        // Build query for matching contacts
        $query = $this->queryBuilder->getMatchesQuery(
            $campaign->client_id,
            $campaign->segment_filter
        );

        // Get contact IDs in cooldown for this campaign
        $cooldownContactIds = [];
        if ($cooldownDays > 0) {
            $cooldownContactIds = \App\Models\CampaignContactLog::where('campaign_id', $campaign->id)
                ->where('sent_at', '>', now()->subDays($cooldownDays))
                ->pluck('contact_id')
                ->toArray();
        }

        // Exclude contacts in cooldown
        if (!empty($cooldownContactIds)) {
            $query->whereNotIn('id', $cooldownContactIds);
        }

        // Get total count before pagination
        $totalPlanned = $query->count();

        // Paginate
        $offset = ($page - 1) * $perPage;
        $contacts = $query->skip($offset)->take($perPage)->get();

        // Render messages for this page
        $plannedContacts = [];
        foreach ($contacts as $contact) {
            $contactData = [
                'contact_id' => $contact->id,
                'phone' => $contact->phone,
                'email' => $contact->email ?? $contact->attributes['email'] ?? null,
                'message' => null,
                'email_subject' => null,
                'email_body' => null,
                'segments' => 0,
                'attributes' => $contact->attributes,
            ];

            // Render SMS message if channel is sms or both
            if ($campaign->requiresPhone() && $campaign->message_template) {
                $message = $this->templateRenderer->render(
                    $campaign->message_template,
                    $contact
                );
                $message = $this->templateRenderer->sanitizeForSMS($message);
                $contactData['message'] = $message;
                $contactData['segments'] = $this->templateRenderer->calculateSMSSegments($message);
            }

            // Render email if channel is email or both
            if ($campaign->requiresEmail()) {
                if ($campaign->email_subject_template) {
                    $contactData['email_subject'] = $this->templateRenderer->render(
                        $campaign->email_subject_template,
                        $contact
                    );
                }
                if ($campaign->email_body_template) {
                    $contactData['email_body'] = $this->templateRenderer->render(
                        $campaign->email_body_template,
                        $contact
                    );
                }
            }

            $plannedContacts[] = $contactData;
        }

        return [
            'contacts' => $plannedContacts,
            'pagination' => [
                'current_page' => $page,
                'last_page' => (int) ceil($totalPlanned / $perPage),
                'per_page' => $perPage,
                'total' => $totalPlanned,
            ],
            'next_run_at' => $campaign->next_run_at?->toIso8601String(),
        ];
    }

    /**
     * Preview campaign (show sample rendered messages)
     * Returns planned count (matching - cooldown) to show actual recipients
     *
     * @param Campaign $campaign
     * @param int $limit
     * @return array
     */
    public function previewMessages(Campaign $campaign, int $limit = 5): array
    {
        $cooldownDays = $campaign->cooldown_days ?? 0;

        // Build query for matching contacts
        $query = $this->queryBuilder->getMatchesQuery(
            $campaign->client_id,
            $campaign->segment_filter
        );

        // Get contact IDs in cooldown for this campaign
        $cooldownContactIds = [];
        if ($cooldownDays > 0) {
            $cooldownContactIds = \App\Models\CampaignContactLog::where('campaign_id', $campaign->id)
                ->where('sent_at', '>', now()->subDays($cooldownDays))
                ->pluck('contact_id')
                ->toArray();
        }

        // Exclude contacts in cooldown
        if (!empty($cooldownContactIds)) {
            $query->whereNotIn('id', $cooldownContactIds);
        }

        // Get total planned count (matching - cooldown)
        $totalCount = $query->count();

        // Get sample contacts for preview
        $contacts = $query->limit($limit)->get();

        $previews = [];

        foreach ($contacts as $contact) {
            $preview = [
                'phone' => $contact->phone,
                'email' => $contact->email,
                'attributes' => $contact->attributes,
                'can_receive_sms' => $contact->canReceiveSms(),
                'can_receive_email' => $contact->canReceiveEmail(),
            ];

            // Add SMS preview if campaign requires phone
            if ($campaign->requiresPhone() && !empty($campaign->message_template)) {
                $smsMessage = $this->templateRenderer->render(
                    $campaign->message_template,
                    $contact
                );
                $smsMessage = $this->templateRenderer->sanitizeForSMS($smsMessage);
                $preview['sms_message'] = $smsMessage;
                $preview['sms_segments'] = $this->templateRenderer->calculateSMSSegments($smsMessage);
                // Legacy field for backwards compatibility
                $preview['message'] = $smsMessage;
                $preview['segments'] = $preview['sms_segments'];
            }

            // Add Email preview if campaign requires email
            if ($campaign->requiresEmail()) {
                if (!empty($campaign->email_subject_template)) {
                    $preview['email_subject'] = $this->templateRenderer->render(
                        $campaign->email_subject_template,
                        $contact
                    );
                }
                if (!empty($campaign->email_body_template)) {
                    $preview['email_body'] = $this->templateRenderer->render(
                        $campaign->email_body_template,
                        $contact
                    );
                }
            }

            $previews[] = $preview;
        }

        return [
            'total_count' => $totalCount,
            'previews' => $previews,
            'channel' => $campaign->channel ?? 'sms',
        ];
    }

    /**
     * Count contacts eligible to receive messages for this campaign
     *
     * @param Campaign $campaign
     * @return array
     */
    public function countEligibleContacts(Campaign $campaign): array
    {
        $contacts = $this->queryBuilder->getMatches(
            $campaign->client_id,
            $campaign->segment_filter,
            PHP_INT_MAX
        );

        $totalMatching = $contacts->count();
        $withPhone = 0;
        $withEmail = 0;
        $withBoth = 0;

        foreach ($contacts as $contact) {
            $hasPhone = $contact->canReceiveSms();
            $hasEmail = $contact->canReceiveEmail();

            if ($hasPhone) $withPhone++;
            if ($hasEmail) $withEmail++;
            if ($hasPhone && $hasEmail) $withBoth++;
        }

        $eligibleForSms = $campaign->requiresPhone() ? $withPhone : 0;
        $eligibleForEmail = $campaign->requiresEmail() ? $withEmail : 0;

        return [
            'total_matching' => $totalMatching,
            'with_phone' => $withPhone,
            'with_email' => $withEmail,
            'with_both' => $withBoth,
            'eligible_for_sms' => $eligibleForSms,
            'eligible_for_email' => $eligibleForEmail,
        ];
    }
}
