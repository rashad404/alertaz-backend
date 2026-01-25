<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageSender
{
    private QuickSmsService $quickSmsService;

    public function __construct(QuickSmsService $quickSmsService)
    {
        $this->quickSmsService = $quickSmsService;
    }

    /**
     * Send SMS
     */
    public function sendSms(string $phone, string $message, string $sender): array
    {
        $testMode = config('services.quicksms.test_mode', true);

        // In test mode, just log and return success
        if ($testMode) {
            Log::info('SMS Test Mode: Would send SMS', [
                'phone' => $phone,
                'message' => $message,
                'sender' => $sender,
            ]);

            return [
                'success' => true,
                'message_id' => 'test_' . uniqid(),
                'cost' => config('services.sms.cost_per_message', 0.04),
                'test_mode' => true,
            ];
        }

        // Use QuickSmsService for actual sending
        $result = $this->quickSmsService->sendSMS($phone, $message, $sender);

        if ($result['success']) {
            return [
                'success' => true,
                'message_id' => $result['transaction_id'] ?? null,
                'cost' => config('services.sms.cost_per_message', 0.04),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error_message'] ?? 'Failed to send SMS',
        ];
    }

    /**
     * Send Email
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $from = null): array
    {
        $testMode = config('services.email.test_mode', true);

        // In test mode, just log and return success
        if ($testMode) {
            Log::info('Email Test Mode: Would send email', [
                'to' => $to,
                'subject' => $subject,
                'from' => $from,
            ]);

            return [
                'success' => true,
                'message_id' => 'test_email_' . uniqid(),
                'cost' => config('services.email.cost_per_message', 0.01),
                'test_mode' => true,
            ];
        }

        try {
            $fromAddress = $from ?? config('mail.from.address');
            $fromName = config('mail.from.name', 'Alert.az');

            \Mail::send([], [], function ($mail) use ($to, $subject, $body, $fromAddress, $fromName) {
                $mail->to($to)
                    ->from($fromAddress, $fromName)
                    ->subject($subject)
                    ->html($body);
            });

            return [
                'success' => true,
                'message_id' => uniqid('email_'),
                'cost' => config('services.email.cost_per_message', 0.01),
            ];
        } catch (\Exception $e) {
            Log::error('Email send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
