<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannel
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send SMS notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Phone number not configured or not verified',
            ];
        }

        // Clean message for SMS (remove markdown)
        $smsMessage = $this->cleanMessage($message);

        // Truncate if too long (max 3 SMS segments = ~450 chars)
        if (strlen($smsMessage) > 450) {
            $smsMessage = substr($smsMessage, 0, 447) . '...';
        }

        // Send via SmsService with billing
        $result = $this->smsService->send(
            user: $user,
            phone: $this->formatPhone($user->phone),
            message: $smsMessage,
            sender: 'Alert.az',
            source: 'alert'
        );

        if (!$result['success']) {
            // Log the error
            Log::warning("📱 SMS alert failed for user {$user->id}", [
                'alert' => $alert->name,
                'error' => $result['error'] ?? 'Unknown error',
                'error_code' => $result['error_code'] ?? null,
            ]);

            // Return appropriate error message
            if (($result['error_code'] ?? null) === 'insufficient_balance') {
                return [
                    'success' => false,
                    'error' => 'Insufficient balance to send SMS alert',
                    'error_code' => 'insufficient_balance',
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send SMS',
            ];
        }

        Log::info("📱 SMS alert sent to user {$user->id}", [
            'alert' => $alert->name,
            'cost' => $result['cost'] ?? 0,
            'transaction_id' => $result['transaction_id'] ?? null,
        ]);

        return [
            'success' => true,
            'error' => null,
            'cost' => $result['cost'] ?? 0,
            'is_test' => $result['is_test'] ?? false,
        ];
    }

    /**
     * Send test SMS.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Phone number not configured or not verified',
            ];
        }

        $testMessage = "Alert.az Test: " . $this->cleanMessage($message);

        if (strlen($testMessage) > 160) {
            $testMessage = substr($testMessage, 0, 157) . '...';
        }

        // Send via SmsService with billing
        $result = $this->smsService->send(
            user: $user,
            phone: $this->formatPhone($user->phone),
            message: $testMessage,
            sender: 'Alert.az',
            source: 'alert'
        );

        if (!$result['success']) {
            if (($result['error_code'] ?? null) === 'insufficient_balance') {
                return [
                    'success' => false,
                    'error' => 'Insufficient balance to send test SMS',
                    'error_code' => 'insufficient_balance',
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send test SMS',
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'cost' => $result['cost'] ?? 0,
        ];
    }

    /**
     * Check if SMS is configured and verified.
     */
    public function isConfigured(User $user): bool
    {
        // Phone must be set, verified, and valid format
        return !empty($user->phone)
            && $user->phone_verified_at !== null
            && $this->isValidPhone($user->phone);
    }

    /**
     * Clean message for SMS (remove markdown and special characters).
     */
    private function cleanMessage(string $message): string
    {
        // Remove markdown bold
        $message = preg_replace('/\*\*(.*?)\*\*/', '$1', $message);

        // Replace multiple spaces and newlines
        $message = preg_replace('/\s+/', ' ', $message);

        // Trim
        return trim($message);
    }

    /**
     * Format phone number for QuickSMS format (994XXXXXXXXX).
     */
    private function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add Azerbaijan country code if not present
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 2), ['50', '51', '55', '70', '77', '10', '60', '99'])) {
            $phone = '994' . $phone;
        }

        // Remove leading + if present (QuickSMS uses 994XXXXXXXXX format)
        $phone = ltrim($phone, '+');

        return $phone;
    }

    /**
     * Validate phone number format.
     */
    private function isValidPhone(string $phone): bool
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if it's a valid Azerbaijan mobile number
        if (strlen($phone) === 9) {
            $prefixes = ['50', '51', '55', '70', '77', '10', '60', '99'];
            return in_array(substr($phone, 0, 2), $prefixes);
        }

        // Check if it's already in international format
        if (strlen($phone) === 12 && str_starts_with($phone, '994')) {
            return true;
        }

        return false;
    }
}
