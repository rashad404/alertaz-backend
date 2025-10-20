<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannel
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        // WhatsApp Business API or Twilio WhatsApp
        $this->provider = config('services.whatsapp.provider', 'twilio');
        $this->config = config('services.whatsapp', []);
    }

    /**
     * Send WhatsApp notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'WhatsApp not configured',
            ];
        }

        // Format message for WhatsApp
        $whatsappMessage = $this->formatMessage($message);

        // Mock mode for alert notifications
        if (config('app.notifications_mock')) {
            Log::info("💬 [MOCK] WhatsApp notification to {$user->whatsapp_number}:", [
                'alert' => $alert->name,
                'message' => $whatsappMessage,
                'user_id' => $user->id,
            ]);

            return [
                'success' => true,
                'error' => null,
                'mocked' => true,
            ];
        }

        return $this->sendWhatsApp($user->whatsapp_number, $whatsappMessage);
    }

    /**
     * Send test WhatsApp message.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'WhatsApp not configured',
            ];
        }

        $testMessage = "🔔 *Test from Alert.az*\n\n" . $this->formatMessage($message);

        return $this->sendWhatsApp($user->whatsapp_number, $testMessage);
    }

    /**
     * Check if WhatsApp is configured.
     */
    public function isConfigured(User $user): bool
    {
        return !empty($user->whatsapp_number) && $this->isValidWhatsAppNumber($user->whatsapp_number);
    }

    /**
     * Send message via configured provider.
     */
    private function sendWhatsApp(string $number, string $message): array
    {
        switch ($this->provider) {
            case 'twilio':
                return $this->sendViaTwilio($number, $message);
            case 'whatsapp_business':
                return $this->sendViaWhatsAppBusiness($number, $message);
            case 'mock':
                return $this->sendViaMock($number, $message);
            default:
                return [
                    'success' => false,
                    'error' => 'WhatsApp provider not configured',
                ];
        }
    }

    /**
     * Send via Twilio WhatsApp.
     */
    private function sendViaTwilio(string $number, string $message): array
    {
        try {
            $accountSid = $this->config['twilio_sid'] ?? env('TWILIO_SID');
            $authToken = $this->config['twilio_token'] ?? env('TWILIO_TOKEN');
            $fromNumber = $this->config['twilio_whatsapp_from'] ?? env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');

            if (!$accountSid || !$authToken) {
                throw new \Exception('Twilio credentials not configured');
            }

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => 'whatsapp:' . $this->formatNumber($number),
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'error' => null,
                ];
            }

            $error = $response->json()['message'] ?? 'Failed to send WhatsApp message';
            throw new \Exception($error);
        } catch (\Exception $e) {
            Log::error("Twilio WhatsApp error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send via WhatsApp Business API.
     */
    private function sendViaWhatsAppBusiness(string $number, string $message): array
    {
        try {
            $apiUrl = $this->config['api_url'] ?? env('WHATSAPP_API_URL');
            $accessToken = $this->config['access_token'] ?? env('WHATSAPP_ACCESS_TOKEN');
            $phoneNumberId = $this->config['phone_number_id'] ?? env('WHATSAPP_PHONE_NUMBER_ID');

            if (!$apiUrl || !$accessToken || !$phoneNumberId) {
                throw new \Exception('WhatsApp Business API not configured');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$apiUrl}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatNumber($number),
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['messages'][0]['id'])) {
                    return [
                        'success' => true,
                        'error' => null,
                    ];
                }
            }

            $error = $response->json()['error']['message'] ?? 'Failed to send WhatsApp message';
            throw new \Exception($error);
        } catch (\Exception $e) {
            Log::error("WhatsApp Business API error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mock WhatsApp for development.
     */
    private function sendViaMock(string $number, string $message): array
    {
        Log::info("Mock WhatsApp to {$number}: {$message}");

        // Simulate success
        return [
            'success' => true,
            'error' => null,
        ];
    }

    /**
     * Format message for WhatsApp.
     */
    private function formatMessage(string $message): string
    {
        // WhatsApp supports basic formatting:
        // Bold: *text*
        // Italic: _text_
        // Strikethrough: ~text~
        // Monospace: ```text```

        // Convert markdown bold (**text**) to WhatsApp bold (*text*)
        $message = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $message);

        // Remove excessive newlines
        $message = preg_replace('/\n{3,}/', "\n\n", $message);

        // Limit message length (WhatsApp has limits but they're quite high)
        if (strlen($message) > 4000) {
            $message = substr($message, 0, 3997) . '...';
        }

        return trim($message);
    }

    /**
     * Format phone number for WhatsApp.
     */
    private function formatNumber(string $number): string
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Add Azerbaijan country code if not present
        if (strlen($number) === 9 && in_array(substr($number, 0, 2), ['50', '51', '55', '70', '77'])) {
            $number = '994' . $number;
        }

        // Ensure it starts with + for some providers
        if (!str_starts_with($number, '+')) {
            $number = '+' . $number;
        }

        return $number;
    }

    /**
     * Validate WhatsApp number format.
     */
    private function isValidWhatsAppNumber(string $number): bool
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        // Check if it's a valid phone number format
        if (strlen($number) >= 7 && strlen($number) <= 15) {
            return true;
        }

        return false;
    }

    /**
     * Get QR code for WhatsApp connection.
     */
    public function getConnectionQR(string $phoneNumber): string
    {
        $formattedNumber = $this->formatNumber($phoneNumber);
        $message = urlencode("Hi! I want to connect my Alert.az account to receive notifications.");

        // Generate WhatsApp deep link
        return "https://wa.me/{$formattedNumber}?text={$message}";
    }
}