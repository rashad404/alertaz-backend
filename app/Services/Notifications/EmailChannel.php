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
            // Build professional HTML email
            $htmlMessage = $this->buildAlertEmail($message, $alert);

            // Create plain text version
            $plainText = $this->buildPlainText($message, $alert);

            Mail::send([], [], function ($mail) use ($user, $alert, $htmlMessage, $plainText) {
                $mail->to($user->email)
                    ->subject("Alert: {$alert->name} - Alert.az")
                    ->html($htmlMessage)
                    ->text($plainText);
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
     * Build professional HTML email from alert data.
     */
    private function buildAlertEmail(string $message, PersonalAlert $alert): string
    {
        // Try to parse JSON message (new format)
        $data = json_decode($message, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($data['type'])) {
            return $this->buildStructuredEmail($data, $alert);
        }

        // Fallback for plain text messages
        return $this->buildSimpleEmail($message, $alert);
    }

    /**
     * Build email for structured alert data (JSON format).
     */
    private function buildStructuredEmail(array $data, PersonalAlert $alert): string
    {
        $type = $data['type'] ?? 'alert';
        $alertName = htmlspecialchars($alert->name);

        // Determine status styling based on alert type
        $isDown = str_contains($type, 'down') || str_contains($type, 'below');
        $statusColor = $isDown ? '#DC2626' : '#059669';
        $statusBg = $isDown ? '#FEF2F2' : '#ECFDF5';
        $statusText = $isDown ? 'ALERT' : 'RECOVERED';

        // Build content based on alert type
        $content = $this->buildAlertContent($data, $type);

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #515BC3; padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: bold;">Alert.az</h1>
                            <p style="margin: 8px 0 0; color: #E0E7FF; font-size: 14px;">Monitoring System</p>
                        </td>
                    </tr>

                    <!-- Status Banner -->
                    <tr>
                        <td style="background-color: ' . $statusBg . '; padding: 20px 30px; border-left: 4px solid ' . $statusColor . ';">
                            <p style="margin: 0; font-size: 12px; font-weight: bold; color: ' . $statusColor . '; text-transform: uppercase; letter-spacing: 1px;">' . $statusText . '</p>
                            <p style="margin: 6px 0 0; font-size: 18px; font-weight: bold; color: #1F2937;">' . $alertName . '</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            ' . $content . '
                        </td>
                    </tr>

                    <!-- Action Button -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 0 30px 30px; text-align: center;">
                            <a href="' . config('app.frontend_url', 'https://alert.az') . '/alerts" style="display: inline-block; background-color: #515BC3; color: #ffffff; text-decoration: none; padding: 14px 32px; font-weight: bold; font-size: 14px;">View Dashboard</a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 24px 30px; border-top: 1px solid #E5E7EB;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #6B7280; text-align: center;">This is an automated alert from Alert.az</p>
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF; text-align: center;">' . date('Y') . ' Alert.az</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Build alert-specific content section.
     */
    private function buildAlertContent(array $data, string $type): string
    {
        $rows = [];

        if (str_starts_with($type, 'website_')) {
            $url = htmlspecialchars($data['url'] ?? 'Unknown');
            $statusCode = $data['status_code'] ?? 0;
            $timestamp = $data['timestamp'] ?? now()->format('Y-m-d H:i:s');

            if ($type === 'website_down') {
                $error = htmlspecialchars($data['error'] ?? 'Connection failed or server error');
                $rows[] = $this->buildInfoRow('Website', '<a href="' . $url . '" style="color: #515BC3; text-decoration: none;">' . $url . '</a>');
                $rows[] = $this->buildInfoRow('Status', '<span style="color: #EF4444; font-weight: 600;">Unreachable</span>');
                $rows[] = $this->buildInfoRow('HTTP Code', $statusCode > 0 ? (string)$statusCode : 'N/A');
                $rows[] = $this->buildInfoRow('Error', $error);
                $rows[] = $this->buildInfoRow('Detected At', $timestamp);
            } else {
                $responseTime = $data['response_time'] ?? 0;
                $rows[] = $this->buildInfoRow('Website', '<a href="' . $url . '" style="color: #515BC3; text-decoration: none;">' . $url . '</a>');
                $rows[] = $this->buildInfoRow('Status', '<span style="color: #10B981; font-weight: 600;">Online</span>');
                $rows[] = $this->buildInfoRow('HTTP Code', (string)$statusCode);
                $rows[] = $this->buildInfoRow('Response Time', $responseTime . 'ms');
                $rows[] = $this->buildInfoRow('Checked At', $timestamp);
            }
        } elseif (str_starts_with($type, 'crypto_') || str_starts_with($type, 'stock_')) {
            $asset = htmlspecialchars($data['asset'] ?? $data['symbol'] ?? 'Unknown');
            $price = $data['price'] ?? $data['current_price'] ?? 0;
            $threshold = $data['threshold'] ?? $data['target_price'] ?? 0;
            $timestamp = $data['timestamp'] ?? now()->format('Y-m-d H:i:s');

            $rows[] = $this->buildInfoRow('Asset', '<strong>' . strtoupper($asset) . '</strong>');
            $rows[] = $this->buildInfoRow('Current Price', '<span style="font-size: 18px; font-weight: 600; color: #1F2937;">$' . number_format($price, 2) . '</span>');
            $rows[] = $this->buildInfoRow('Target Price', '$' . number_format($threshold, 2));
            $rows[] = $this->buildInfoRow('Triggered At', $timestamp);
        } else {
            // Generic format for other alert types
            foreach ($data as $key => $value) {
                if ($key !== 'type' && !is_array($value)) {
                    $label = ucfirst(str_replace('_', ' ', $key));
                    $rows[] = $this->buildInfoRow($label, htmlspecialchars((string)$value));
                }
            }
        }

        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden;">'
            . implode('', $rows)
            . '</table>';
    }

    /**
     * Build a single info row for the content table.
     */
    private function buildInfoRow(string $label, string $value): string
    {
        return '<tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #E5E7EB; background-color: #F9FAFB; width: 140px; vertical-align: top;">
                <span style="font-size: 13px; color: #6B7280; font-weight: 500;">' . htmlspecialchars($label) . '</span>
            </td>
            <td style="padding: 12px 16px; border-bottom: 1px solid #E5E7EB; vertical-align: top;">
                <span style="font-size: 14px; color: #1F2937;">' . $value . '</span>
            </td>
        </tr>';
    }

    /**
     * Build plain text version of the email.
     */
    private function buildPlainText(string $message, PersonalAlert $alert): string
    {
        $data = json_decode($message, true);
        $lines = [];

        $lines[] = "Alert.az Notification";
        $lines[] = str_repeat("=", 40);
        $lines[] = "";
        $lines[] = "Alert: " . $alert->name;
        $lines[] = "";

        if (json_last_error() === JSON_ERROR_NONE && isset($data['type'])) {
            $type = $data['type'];

            if (str_starts_with($type, 'website_')) {
                $lines[] = "Website: " . ($data['url'] ?? 'Unknown');
                $lines[] = "Status: " . ($type === 'website_down' ? 'DOWN' : 'UP');
                if (isset($data['status_code'])) {
                    $lines[] = "HTTP Code: " . $data['status_code'];
                }
                if (isset($data['error'])) {
                    $lines[] = "Error: " . $data['error'];
                }
                if (isset($data['response_time'])) {
                    $lines[] = "Response Time: " . $data['response_time'] . "ms";
                }
            } else {
                foreach ($data as $key => $value) {
                    if ($key !== 'type' && !is_array($value)) {
                        $lines[] = ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
                    }
                }
            }
        } else {
            $lines[] = $message;
        }

        $lines[] = "";
        $lines[] = str_repeat("-", 40);
        $lines[] = "Timestamp: " . now()->format('Y-m-d H:i:s');
        $lines[] = "";
        $lines[] = "View your alerts: " . config('app.frontend_url', 'https://alert.az') . "/alerts";
        $lines[] = "";
        $lines[] = "© " . date('Y') . " Alert.az";

        return implode("\n", $lines);
    }

    /**
     * Build simple email for plain text messages (fallback).
     */
    private function buildSimpleEmail(string $message, PersonalAlert $alert): string
    {
        $alertName = htmlspecialchars($alert->name);
        $htmlMessage = nl2br(htmlspecialchars($message));

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #515BC3 0%, #7C3AED 100%); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Alert.az</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            <h2 style="margin: 0 0 20px; color: #1F2937; font-size: 18px;">' . $alertName . '</h2>
                            <div style="color: #4B5563; font-size: 15px; line-height: 1.6;">' . $htmlMessage . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">© ' . date('Y') . ' Alert.az</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}