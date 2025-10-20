<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSChannel implements NotificationChannel
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        // Configure SMS provider (Twilio, Nexmo, or local Azerbaijan provider)
        $this->provider = config('services.sms.provider', 'twilio');
        $this->config = config('services.sms', []);
    }

    /**
     * Send SMS notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
            ];
        }

        // Clean message for SMS (remove markdown)
        $smsMessage = $this->cleanMessage($message);

        // Truncate if too long (SMS limit is typically 160 chars)
        if (strlen($smsMessage) > 450) {
            $smsMessage = substr($smsMessage, 0, 447) . '...';
        }

        // Mock mode for alert notifications
        if (config('app.notifications_mock')) {
            Log::info("📱 [MOCK] SMS notification to {$user->phone}:", [
                'alert' => $alert->name,
                'message' => $smsMessage,
                'user_id' => $user->id,
            ]);

            return [
                'success' => true,
                'error' => null,
                'mocked' => true,
            ];
        }

        return $this->sendSMS($user->phone, $smsMessage);
    }

    /**
     * Send test SMS.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
            ];
        }

        $testMessage = "Alert.az Test: " . $this->cleanMessage($message);

        if (strlen($testMessage) > 160) {
            $testMessage = substr($testMessage, 0, 157) . '...';
        }

        return $this->sendSMS($user->phone, $testMessage);
    }

    /**
     * Check if SMS is configured.
     */
    public function isConfigured(User $user): bool
    {
        return !empty($user->phone) && $this->isValidPhone($user->phone);
    }

    /**
     * Send SMS using configured provider.
     */
    private function sendSMS(string $phone, string $message): array
    {
        switch ($this->provider) {
            case 'twilio':
                return $this->sendViaTwilio($phone, $message);
            case 'nexmo':
                return $this->sendViaNexmo($phone, $message);
            case 'azercell':
                return $this->sendViaAzercell($phone, $message);
            case 'mock':
                return $this->sendViaMock($phone, $message);
            default:
                return [
                    'success' => false,
                    'error' => 'SMS provider not configured',
                ];
        }
    }

    /**
     * Send via Twilio.
     */
    private function sendViaTwilio(string $phone, string $message): array
    {
        try {
            $accountSid = $this->config['twilio_sid'] ?? env('TWILIO_SID');
            $authToken = $this->config['twilio_token'] ?? env('TWILIO_TOKEN');
            $fromNumber = $this->config['twilio_from'] ?? env('TWILIO_FROM');

            if (!$accountSid || !$authToken || !$fromNumber) {
                throw new \Exception('Twilio credentials not configured');
            }

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $this->formatPhone($phone),
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'error' => null,
                ];
            }

            throw new \Exception($response->json()['message'] ?? 'Failed to send SMS');
        } catch (\Exception $e) {
            Log::error("Twilio SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send via Nexmo (Vonage).
     */
    private function sendViaNexmo(string $phone, string $message): array
    {
        try {
            $apiKey = $this->config['nexmo_key'] ?? env('NEXMO_KEY');
            $apiSecret = $this->config['nexmo_secret'] ?? env('NEXMO_SECRET');
            $fromNumber = $this->config['nexmo_from'] ?? env('NEXMO_FROM', 'Alert.az');

            if (!$apiKey || !$apiSecret) {
                throw new \Exception('Nexmo credentials not configured');
            }

            $response = Http::post('https://rest.nexmo.com/sms/json', [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'from' => $fromNumber,
                'to' => $this->formatPhone($phone),
                'text' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['messages'][0]['status']) && $data['messages'][0]['status'] == '0') {
                    return [
                        'success' => true,
                        'error' => null,
                    ];
                }
                throw new \Exception($data['messages'][0]['error-text'] ?? 'Failed to send SMS');
            }

            throw new \Exception('Failed to send SMS via Nexmo');
        } catch (\Exception $e) {
            Log::error("Nexmo SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send via Azercell (Azerbaijan local provider).
     */
    private function sendViaAzercell(string $phone, string $message): array
    {
        try {
            // This would integrate with Azercell's SMS API
            // Placeholder implementation

            $apiUrl = $this->config['azercell_api_url'] ?? env('AZERCELL_API_URL');
            $apiKey = $this->config['azercell_api_key'] ?? env('AZERCELL_API_KEY');

            if (!$apiUrl || !$apiKey) {
                throw new \Exception('Azercell API not configured');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->post($apiUrl, [
                'to' => $this->formatPhone($phone),
                'message' => $message,
                'sender' => 'Alert.az',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'error' => null,
                ];
            }

            throw new \Exception('Failed to send SMS via Azercell');
        } catch (\Exception $e) {
            Log::error("Azercell SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mock SMS for development.
     */
    private function sendViaMock(string $phone, string $message): array
    {
        Log::info("Mock SMS to {$phone}: {$message}");

        // Simulate random success/failure for testing
        if (rand(1, 10) > 1) { // 90% success rate
            return [
                'success' => true,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'error' => 'Mock SMS failure (simulated)',
        ];
    }

    /**
     * Clean message for SMS (remove markdown and special characters).
     */
    private function cleanMessage(string $message): string
    {
        // Remove markdown bold
        $message = preg_replace('/\*\*(.*?)\*\*/', '$1', $message);

        // Remove emojis (optional, some providers support them)
        // $message = preg_replace('/[\x{1F000}-\x{1F9FF}]/u', '', $message);

        // Replace multiple spaces and newlines
        $message = preg_replace('/\s+/', ' ', $message);

        // Trim
        return trim($message);
    }

    /**
     * Format phone number for international format.
     */
    private function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add Azerbaijan country code if not present
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 2), ['50', '51', '55', '70', '77'])) {
            $phone = '994' . $phone;
        }

        // Add + if not present
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

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