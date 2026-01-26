<?php

namespace App\Jobs;

use App\Exceptions\TemplateRenderException;
use App\Models\Campaign;
use App\Models\CampaignContactLog;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\UserEmailSender;
use App\Services\EmailService;
use App\Services\QuickSmsService;
use App\Services\TemplateRenderer;
use App\Helpers\EmailValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Set high because rate limiter releases count as attempts.
     */
    public int $tries = 100;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     * This is what actually limits retries on real errors.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Backoff intervals in seconds for retries.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Campaign $campaign,
        public Contact $contact
    ) {}

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new RateLimited('sms')];
    }

    /**
     * Execute the job.
     */
    public function handle(TemplateRenderer $templateRenderer, QuickSmsService $smsService, EmailService $emailService): void
    {
        // Skip if campaign is no longer active
        if (!in_array($this->campaign->status, [Campaign::STATUS_ACTIVE, Campaign::STATUS_SENDING])) {
            Log::info("Skipping message for campaign {$this->campaign->id}: campaign not active");
            return;
        }

        // Skip if contact already received message (cooldown check)
        if (CampaignContactLog::isInCooldown(
            $this->campaign->id,
            $this->contact->id,
            $this->campaign->cooldown_days
        )) {
            Log::info("Skipping message for contact {$this->contact->id}: in cooldown period");
            return;
        }

        // Check test mode
        $globalTestMode = config('services.quicksms.test_mode', false);
        $mockMode = $globalTestMode || $this->campaign->is_test;

        // Get user for balance
        $user = User::find($this->campaign->client->user_id);

        $smsSent = false;
        $emailSent = false;

        // Send SMS if campaign requires it and contact can receive
        if ($this->campaign->requiresPhone() && $this->contact->canReceiveSms()) {
            $smsSent = $this->sendSms($user, $templateRenderer, $smsService, $mockMode);
        }

        // Send Email if campaign requires it and contact can receive
        if ($this->campaign->requiresEmail() && $this->contact->canReceiveEmail()) {
            $emailSent = $this->sendEmail($user, $templateRenderer, $emailService, $mockMode);
        }

        // Record in contact log for deduplication if at least one message was sent
        if ($smsSent || $emailSent) {
            CampaignContactLog::recordSend($this->campaign->id, $this->contact->id);
        }
    }

    /**
     * Send SMS to contact
     */
    protected function sendSms(User $user, TemplateRenderer $templateRenderer, QuickSmsService $smsService, bool $mockMode): bool
    {
        // Render message with strict mode (throws exception on unresolved variables)
        try {
            $message = $templateRenderer->renderStrict(
                $this->campaign->message_template,
                $this->contact
            );
        } catch (TemplateRenderException $e) {
            Log::warning("SMS template rendering failed for campaign {$this->campaign->id}", [
                'contact_id' => $this->contact->id,
                'phone' => $this->contact->phone,
                'error' => $e->getMessage(),
                'unresolved_variables' => $e->getUnresolvedVariables(),
            ]);

            $this->recordSmsFailure($user, $templateRenderer, $this->campaign->message_template, $mockMode, 'Template error: ' . $e->getMessage(), null);
            return false;
        }

        $message = $templateRenderer->sanitizeForSMS($message);

        // Calculate cost
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * config('app.sms_cost_per_message', 0.04);

        // Check balance (skip in mock mode)
        if (!$mockMode && $user->balance < $cost) {
            Log::warning("Insufficient balance for SMS in campaign {$this->campaign->id}, user {$user->id}");
            $this->campaign->pause('insufficient_balance');
            return false;
        }

        // Send SMS
        $messageStatus = 'pending';
        $deliveryStatus = null;
        $externalId = null;
        $errorMessage = null;
        $errorCode = null;

        if ($mockMode) {
            $messageStatus = 'sent';
            $deliveryStatus = 'delivered';
        } else {
            $unicode = $smsService->requiresUnicode($message);
            $result = $smsService->sendSMS($this->contact->phone, $message, $this->campaign->sender, $unicode);

            if ($result['success']) {
                $messageStatus = 'sent';
                $externalId = $result['transaction_id'] ?? null;
                $deliveryStatus = 'pending';
                $user->deductBalance($cost);
            } else {
                $errorCode = $result['error_code'] ?? -500;
                $errorMessage = $result['error_message'] ?? 'Unknown error';
                $errorType = $smsService->categorizeError($errorCode);

                Log::warning("Campaign SMS failed", [
                    'campaign_id' => $this->campaign->id,
                    'contact_id' => $this->contact->id,
                    'phone' => $this->contact->phone,
                    'error_code' => $errorCode,
                    'error_type' => $errorType,
                    'error' => $errorMessage,
                ]);

                switch ($errorType) {
                    case 'permanent':
                        $messageStatus = 'failed';
                        $deliveryStatus = 'failed';
                        $this->campaign->increment('failed_count');
                        break;

                    case 'auth':
                    case 'balance':
                        $this->campaign->pause();
                        Log::error("Campaign {$this->campaign->id} paused due to {$errorType} error: {$errorMessage}");
                        $this->recordSmsFailure($user, $templateRenderer, $message, $mockMode, $errorMessage, $errorCode);
                        return false;

                    case 'temporary':
                    default:
                        throw new RuntimeException("SMS send failed (temporary): {$errorMessage}");
                }
            }
        }

        // Create SMS message record
        Message::createSms([
            'client_id' => $this->campaign->client_id,
            'campaign_id' => $this->campaign->id,
            'customer_id' => null, // Contact-based campaign, not customer-based
            'recipient' => $this->contact->phone,
            'content' => $message,
            'sender' => $this->campaign->sender,
            'source' => 'campaign',
            'cost' => $cost,
            'status' => $messageStatus,
            'is_test' => $mockMode,
            'provider_message_id' => $externalId,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'sent_at' => $messageStatus === 'sent' ? now() : null,
            'delivered_at' => $deliveryStatus === 'delivered' ? now() : null,
        ]);

        if ($messageStatus === 'sent') {
            $this->campaign->increment('sent_count');
            Log::info("SMS sent to {$this->contact->phone} for campaign {$this->campaign->id}");
            return true;
        }

        return false;
    }

    /**
     * Send Email to contact
     */
    protected function sendEmail(User $user, TemplateRenderer $templateRenderer, EmailService $emailService, bool $mockMode): bool
    {
        $email = $this->contact->getEmailForValidation();

        if (!EmailValidator::isValid($email)) {
            Log::warning('Skipping invalid email in automated campaign', [
                'campaign_id' => $this->campaign->id,
                'contact_id' => $this->contact->id,
                'email' => $email,
            ]);
            return false;
        }

        // Render email subject and body
        try {
            $subject = $templateRenderer->renderStrict(
                $this->campaign->email_subject_template ?? '',
                $this->contact
            );
            $bodyText = $templateRenderer->renderStrict(
                $this->campaign->email_body_template ?? '',
                $this->contact
            );
        } catch (TemplateRenderException $e) {
            Log::warning("Email template rendering failed for campaign {$this->campaign->id}", [
                'contact_id' => $this->contact->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $this->campaign->increment('email_failed_count');
            return false;
        }

        // Get email sender details
        $emailSenderDetails = UserEmailSender::getByEmail($this->campaign->email_sender ?? '');
        if (!$emailSenderDetails) {
            $emailSenderDetails = UserEmailSender::getDefault();
        }
        $emailSenderEmail = $emailSenderDetails['email'];
        $emailSenderName = $emailSenderDetails['name'];

        // Use campaign's email_display_name if set
        $displayName = $this->campaign->email_display_name ?? $emailSenderName;

        // Convert to HTML
        $bodyHtml = $this->convertToHtmlEmail($bodyText, $subject, $displayName);

        // Calculate cost
        $cost = config('app.email_cost_per_message', 0.01);

        // Check balance
        if (!$mockMode && $user->balance < $cost) {
            Log::warning("Insufficient balance for email in campaign {$this->campaign->id}, user {$user->id}");
            $this->campaign->pause('insufficient_balance');
            return false;
        }

        if ($mockMode) {
            // Mock mode: create record without actually sending
            Message::createEmail([
                'client_id' => $this->campaign->client_id,
                'campaign_id' => $this->campaign->id,
                'customer_id' => null, // Contact-based campaign, not customer-based
                'recipient' => $email,
                'subject' => $subject,
                'content' => $bodyHtml,
                'sender' => $displayName,
                'source' => 'campaign',
                'cost' => 0,
                'status' => Message::STATUS_SENT,
                'is_test' => true,
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);

            $this->campaign->increment('email_sent_count');
            Log::info("Email (mock) sent to {$email} for campaign {$this->campaign->id}");
            return true;
        } else {
            // Real mode: send via EmailService
            $result = $emailService->send(
                $user,
                $email,
                $subject,
                $bodyHtml,
                $bodyText,
                null, // toName
                $emailSenderEmail,
                $emailSenderName,
                'campaign',
                $this->campaign->client_id,
                $this->campaign->id,
                $this->contact->id
            );

            if ($result['success']) {
                $this->campaign->increment('email_sent_count');
                Log::info("Email sent to {$email} for campaign {$this->campaign->id}");
                return true;
            } else {
                $this->campaign->increment('email_failed_count');
                Log::warning("Email failed for campaign {$this->campaign->id}", [
                    'contact_id' => $this->contact->id,
                    'email' => $email,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                return false;
            }
        }
    }

    /**
     * Convert plain text to HTML email
     */
    protected function convertToHtmlEmail(string $body, string $subject, ?string $senderName = null): string
    {
        $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $senderDisplay = $senderName ?? 'Alert.az';
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="az">
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
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">{$senderDisplay}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            <div style="color: #4B5563; font-size: 15px; line-height: 1.6;">{$htmlBody}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">&copy; {$year} {$senderDisplay}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Record a failed SMS message
     */
    private function recordSmsFailure(
        User $user,
        TemplateRenderer $templateRenderer,
        string $message,
        bool $mockMode,
        string $errorMessage,
        ?int $errorCode
    ): void {
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * config('app.sms_cost_per_message', 0.04);

        Message::createSms([
            'client_id' => $this->campaign->client_id,
            'campaign_id' => $this->campaign->id,
            'customer_id' => null,
            'recipient' => $this->contact->phone,
            'content' => $message,
            'sender' => $this->campaign->sender,
            'source' => 'campaign',
            'cost' => $cost,
            'segments' => $segments,
            'status' => Message::STATUS_FAILED,
            'is_test' => $mockMode,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
        ]);

        $this->campaign->increment('failed_count');
    }

    /**
     * Handle a job failure (after all retries exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send message to contact {$this->contact->id} for campaign {$this->campaign->id}: " . $exception->getMessage());
    }
}
