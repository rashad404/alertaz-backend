<?php

namespace App\Services;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Mail\AlertNotification;
use App\Services\Notifications\EmailChannel;
use App\Services\Notifications\SMSChannel;
use App\Services\Notifications\TelegramChannel;
use App\Services\Notifications\WhatsAppChannel;
use App\Services\Notifications\SlackChannel;
use App\Services\Notifications\PushChannel;

class NotificationDispatcher
{
    private array $channels = [];

    public function __construct()
    {
        // Initialize notification channels
        $this->channels = [
            'email' => new EmailChannel(),
            'sms' => new SMSChannel(),
            'telegram' => new TelegramChannel(),
            'whatsapp' => new WhatsAppChannel(),
            'slack' => new SlackChannel(),
            'push' => new PushChannel(),
        ];
    }

    /**
     * Dispatch notifications through all configured channels.
     *
     * @param User $user
     * @param array $channels List of channel names to use
     * @param string $message The message to send
     * @param PersonalAlert $alert The alert that triggered this notification
     * @param array $data Additional data for the notification
     * @return array Delivery status for each channel
     */
    public function dispatch(
        User $user,
        array $channels,
        string $message,
        PersonalAlert $alert,
        array $data = []
    ): array {
        $deliveryStatus = [];

        foreach ($channels as $channelName) {
            if (!isset($this->channels[$channelName])) {
                Log::warning("Unknown notification channel: {$channelName}");
                $deliveryStatus[$channelName] = [
                    'success' => false,
                    'error' => 'Channel not configured',
                ];
                continue;
            }

            // Check if user has this channel configured
            if (!$user->hasNotificationChannel($channelName)) {
                Log::info("User {$user->id} doesn't have {$channelName} configured");
                $deliveryStatus[$channelName] = [
                    'success' => false,
                    'error' => 'Channel not configured for user',
                ];
                continue;
            }

            try {
                $channel = $this->channels[$channelName];
                $result = $channel->send($user, $message, $alert, $data);

                $deliveryStatus[$channelName] = [
                    'success' => $result['success'],
                    'error' => $result['error'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                ];

                if ($result['success']) {
                    Log::info("Notification sent via {$channelName} to user {$user->id}");
                } else {
                    Log::error("Failed to send {$channelName} notification to user {$user->id}: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                Log::error("Exception sending {$channelName} notification: " . $e->getMessage());
                $deliveryStatus[$channelName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        return $deliveryStatus;
    }

    /**
     * Test a specific notification channel.
     */
    public function testChannel(User $user, string $channelName): array
    {
        if (!isset($this->channels[$channelName])) {
            return [
                'success' => false,
                'error' => 'Channel not configured',
            ];
        }

        $testMessage = "🔔 Test notification from Alert.az\n\n";
        $testMessage .= "This is a test message to verify your {$channelName} notifications are working correctly.\n";
        $testMessage .= "Time: " . now()->format('Y-m-d H:i:s') . " (Asia/Baku)";

        try {
            $channel = $this->channels[$channelName];
            return $channel->sendTest($user, $testMessage);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available notification channels.
     */
    public function getAvailableChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Check if a channel is available.
     */
    public function hasChannel(string $channelName): bool
    {
        return isset($this->channels[$channelName]);
    }
}