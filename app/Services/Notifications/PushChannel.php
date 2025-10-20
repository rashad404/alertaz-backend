<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushChannel implements NotificationChannel
{
    private ?WebPush $webPush = null;

    public function __construct()
    {
        // Initialize WebPush with VAPID keys
        $auth = [
            'VAPID' => [
                'subject' => config('app.url', 'https://alert.az'),
                'publicKey' => config('services.push.public_key', env('VAPID_PUBLIC_KEY')),
                'privateKey' => config('services.push.private_key', env('VAPID_PRIVATE_KEY')),
            ],
        ];

        try {
            if ($auth['VAPID']['publicKey'] && $auth['VAPID']['privateKey']) {
                $this->webPush = new WebPush($auth);
            }
        } catch (\Exception $e) {
            Log::error("Failed to initialize WebPush: " . $e->getMessage());
        }
    }

    /**
     * Send push notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Push notifications not configured',
            ];
        }

        // Mock mode for alert notifications
        if (config('app.notifications_mock')) {
            Log::info("🔔 [MOCK] Push notification to user {$user->id}:", [
                'alert' => $alert->name,
                'message' => $message,
                'user_id' => $user->id,
            ]);

            return [
                'success' => true,
                'error' => null,
                'mocked' => true,
            ];
        }

        // Format notification payload
        $payload = $this->formatPayload($message, $alert, $data);

        return $this->sendPushNotification($user->push_token, $payload);
    }

    /**
     * Send test push notification.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Push notifications not configured',
            ];
        }

        $payload = [
            'title' => '🔔 Test Notification',
            'body' => $this->cleanMessage($message),
            'icon' => '/icon-192.png',
            'badge' => '/badge-72.png',
            'tag' => 'test-' . time(),
            'data' => [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        return $this->sendPushNotification($user->push_token, $payload);
    }

    /**
     * Check if push notifications are configured.
     */
    public function isConfigured(User $user): bool
    {
        return !empty($user->push_token) && $this->webPush !== null;
    }

    /**
     * Send push notification via WebPush.
     */
    private function sendPushNotification(string $pushToken, array $payload): array
    {
        if (!$this->webPush) {
            return [
                'success' => false,
                'error' => 'WebPush not configured',
            ];
        }

        try {
            // Parse the subscription from the stored token
            $subscription = $this->parseSubscription($pushToken);

            if (!$subscription) {
                throw new \Exception('Invalid push subscription token');
            }

            // Queue the notification
            $report = $this->webPush->sendOneNotification(
                $subscription,
                json_encode($payload),
                ['TTL' => 86400] // 24 hours
            );

            // Check if successful
            if ($report->isSuccess()) {
                return [
                    'success' => true,
                    'error' => null,
                ];
            }

            // Handle failure
            $endpoint = $report->getEndpoint();
            $reason = $report->getReason();
            $statusCode = $report->getStatusCode();

            // If subscription is invalid (410 Gone), we should remove it
            if ($statusCode === 410) {
                Log::warning("Push subscription expired for endpoint: {$endpoint}");
                // You might want to clear the user's push_token here
            }

            throw new \Exception("Push failed: {$reason} (Status: {$statusCode})");
        } catch (\Exception $e) {
            Log::error("Push notification error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse subscription from stored token.
     */
    private function parseSubscription(string $token): ?Subscription
    {
        try {
            $data = json_decode($token, true);

            if (!$data || !isset($data['endpoint'])) {
                // Try to handle it as a simple endpoint string
                if (filter_var($token, FILTER_VALIDATE_URL)) {
                    return Subscription::create([
                        'endpoint' => $token,
                    ]);
                }
                return null;
            }

            return Subscription::create([
                'endpoint' => $data['endpoint'],
                'keys' => $data['keys'] ?? [],
                'contentEncoding' => $data['contentEncoding'] ?? 'aesgcm',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to parse push subscription: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Format notification payload.
     */
    private function formatPayload(string $message, PersonalAlert $alert, array $data = []): array
    {
        // Clean and format the message
        $cleanedMessage = $this->cleanMessage($message);
        $lines = explode("\n", $cleanedMessage);
        $title = $lines[0] ?? 'Alert Triggered';
        $body = implode("\n", array_slice($lines, 1, 3)); // Take first 3 lines for body

        // Determine icon based on alert type
        $icon = $this->getIconForAlert($alert);

        return [
            'title' => $title,
            'body' => $body ?: 'Your alert condition has been met',
            'icon' => $icon,
            'badge' => '/badge-72.png',
            'tag' => 'alert-' . $alert->id . '-' . time(),
            'renotify' => true,
            'requireInteraction' => false,
            'silent' => false,
            'timestamp' => time() * 1000,
            'vibrate' => [200, 100, 200],
            'actions' => [
                [
                    'action' => 'view',
                    'title' => 'View Details',
                    'icon' => '/icons/view.png',
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Dismiss',
                    'icon' => '/icons/dismiss.png',
                ],
            ],
            'data' => array_merge([
                'alertId' => $alert->id,
                'alertType' => $alert->alertType->slug ?? 'custom',
                'url' => config('app.url') . '/dashboard/alerts/' . $alert->id,
                'timestamp' => now()->toIso8601String(),
            ], $data),
        ];
    }

    /**
     * Get icon for alert type.
     */
    private function getIconForAlert(PersonalAlert $alert): string
    {
        $type = $alert->alertType->slug ?? '';

        $icons = [
            'crypto' => '/icons/crypto.png',
            'weather' => '/icons/weather.png',
            'website' => '/icons/website.png',
            'stock' => '/icons/stock.png',
            'currency' => '/icons/currency.png',
        ];

        return $icons[$type] ?? '/icon-192.png';
    }

    /**
     * Clean message from markdown and emojis.
     */
    private function cleanMessage(string $message): string
    {
        // Remove markdown bold
        $message = preg_replace('/\*\*(.*?)\*\*/', '$1', $message);

        // Keep emojis for push notifications (they're supported)

        // Limit length
        if (strlen($message) > 500) {
            $message = substr($message, 0, 497) . '...';
        }

        return trim($message);
    }

    /**
     * Generate VAPID keys for push notifications.
     */
    public static function generateVAPIDKeys(): array
    {
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

            return [
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ];
        } catch (\Exception $e) {
            Log::error("Failed to generate VAPID keys: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get public key for client-side subscription.
     */
    public function getPublicKey(): ?string
    {
        return config('services.push.public_key', env('VAPID_PUBLIC_KEY'));
    }
}