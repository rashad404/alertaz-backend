<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use App\Models\PushSubscription;
use App\Models\NotificationLog;
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

        // Parse message to extract type key and data for NotificationLog
        // Monitors may send JSON (WebsiteMonitor) or plain string (CryptoMonitor)
        $parsedMessage = json_decode($message, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($parsedMessage['type'])) {
            // JSON format: {"type": "website_down", "url": "...", ...}
            $typeKey = $parsedMessage['type'];
            $messageData = $parsedMessage;
        } else {
            // Plain string format: "crypto_target_reached"
            $typeKey = $message;
            $messageData = [];
        }

        // Merge message data with additional data for frontend translation
        $notificationData = array_merge($messageData, $data, [
            'alertId' => $alert->id,
            'alertType' => $alert->alertType->slug ?? 'unknown',
            'alertName' => $alert->name,
            'asset' => $alert->asset,
        ]);

        // Mock mode for alert notifications
        $isMockMode = config('app.notifications_mock', env('NOTIFICATIONS_MOCK_MODE', false));

        if ($isMockMode) {
            Log::info("🔔 [MOCK] Push notification to user {$user->id}:", [
                'alert' => $alert->name,
                'type' => $typeKey,
                'user_id' => $user->id,
            ]);

            // Save type key + data (frontend will translate based on user's language)
            NotificationLog::create([
                'user_id' => $user->id,
                'type' => 'push',
                'title' => $typeKey,
                'body' => null,
                'data' => $notificationData,
                'is_mock' => true,
                'is_read' => false,
            ]);

            return [
                'success' => true,
                'error' => null,
                'mocked' => true,
            ];
        }

        // Format payload for actual browser push notification (needs formatted text)
        $payload = $this->formatPayload($message, $alert, $data);

        // Real mode: send to all user's push subscriptions
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        $sentCount = 0;
        $errors = [];

        foreach ($subscriptions as $sub) {
            Log::info("Sending push to subscription {$sub->id}", [
                'endpoint' => substr($sub->endpoint, 0, 50) . '...',
                'user_id' => $user->id,
            ]);

            $result = $this->sendToSubscription($sub, $payload);

            Log::info("Push result for subscription {$sub->id}", [
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ]);

            if ($result['success']) {
                $sentCount++;
            } else {
                $errors[] = $result['error'];

                // Remove expired subscription
                if (str_contains($result['error'] ?? '', '410') || str_contains($result['error'] ?? '', 'expired')) {
                    $sub->delete();
                }
            }
        }

        // Save type key + data (frontend will translate based on user's language)
        NotificationLog::create([
            'user_id' => $user->id,
            'type' => 'push',
            'title' => $typeKey,
            'body' => null,
            'data' => array_merge($notificationData, [
                'sent_count' => $sentCount,
                'total_subscriptions' => $subscriptions->count(),
            ]),
            'is_mock' => false,
            'is_read' => false,
        ]);

        if ($sentCount === 0) {
            return [
                'success' => false,
                'error' => 'Failed to send to any subscription: ' . implode(', ', $errors),
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'sent_count' => $sentCount,
        ];
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
        return PushSubscription::where('user_id', $user->id)->exists() && $this->webPush !== null;
    }

    /**
     * Send notification to a specific subscription.
     */
    private function sendToSubscription(PushSubscription $pushSub, array $payload): array
    {
        if (!$this->webPush) {
            return [
                'success' => false,
                'error' => 'WebPush not configured',
            ];
        }

        try {
            // Create subscription object from database record
            // Don't specify contentEncoding - let the library auto-detect
            $subscription = Subscription::create([
                'endpoint' => $pushSub->endpoint,
                'keys' => [
                    'p256dh' => $pushSub->public_key,
                    'auth' => $pushSub->auth_token,
                ],
            ]);

            // Send the notification
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

            // Get status code - handle different library versions
            $statusCode = null;
            if (method_exists($report, 'getStatusCode')) {
                $statusCode = $report->getStatusCode();
            } elseif (method_exists($report, 'getResponse') && $report->getResponse()) {
                $statusCode = $report->getResponse()->getStatusCode();
            }

            // If subscription is invalid (410 Gone), mark for removal
            if ($statusCode === 410) {
                Log::warning("Push subscription expired for endpoint: {$endpoint}");
            }

            return [
                'success' => false,
                'error' => "Push failed: {$reason}" . ($statusCode ? " (Status: {$statusCode})" : ''),
            ];
        } catch (\Exception $e) {
            Log::error("Push notification error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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
        // Try to parse JSON message (format from monitors)
        $parsedData = json_decode($message, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['type'])) {
            // Use parsed data to create human-readable message
            $formatted = $this->formatAlertMessage($parsedData, $alert);
            $title = $formatted['title'];
            $body = $formatted['body'];
        } else {
            // Fallback for plain text messages
            $cleanedMessage = $this->cleanMessage($message);
            $lines = explode("\n", $cleanedMessage);
            $title = $lines[0] ?? 'Alert Triggered';
            $body = implode("\n", array_slice($lines, 1, 3));
        }

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
            'data' => array_merge($data, [
                'alertId' => $alert->id,
                'alertType' => $alert->alertType->slug ?? 'custom',
                'clickUrl' => config('app.frontend_url', config('app.url')) . '/alerts',
                'timestamp' => now()->toIso8601String(),
            ]),
        ];
    }

    /**
     * Format alert message based on type.
     */
    private function formatAlertMessage(array $data, PersonalAlert $alert): array
    {
        $type = $data['type'] ?? 'alert';
        $alertName = $alert->name;

        // Website alerts
        if (str_starts_with($type, 'website_')) {
            $url = $data['url'] ?? $alert->asset ?? 'Unknown';
            // Extract domain for cleaner display
            $domain = parse_url($url, PHP_URL_HOST) ?? $url;

            if ($type === 'website_down') {
                $statusCode = $data['status_code'] ?? null;
                $error = $data['error'] ?? null;

                // Build informative body message
                if ($error) {
                    $body = $error;
                } elseif ($statusCode) {
                    $body = "HTTP {$statusCode} error";
                } else {
                    $body = "Website is not responding";
                }

                return [
                    'title' => "Website Down: {$domain}",
                    'body' => $body,
                ];
            } else {
                $responseTime = $data['response_time'] ?? 0;
                return [
                    'title' => "Website Online: {$domain}",
                    'body' => "Response time: {$responseTime}ms",
                ];
            }
        }

        // Crypto alerts
        if (str_starts_with($type, 'crypto_')) {
            $asset = strtoupper($data['asset'] ?? $data['symbol'] ?? $alert->asset ?? 'UNKNOWN');
            $price = $data['price'] ?? $data['current_price'] ?? 0;
            $formattedPrice = number_format($price, 2);

            if ($type === 'crypto_above') {
                return [
                    'title' => "{$asset} Price Alert",
                    'body' => "Price rose above \${$formattedPrice}",
                ];
            } elseif ($type === 'crypto_below') {
                return [
                    'title' => "{$asset} Price Alert",
                    'body' => "Price dropped below \${$formattedPrice}",
                ];
            } else {
                return [
                    'title' => "{$asset} Price Alert",
                    'body' => "Current price: \${$formattedPrice}",
                ];
            }
        }

        // Stock alerts
        if (str_starts_with($type, 'stock_')) {
            $symbol = strtoupper($data['symbol'] ?? $alert->asset ?? 'UNKNOWN');
            $price = $data['price'] ?? 0;
            $formattedPrice = number_format($price, 2);

            if ($type === 'stock_above') {
                return [
                    'title' => "{$symbol} Stock Alert",
                    'body' => "Price rose above \${$formattedPrice}",
                ];
            } elseif ($type === 'stock_below') {
                return [
                    'title' => "{$symbol} Stock Alert",
                    'body' => "Price dropped below \${$formattedPrice}",
                ];
            } else {
                return [
                    'title' => "{$symbol} Stock Alert",
                    'body' => "Current price: \${$formattedPrice}",
                ];
            }
        }

        // Currency alerts
        if (str_starts_with($type, 'currency_')) {
            $pair = strtoupper($data['pair'] ?? $data['asset'] ?? $alert->asset ?? 'USD/AZN');
            $rate = $data['rate'] ?? $data['price'] ?? 0;

            return [
                'title' => "{$pair} Rate Alert",
                'body' => "Current rate: {$rate}",
            ];
        }

        // Weather alerts
        if (str_starts_with($type, 'weather_')) {
            $location = $data['location'] ?? $alert->asset ?? 'Unknown';
            $condition = $data['condition'] ?? $data['description'] ?? '';
            $temp = $data['temperature'] ?? $data['temp'] ?? null;

            $body = $condition;
            if ($temp !== null) {
                $body = "Temperature: {$temp}°C" . ($condition ? " - {$condition}" : '');
            }

            return [
                'title' => "Weather Alert: {$location}",
                'body' => $body ?: 'Weather condition changed',
            ];
        }

        // Default fallback - use alert name
        return [
            'title' => $alertName,
            'body' => 'Alert condition met',
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