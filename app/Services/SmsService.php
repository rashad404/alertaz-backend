<?php

namespace App\Services;

use App\Models\User;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private QuickSmsService $quickSmsService;

    // SMS cost per message segment (in AZN or your currency)
    private float $costPerMessage;

    // Characters per segment (153 for Unicode, 160 for GSM-7)
    private int $charsPerSegment;

    public function __construct(QuickSmsService $quickSmsService)
    {
        $this->quickSmsService = $quickSmsService;
        $this->costPerMessage = (float) config('services.sms.cost_per_message', 0.04);
        $this->charsPerSegment = (int) config('services.sms.chars_per_segment', 153);
    }

    /**
     * Check if SMS test mode is enabled
     */
    public function isTestMode(): bool
    {
        return config('services.sms.test_mode', true);
    }

    /**
     * Send SMS with billing
     *
     * @param User $user - User to charge
     * @param string $phone - Recipient phone
     * @param string $message - SMS content
     * @param string $sender - Sender name (Alert.az, etc)
     * @param string $source - 'verification', 'alert', 'api', 'campaign'
     * @param int|null $clientId - Optional client ID for API/Campaign sources
     * @param int|null $campaignId - Optional campaign ID
     * @param int|null $customerId - Optional customer ID
     * @return array ['success' => bool, 'error' => string|null, 'error_code' => string|null, 'transaction_id' => string|null, 'cost' => float|null]
     */
    public function send(
        User $user,
        string $phone,
        string $message,
        string $sender = 'Alert.az',
        string $source = 'api',
        ?int $clientId = null,
        ?int $campaignId = null,
        ?int $customerId = null
    ): array {
        // Calculate cost
        $cost = $this->calculateCost($message);
        $isTest = $this->isTestMode();

        // In test mode, skip billing and actual sending
        if ($isTest) {
            Log::info("📱 [TEST MODE] SMS to {$phone}: {$message}", [
                'user_id' => $user->id,
                'source' => $source,
                'cost' => $cost,
                'sender' => $sender,
            ]);

            // Record message as test
            $smsMessage = $this->recordMessage(
                user: $user,
                phone: $phone,
                message: $message,
                sender: $sender,
                source: $source,
                cost: 0, // No cost in test mode
                isTest: true,
                clientId: $clientId,
                campaignId: $campaignId,
                customerId: $customerId
            );

            $smsMessage->markAsSent('test-' . uniqid());

            return [
                'success' => true,
                'message' => 'SMS sent successfully (test mode)',
                'transaction_id' => $smsMessage->provider_message_id,
                'cost' => 0,
                'is_test' => true,
                'debug' => [
                    'phone' => $phone,
                    'message' => $message,
                    'mode' => 'test',
                ],
            ];
        }

        // Production mode - check balance first
        if (!$user->hasEnoughBalance($cost)) {
            Log::warning("SMS send failed: insufficient balance", [
                'user_id' => $user->id,
                'balance' => $user->balance,
                'required' => $cost,
                'phone' => $phone,
            ]);

            return [
                'success' => false,
                'error' => 'Insufficient balance. Please add funds to send SMS.',
                'error_code' => 'insufficient_balance',
                'required_amount' => $cost,
                'current_balance' => (float) $user->balance,
            ];
        }

        // Record message before sending (status: pending)
        $smsMessage = $this->recordMessage(
            user: $user,
            phone: $phone,
            message: $message,
            sender: $sender,
            source: $source,
            cost: $cost,
            isTest: false,
            clientId: $clientId,
            campaignId: $campaignId,
            customerId: $customerId
        );

        // Send via QuickSMS
        $unicode = $this->quickSmsService->requiresUnicode($message);
        $result = $this->quickSmsService->sendSMS($phone, $message, $sender, $unicode);

        if ($result['success']) {
            // Deduct balance on success
            $user->deductBalance($cost);

            // Mark message as sent
            $smsMessage->markAsSent($result['transaction_id']);

            Log::info("📱 SMS sent successfully", [
                'user_id' => $user->id,
                'phone' => $phone,
                'source' => $source,
                'cost' => $cost,
                'transaction_id' => $result['transaction_id'],
            ]);

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'transaction_id' => $result['transaction_id'],
                'cost' => $cost,
                'new_balance' => (float) $user->fresh()->balance,
            ];
        }

        // Send failed - mark message as failed (no billing)
        $smsMessage->markAsFailed(
            $result['error_message'] ?? 'Unknown error',
            $result['error_code'] ?? null
        );

        Log::error("📱 SMS send failed", [
            'user_id' => $user->id,
            'phone' => $phone,
            'source' => $source,
            'error_code' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? null,
        ]);

        return [
            'success' => false,
            'error' => $result['error_message'] ?? 'Failed to send SMS',
            'error_code' => $result['error_code'] ?? null,
        ];
    }

    /**
     * Calculate SMS cost based on message length
     *
     * @param string $message
     * @return float
     */
    public function calculateCost(string $message): float
    {
        $segments = $this->calculateSegments($message);
        return round($segments * $this->costPerMessage, 2);
    }

    /**
     * Calculate number of SMS segments
     *
     * @param string $message
     * @return int
     */
    public function calculateSegments(string $message): int
    {
        $length = mb_strlen($message);

        if ($length === 0) {
            return 0;
        }

        // For Unicode (non-Latin characters), segment size is smaller
        $isUnicode = $this->quickSmsService->requiresUnicode($message);
        $segmentSize = $isUnicode ? 67 : 160; // First segment
        $segmentSizeConcat = $isUnicode ? 67 : 153; // Concatenated segments

        if ($length <= $segmentSize) {
            return 1;
        }

        // For multi-part messages, use concatenated segment size
        return (int) ceil($length / $segmentSizeConcat);
    }

    /**
     * Check if user has enough balance for SMS
     *
     * @param User $user
     * @param string $message
     * @return bool
     */
    public function hasEnoughBalance(User $user, string $message): bool
    {
        $cost = $this->calculateCost($message);
        return $user->hasEnoughBalance($cost);
    }

    /**
     * Get estimated cost for a message
     *
     * @param string $message
     * @return array
     */
    public function getEstimate(string $message): array
    {
        $segments = $this->calculateSegments($message);
        $cost = $this->calculateCost($message);

        return [
            'segments' => $segments,
            'cost' => $cost,
            'cost_per_segment' => $this->costPerMessage,
            'is_unicode' => $this->quickSmsService->requiresUnicode($message),
            'character_count' => mb_strlen($message),
        ];
    }

    /**
     * Record SMS message in database
     */
    private function recordMessage(
        User $user,
        string $phone,
        string $message,
        string $sender,
        string $source,
        float $cost,
        bool $isTest,
        ?int $clientId = null,
        ?int $campaignId = null,
        ?int $customerId = null
    ): Message {
        return Message::createSms([
            'client_id' => $clientId,
            'campaign_id' => $campaignId,
            'customer_id' => $customerId,
            'recipient' => $phone,
            'content' => $message,
            'sender' => $sender,
            'source' => $source,
            'cost' => $cost,
            'is_test' => $isTest,
            'status' => Message::STATUS_PENDING,
            'segments' => $this->calculateSegments($message),
        ]);
    }

    /**
     * Send bulk SMS with billing
     *
     * @param User $user
     * @param array $recipients - Array of ['phone' => string, 'message' => string]
     * @param string $sender
     * @param string $source
     * @param int|null $clientId
     * @param int|null $campaignId
     * @return array
     */
    public function sendBulk(
        User $user,
        array $recipients,
        string $sender = 'Alert.az',
        string $source = 'campaign',
        ?int $clientId = null,
        ?int $campaignId = null
    ): array {
        // Calculate total cost first
        $totalCost = 0;
        foreach ($recipients as $recipient) {
            $totalCost += $this->calculateCost($recipient['message']);
        }

        // Check balance for all messages
        if (!$this->isTestMode() && !$user->hasEnoughBalance($totalCost)) {
            return [
                'success' => false,
                'error' => 'Insufficient balance for bulk send',
                'error_code' => 'insufficient_balance',
                'required_amount' => $totalCost,
                'current_balance' => (float) $user->balance,
                'recipients_count' => count($recipients),
            ];
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $totalSpent = 0;

        foreach ($recipients as $recipient) {
            $result = $this->send(
                user: $user,
                phone: $recipient['phone'],
                message: $recipient['message'],
                sender: $sender,
                source: $source,
                clientId: $clientId,
                campaignId: $campaignId,
                customerId: $recipient['customer_id'] ?? null
            );

            $results[] = [
                'phone' => $recipient['phone'],
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ];

            if ($result['success']) {
                $successCount++;
                $totalSpent += $result['cost'] ?? 0;
            } else {
                $failedCount++;
            }
        }

        return [
            'success' => $failedCount === 0,
            'total_recipients' => count($recipients),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_cost' => $totalSpent,
            'new_balance' => (float) $user->fresh()->balance,
            'results' => $results,
        ];
    }

    /**
     * Get QuickSMS account balance (provider balance, not user balance)
     */
    public function getProviderBalance(): array
    {
        return $this->quickSmsService->checkBalance();
    }
}
