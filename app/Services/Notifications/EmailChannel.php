<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailChannel implements NotificationChannel
{
    /**
     * Send email notification.
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Email not configured',
            ];
        }

        // Mock mode - log instead of sending
        if (config('app.notifications_mock')) {
            Log::info("📧 [MOCK] Email notification to {$user->email}:", [
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

        try {
            // Convert markdown-style message to HTML
            $htmlMessage = $this->markdownToHtml($message);

            Mail::send([], [], function ($mail) use ($user, $alert, $htmlMessage, $message) {
                $mail->to($user->email)
                    ->subject("Alert: {$alert->name}")
                    ->html($htmlMessage)
                    ->text($message);
            });

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to send email to {$user->email}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send test email.
     */
    public function sendTest(User $user, string $message): array
    {
        if (!$this->isConfigured($user)) {
            return [
                'success' => false,
                'error' => 'Email not configured',
            ];
        }

        try {
            $htmlMessage = $this->markdownToHtml($message);

            Mail::send([], [], function ($mail) use ($user, $htmlMessage, $message) {
                $mail->to($user->email)
                    ->subject('Test Notification - Alert.az')
                    ->html($htmlMessage)
                    ->text($message);
            });

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if email is configured.
     */
    public function isConfigured(User $user): bool
    {
        return !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Convert markdown-style message to HTML.
     */
    private function markdownToHtml(string $message): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; }
        .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 10px 10px; }
        strong { color: #1f2937; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
        .alert-box { background: #f3f4f6; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">🔔 Alert.az Notification</h1>
        </div>
        <div class="content">';

        // Convert markdown bold to HTML
        $html .= preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $message);
        $html = nl2br($html);

        $html .= '
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' Alert.az - Your Personal Alert System</p>
            <p>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}