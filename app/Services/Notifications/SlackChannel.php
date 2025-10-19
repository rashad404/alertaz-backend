<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackChannel implements NotificationChannel
{
    /**
     * Send Slack notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Slack not configured',
            ];
        }

        // Format message for Slack
        $slackMessage = $this->formatMessage($message, $alert);

        return $this->sendToSlack($user->slack_webhook_url, $slackMessage, $alert->name);
    }

    /**
     * Send test Slack message.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Slack not configured',
            ];
        }

        $testPayload = [
            'text' => '🔔 Test Notification from Alert.az',
            'attachments' => [
                [
                    'color' => '#667eea',
                    'title' => 'Test Alert',
                    'text' => $this->cleanMessage($message),
                    'footer' => 'Alert.az',
                    'footer_icon' => 'https://alert.az/icon.png',
                    'ts' => time(),
                ],
            ],
        ];

        return $this->sendPayload($user->slack_webhook_url, $testPayload);
    }

    /**
     * Check if Slack is configured.
     */
    public function isConfigured(User $user): bool
    {
        return !empty($user->slack_webhook_url) &&
               filter_var($user->slack_webhook_url, FILTER_VALIDATE_URL);
    }

    /**
     * Send message to Slack webhook.
     */
    private function sendToSlack(string $webhookUrl, array $payload, string $alertName): array
    {
        return $this->sendPayload($webhookUrl, $payload);
    }

    /**
     * Send payload to Slack webhook.
     */
    private function sendPayload(string $webhookUrl, array $payload): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                // Slack returns 'ok' as plain text for successful webhook posts
                if ($response->body() === 'ok') {
                    return [
                        'success' => true,
                        'error' => null,
                    ];
                }
            }

            $error = $response->body() ?: 'Failed to send Slack notification';
            throw new \Exception($error);
        } catch (\Exception $e) {
            Log::error("Slack webhook error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format message for Slack with rich formatting.
     */
    private function formatMessage(string $message, PersonalAlert $alert): array
    {
        // Clean the message from markdown
        $cleanMessage = $this->cleanMessage($message);

        // Extract key information from message
        $lines = explode("\n", $cleanMessage);
        $title = $lines[0] ?? 'Alert Triggered';
        $details = implode("\n", array_slice($lines, 1));

        // Determine color based on alert type
        $color = $this->getColorForAlert($alert);

        // Build Slack message with blocks (modern format)
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "🔔 {$alert->name}",
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $this->convertToSlackMarkdown($message),
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => '*Alert Type:* ' . ($alert->alertType->name ?? 'Custom'),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => '*Triggered:* <!date^' . time() . '^{date_short_pretty} at {time}|' . now()->format('Y-m-d H:i:s') . '>',
                    ],
                ],
            ],
        ];

        // Add action buttons if applicable
        $blocks[] = [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'View Alert',
                        'emoji' => true,
                    ],
                    'url' => config('app.url') . '/alerts/' . $alert->id,
                    'action_id' => 'view_alert',
                ],
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'Manage Alerts',
                        'emoji' => true,
                    ],
                    'url' => config('app.url') . '/dashboard/alerts',
                    'action_id' => 'manage_alerts',
                ],
            ],
        ];

        return [
            'blocks' => $blocks,
            'attachments' => [
                [
                    'color' => $color,
                    'fallback' => $cleanMessage,
                ],
            ],
        ];
    }

    /**
     * Convert markdown to Slack's mrkdwn format.
     */
    private function convertToSlackMarkdown(string $message): string
    {
        // Bold: **text** -> *text*
        $message = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $message);

        // Italic: *text* -> _text_ (but we already use * for bold, so skip)

        // Links: [text](url) -> <url|text>
        $message = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<$2|$1>', $message);

        // Code: `code` -> `code` (same in Slack)

        // Lists: Already compatible

        // Limit length (Slack has a 3000 character limit for text fields)
        if (strlen($message) > 2900) {
            $message = substr($message, 0, 2897) . '...';
        }

        return $message;
    }

    /**
     * Clean message from markdown.
     */
    private function cleanMessage(string $message): string
    {
        // Remove markdown bold
        $message = preg_replace('/\*\*(.*?)\*\*/', '$1', $message);

        // Remove emojis for fallback text
        $message = preg_replace('/[\x{1F000}-\x{1F9FF}]/u', '', $message);

        return trim($message);
    }

    /**
     * Get color for alert based on type and conditions.
     */
    private function getColorForAlert(PersonalAlert $alert): string
    {
        $type = $alert->alertType->slug ?? '';

        switch ($type) {
            case 'crypto':
                return '#f7931a'; // Bitcoin orange
            case 'weather':
                return '#00a8e8'; // Sky blue
            case 'website':
                return '#ff4757'; // Red for down alerts
            case 'stock':
                return '#00b894'; // Green for stocks
            case 'currency':
                return '#6c5ce7'; // Purple for currency
            default:
                return '#667eea'; // Default Alert.az brand color
        }
    }

    /**
     * Generate OAuth URL for Slack app installation.
     */
    public function getOAuthUrl(string $userId): string
    {
        $clientId = config('services.slack.client_id', env('SLACK_CLIENT_ID'));
        $redirectUri = urlencode(config('app.url') . '/api/auth/slack/callback');
        $state = base64_encode(json_encode(['user_id' => $userId]));
        $scope = 'incoming-webhook,commands,chat:write';

        return "https://slack.com/oauth/v2/authorize?" .
               "client_id={$clientId}" .
               "&scope={$scope}" .
               "&redirect_uri={$redirectUri}" .
               "&state={$state}";
    }
}