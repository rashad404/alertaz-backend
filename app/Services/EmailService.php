<?php

namespace App\Services;

use App\Models\User;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    // Email cost per message (in AZN)
    private float $costPerEmail;

    public function __construct()
    {
        $this->costPerEmail = (float) config('services.email.cost_per_message', 0.01);
    }

    /**
     * Check if email test mode is enabled
     */
    public function isTestMode(): bool
    {
        return config('services.email.test_mode', true);
    }

    /**
     * Send email with billing and logging
     *
     * @param User $user - User to charge
     * @param string $toEmail - Recipient email
     * @param string $subject - Email subject
     * @param string $bodyHtml - HTML body
     * @param string|null $bodyText - Plain text body (optional)
     * @param string|null $toName - Recipient name (optional)
     * @param string|null $fromEmail - From email (optional, uses default)
     * @param string|null $fromName - From name (optional, uses default)
     * @param string $source - 'api', 'verification', 'notification', 'campaign'
     * @param int|null $clientId - Optional client ID for API sources
     * @param int|null $campaignId - Optional campaign ID for campaign sources
     * @param int|null $contactId - Optional contact ID for campaign sources
     * @return array
     */
    public function send(
        User $user,
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $toName = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        string $source = 'api',
        ?int $clientId = null,
        ?int $campaignId = null,
        ?int $contactId = null
    ): array {
        $cost = $this->costPerEmail;
        $isTest = $this->isTestMode();

        // Use defaults if not provided
        $fromEmail = $fromEmail ?? config('mail.from.address', 'noreply@alert.az');
        $fromName = $fromName ?? config('mail.from.name', 'Alert.az');

        // In test mode, skip billing and actual sending
        if ($isTest) {
            Log::info("📧 [TEST MODE] Email to {$toEmail}: {$subject}", [
                'user_id' => $user->id,
                'source' => $source,
                'cost' => $cost,
            ]);

            // Record message as test
            $emailMessage = $this->recordMessage(
                user: $user,
                toEmail: $toEmail,
                toName: $toName,
                fromEmail: $fromEmail,
                fromName: $fromName,
                subject: $subject,
                bodyHtml: $bodyHtml,
                bodyText: $bodyText,
                source: $source,
                cost: 0, // No cost in test mode
                isTest: true,
                clientId: $clientId,
                campaignId: $campaignId,
                contactId: $contactId
            );

            $emailMessage->markAsSent('test-' . uniqid());

            return [
                'success' => true,
                'message' => 'Email sent successfully (test mode)',
                'message_id' => $emailMessage->id,
                'cost' => 0,
                'is_test' => true,
                'debug' => [
                    'to' => $toEmail,
                    'subject' => $subject,
                    'mode' => 'test',
                ],
            ];
        }

        // Production mode - check balance first
        if (!$user->hasEnoughBalance($cost)) {
            Log::warning("Email send failed: insufficient balance", [
                'user_id' => $user->id,
                'balance' => $user->balance,
                'required' => $cost,
                'to_email' => $toEmail,
            ]);

            return [
                'success' => false,
                'error' => 'Insufficient balance. Please add funds to send email.',
                'error_code' => 'insufficient_balance',
                'required_amount' => $cost,
                'current_balance' => (float) $user->balance,
            ];
        }

        // Record message before sending (status: pending)
        $emailMessage = $this->recordMessage(
            user: $user,
            toEmail: $toEmail,
            toName: $toName,
            fromEmail: $fromEmail,
            fromName: $fromName,
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            source: $source,
            cost: $cost,
            isTest: false,
            clientId: $clientId,
            campaignId: $campaignId,
            contactId: $contactId
        );

        try {
            // Send via Laravel Mail with SES Configuration Set for tracking
            $sesConfigSet = config('services.ses.configuration_set');

            Mail::send([], [], function ($mail) use ($toEmail, $toName, $fromEmail, $fromName, $subject, $bodyHtml, $bodyText, $sesConfigSet) {
                $mail->to($toEmail, $toName)
                    ->from($fromEmail, $fromName)
                    ->subject($subject)
                    ->html($bodyHtml);

                if ($bodyText) {
                    $mail->text($bodyText);
                }

                // Attach SES Configuration Set for delivery tracking
                if ($sesConfigSet) {
                    $mail->getHeaders()->addTextHeader('X-SES-CONFIGURATION-SET', $sesConfigSet);
                }
            });

            // Deduct balance on success
            $user->deductBalance($cost);

            // Mark message as sent
            $emailMessage->markAsSent();

            Log::info("📧 Email sent successfully", [
                'user_id' => $user->id,
                'to_email' => $toEmail,
                'source' => $source,
                'cost' => $cost,
                'message_id' => $emailMessage->id,
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'message_id' => $emailMessage->id,
                'cost' => $cost,
                'new_balance' => (float) $user->fresh()->balance,
            ];
        } catch (\Exception $e) {
            // Send failed - mark message as failed (no billing)
            $emailMessage->markAsFailed(
                errorMessage: $e->getMessage(),
                errorCode: $e->getCode() ? (string) $e->getCode() : null,
                failureReason: 'send_error'
            );

            Log::error("📧 Email send failed", [
                'user_id' => $user->id,
                'to_email' => $toEmail,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage(),
                'error_code' => 'send_failed',
            ];
        }
    }

    /**
     * Send simple email (for verification codes, etc.)
     */
    public function sendSimple(
        User $user,
        string $toEmail,
        string $subject,
        string $message,
        string $source = 'verification'
    ): array {
        $bodyHtml = $this->buildSimpleHtml($subject, $message);
        $bodyText = strip_tags($message);

        return $this->send(
            user: $user,
            toEmail: $toEmail,
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            source: $source
        );
    }

    /**
     * Send verification code email
     */
    public function sendVerificationCode(
        User $user,
        string $toEmail,
        string $code,
        string $source = 'verification'
    ): array {
        $subject = 'Verification Code - Alert.az';
        $bodyHtml = $this->buildVerificationCodeHtml($code);
        $bodyText = "Your verification code is: {$code}\n\nThis code will expire in 15 minutes.";

        return $this->send(
            user: $user,
            toEmail: $toEmail,
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            source: $source
        );
    }

    /**
     * Get cost per email
     */
    public function getCostPerEmail(): float
    {
        return $this->costPerEmail;
    }

    /**
     * Check if user has enough balance for email
     */
    public function hasEnoughBalance(User $user): bool
    {
        return $user->hasEnoughBalance($this->costPerEmail);
    }

    /**
     * Record email message in database
     */
    private function recordMessage(
        User $user,
        string $toEmail,
        ?string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $bodyHtml,
        ?string $bodyText,
        string $source,
        float $cost,
        bool $isTest,
        ?int $clientId = null,
        ?int $campaignId = null,
        ?int $contactId = null
    ): EmailMessage {
        return EmailMessage::create([
            'user_id' => $user->id,
            'client_id' => $clientId,
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'source' => $source,
            'cost' => $cost,
            'is_test' => $isTest,
            'status' => 'pending',
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Build simple HTML email
     */
    private function buildSimpleHtml(string $title, string $message): string
    {
        $htmlMessage = nl2br(htmlspecialchars($message));

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
                    <tr>
                        <td style="background-color: #515BC3; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Alert.az</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            <h2 style="margin: 0 0 20px; color: #1F2937; font-size: 18px;">' . htmlspecialchars($title) . '</h2>
                            <div style="color: #4B5563; font-size: 15px; line-height: 1.6;">' . $htmlMessage . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">&copy; ' . date('Y') . ' Alert.az</p>
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
     * Build verification code HTML email
     */
    private function buildVerificationCodeHtml(string $code): string
    {
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
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Alert.az</h1>
                            <p style="margin: 8px 0 0; color: #E0E7FF; font-size: 14px;">Verification Code</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px 30px; text-align: center;">
                            <p style="margin: 0 0 20px; color: #4B5563; font-size: 16px;">Your verification code is:</p>
                            <div style="background-color: #F3F4F6; border-radius: 8px; padding: 20px; display: inline-block;">
                                <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1F2937; font-family: monospace;">' . $code . '</span>
                            </div>
                            <p style="margin: 20px 0 0; color: #9CA3AF; font-size: 14px;">This code will expire in 15 minutes.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">If you did not request this code, please ignore this email.</p>
                            <p style="margin: 10px 0 0; font-size: 12px; color: #9CA3AF;">&copy; ' . date('Y') . ' Alert.az</p>
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
