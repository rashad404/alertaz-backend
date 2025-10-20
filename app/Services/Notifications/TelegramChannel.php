<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel implements NotificationChannel
{
    private ?string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        $this->apiUrl = 'https://api.telegram.org/bot' . ($this->botToken ?? '');
    }

    /**
     * Send Telegram notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Telegram not configured',
            ];
        }

        // Format message for Telegram (supports Markdown)
        $telegramMessage = $this->formatMessage($message);

        // Mock mode for alert notifications
        if (config('app.notifications_mock')) {
            Log::info("✈️ [MOCK] Telegram notification to {$user->telegram_chat_id}:", [
                'alert' => $alert->name,
                'message' => $telegramMessage,
                'user_id' => $user->id,
            ]);

            return [
                'success' => true,
                'error' => null,
                'mocked' => true,
            ];
        }

        return $this->sendMessage($user->telegram_chat_id, $telegramMessage);
    }

    /**
     * Send test Telegram message.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Telegram not configured',
            ];
        }

        $testMessage = "🔔 *Test Notification*\n\n" . $this->formatMessage($message);

        return $this->sendMessage($user->telegram_chat_id, $testMessage);
    }

    /**
     * Check if Telegram is configured.
     */
    public function isConfigured(User $user): bool
    {
        return !empty($user->telegram_chat_id) && !empty($this->botToken);
    }

    /**
     * Send message via Telegram Bot API.
     */
    private function sendMessage(string $chatId, string $message): array
    {
        try {
            if (!$this->botToken) {
                throw new \Exception('Telegram bot token not configured');
            }

            $response = Http::post($this->apiUrl . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok'] ?? false) {
                    return [
                        'success' => true,
                        'error' => null,
                    ];
                }
            }

            $error = $response->json()['description'] ?? 'Failed to send Telegram message';
            throw new \Exception($error);
        } catch (\Exception $e) {
            Log::error("Telegram error: " . $e->getMessage());

            // If chat not found, clear the telegram_chat_id
            if (str_contains($e->getMessage(), 'chat not found')) {
                Log::warning("Clearing invalid Telegram chat ID for user");
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format message for Telegram.
     */
    private function formatMessage(string $message): string
    {
        // Telegram uses Markdown, but needs some adjustments
        // Bold: **text** -> *text*
        $message = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $message);

        // Escape special characters that might break Markdown
        $specialChars = ['_', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($specialChars as $char) {
            // Don't escape if it's part of markdown syntax we're using
            if ($char !== '*') {
                $message = str_replace($char, '\\' . $char, $message);
            }
        }

        // Limit message length (Telegram has a 4096 character limit)
        if (strlen($message) > 4000) {
            $message = substr($message, 0, 3997) . '...';
        }

        return $message;
    }

    /**
     * Send webhook info for user to connect Telegram.
     */
    public function getConnectionInstructions(): array
    {
        if (!$this->botToken) {
            return [
                'success' => false,
                'error' => 'Telegram bot not configured',
            ];
        }

        // Get bot info
        try {
            $response = Http::get($this->apiUrl . '/getMe');
            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok'] ?? false) {
                    $botUsername = $data['result']['username'] ?? 'alertaz_bot';

                    return [
                        'success' => true,
                        'bot_username' => $botUsername,
                        'instructions' => [
                            'step1' => "Open Telegram and search for @{$botUsername}",
                            'step2' => 'Start a conversation by clicking "Start" or sending /start',
                            'step3' => 'Send the command: /connect ' . bin2hex(random_bytes(8)),
                            'step4' => 'Copy the chat ID provided by the bot',
                            'step5' => 'Enter the chat ID in the field above',
                        ],
                        'deeplink' => "https://t.me/{$botUsername}?start=connect",
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to get Telegram bot info: " . $e->getMessage());
        }

        return [
            'success' => false,
            'error' => 'Failed to get bot information',
        ];
    }

    /**
     * Verify a chat ID is valid.
     */
    public function verifyChatId(string $chatId): bool
    {
        try {
            $response = Http::post($this->apiUrl . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => "✅ Your Telegram is now connected to Alert.az!\n\nYou will receive notifications here.",
                'parse_mode' => 'Markdown',
            ]);

            return $response->successful() && ($response->json()['ok'] ?? false);
        } catch (\Exception $e) {
            Log::error("Failed to verify Telegram chat ID: " . $e->getMessage());
            return false;
        }
    }
}