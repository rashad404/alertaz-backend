<?php

namespace App\Services;

use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VerificationService
{
    private SmsService $smsService;
    private QuickSmsService $quickSmsService;

    public function __construct(SmsService $smsService, QuickSmsService $quickSmsService)
    {
        $this->smsService = $smsService;
        $this->quickSmsService = $quickSmsService;
    }

    /**
     * Check if SMS test mode is enabled
     */
    private function isSmsTestMode(): bool
    {
        return env('SMS_TEST_MODE', true);
    }

    /**
     * Check if Email test mode is enabled
     */
    private function isEmailTestMode(): bool
    {
        return env('EMAIL_TEST_MODE', true);
    }

    /**
     * Get the mock verification code for testing
     */
    private function getMockCode(): string
    {
        return env('VERIFICATION_MOCK_CODE', '123456');
    }

    /**
     * Send SMS verification code
     *
     * @param string $phone Phone number to send code to
     * @param User|null $user User to charge (if provided, billing will be applied)
     * @param string $purpose Purpose of verification ('login', 'verify')
     * @return array
     */
    public function sendSMSVerification(string $phone, ?User $user = null, string $purpose = 'login'): array
    {
        try {
            // Generate code - use mock code in test mode, random code in production
            $code = $this->isSmsTestMode() ? $this->getMockCode() : $this->generateCode();

            // Store verification record
            $verification = OtpVerification::create([
                'phone' => $phone,
                'email' => null,
                'code' => $code,
                'type' => 'sms',
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // In test mode, just log the code (no real SMS, no billing)
            if ($this->isSmsTestMode()) {
                Log::info("📱 [TEST MODE] SMS Verification Code for {$phone}: {$code}");

                return [
                    'success' => true,
                    'message' => 'Verification code sent successfully',
                    'expires_in' => 600,
                    'debug' => [
                        'code' => $code,
                        'phone' => $phone,
                        'mode' => 'test',
                    ],
                ];
            }

            // Production mode - send actual SMS with billing
            $message = "Your Alert.az verification code is: {$code}. Valid for 10 minutes.";

            // If user is provided, use SmsService with billing
            if ($user) {
                $result = $this->smsService->send(
                    user: $user,
                    phone: $phone,
                    message: $message,
                    sender: 'Alert.az',
                    source: 'verification'
                );

                // Check for insufficient balance
                if (!$result['success'] && ($result['error_code'] ?? null) === 'insufficient_balance') {
                    return [
                        'success' => false,
                        'message' => 'Insufficient balance to send verification SMS',
                        'error_code' => 'insufficient_balance',
                        'required_amount' => $result['required_amount'] ?? null,
                        'current_balance' => $result['current_balance'] ?? null,
                    ];
                }

                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'Failed to send SMS');
                }

                Log::info("📱 SMS Verification sent to {$phone} (billed)", [
                    'user_id' => $user->id,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'cost' => $result['cost'] ?? 0,
                ]);

                return [
                    'success' => true,
                    'message' => 'Verification code sent to ' . $this->maskPhone($phone),
                    'expires_in' => 600,
                    'cost' => $result['cost'] ?? null,
                ];
            }

            // No user provided - send via QuickSMS directly (no billing, e.g., registration)
            $result = $this->quickSmsService->sendSMS($phone, $message, 'Alert.az');

            if (!$result['success']) {
                throw new \Exception($result['error_message'] ?? 'Failed to send SMS');
            }

            Log::info("📱 SMS Verification sent to {$phone} (no billing)", [
                'transaction_id' => $result['transaction_id'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'Verification code sent to ' . $this->maskPhone($phone),
                'expires_in' => 600,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send SMS verification: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email verification code
     */
    public function sendEmailVerification(string $email, ?User $user = null): array
    {
        try {
            // Generate code - use mock code in test mode, random code in production
            $code = $this->isEmailTestMode() ? $this->getMockCode() : $this->generateCode();

            // Store verification record
            $verification = OtpVerification::create([
                'phone' => null,
                'email' => $email,
                'code' => $code,
                'type' => 'email',
                'purpose' => 'verify',
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // In test mode, just log the code (no real email)
            if ($this->isEmailTestMode()) {
                Log::info("📧 [TEST MODE] Email Verification Code for {$email}: {$code}");

                return [
                    'success' => true,
                    'message' => 'Verification code sent successfully',
                    'expires_in' => 900,
                    'debug' => [
                        'code' => $code,
                        'email' => $email,
                        'mode' => 'test',
                    ],
                ];
            }

            // Production mode - send actual email
            $sent = $this->sendActualEmail($email, $code);

            if (!$sent) {
                throw new \Exception('Failed to send email');
            }

            return [
                'success' => true,
                'message' => 'Verification code sent to ' . $this->maskEmail($email),
                'expires_in' => 900,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send email verification: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyCode(string $identifier, string $code, string $type = 'sms'): array
    {
        try {
            // Check test mode based on type
            $isTestMode = $type === 'sms' ? $this->isSmsTestMode() : $this->isEmailTestMode();

            // In test mode, always accept the mock code
            if ($isTestMode && $code === $this->getMockCode()) {
                Log::info("[TEST MODE] Auto-accepting mock verification code {$code} for {$identifier}");

                // Clean up any existing verifications for this identifier
                if ($type === 'sms') {
                    OtpVerification::where('phone', $identifier)->delete();
                } else {
                    OtpVerification::where('email', $identifier)->delete();
                }

                return [
                    'success' => true,
                    'message' => 'Verification successful',
                    'debug' => [
                        'mode' => 'test',
                        'accepted_code' => $code,
                    ],
                ];
            }

            // Find the most recent valid verification
            $query = OtpVerification::where('type', $type)
                ->where('code', $code)
                ->where('expires_at', '>', now())
                ->where('verified_at', null);

            if ($type === 'sms') {
                $query->where('phone', $identifier);
            } else {
                $query->where('email', $identifier);
            }

            $verification = $query->latest()->first();

            if (!$verification) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification code',
                ];
            }

            // Mark as verified
            $verification->update([
                'verified_at' => now(),
            ]);

            // Clean up old verifications for this identifier
            if ($type === 'sms') {
                OtpVerification::where('phone', $identifier)
                    ->where('id', '!=', $verification->id)
                    ->delete();
            } else {
                OtpVerification::where('email', $identifier)
                    ->where('id', '!=', $verification->id)
                    ->delete();
            }

            return [
                'success' => true,
                'message' => 'Verification successful',
                'user_id' => $verification->user_id,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to verify code: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resend verification code
     *
     * @param string $identifier Phone or email
     * @param string $type Type of verification ('sms' or 'email')
     * @param User|null $user User to charge (if provided, billing will be applied for SMS)
     * @return array
     */
    public function resendCode(string $identifier, string $type = 'sms', ?User $user = null): array
    {
        // Check for rate limiting
        $recentAttempt = OtpVerification::where('type', $type)
            ->where($type === 'sms' ? 'phone' : 'email', $identifier)
            ->where('created_at', '>', now()->subMinute())
            ->exists();

        $isTestMode = $type === 'sms' ? $this->isSmsTestMode() : $this->isEmailTestMode();
        if ($recentAttempt && !$isTestMode) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting another code',
                'retry_after' => 60,
            ];
        }

        // Delete old codes for this identifier
        OtpVerification::where('type', $type)
            ->where($type === 'sms' ? 'phone' : 'email', $identifier)
            ->where('verified_at', null)
            ->delete();

        // Send new code
        if ($type === 'sms') {
            return $this->sendSMSVerification($identifier, $user, 'verify');
        } else {
            return $this->sendEmailVerification($identifier, $user);
        }
    }

    /**
     * Generate random verification code
     */
    private function generateCode(): string
    {
        return (string) rand(100000, 999999); // 6-digit code
    }

    /**
     * Send actual email (production)
     */
    private function sendActualEmail(string $email, string $code): bool
    {
        try {
            Mail::send([], [], function ($mail) use ($email, $code) {
                $mail->to($email)
                    ->subject('Alert.az - Verification Code')
                    ->html($this->getEmailTemplate($code))
                    ->text("Your Alert.az verification code is: {$code}. Valid for 15 minutes.");
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email template
     */
    private function getEmailTemplate(string $code): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 10px 10px; }
                .code-box { background: #f3f4f6; border: 2px solid #667eea; padding: 20px; text-align: center; margin: 30px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Alert.az</h1>
                    <p>Email Verification</p>
                </div>
                <div class="content">
                    <p>Hello!</p>
                    <p>You requested a verification code for your Alert.az account.</p>
                    <div class="code-box">
                        <div class="code">' . $code . '</div>
                    </div>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you didn\'t request this code, please ignore this email.</p>
                    <div class="footer">
                        <p>© ' . date('Y') . ' Alert.az - Your Personal Alert System</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Mask phone number for display
     */
    private function maskPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 6) {
            return substr($phone, 0, 3) . '****' . substr($phone, -2);
        }
        return $phone;
    }

    /**
     * Mask email for display
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $name = $parts[0];
            $domain = $parts[1];

            if (strlen($name) > 2) {
                $name = substr($name, 0, 2) . str_repeat('*', min(4, strlen($name) - 2));
            }

            return $name . '@' . $domain;
        }
        return $email;
    }

    /**
     * Check if identifier has been verified recently
     */
    public function isRecentlyVerified(string $identifier, string $type = 'sms', int $minutes = 30): bool
    {
        $query = OtpVerification::where('type', $type)
            ->where('verified_at', '>', now()->subMinutes($minutes));

        if ($type === 'sms') {
            $query->where('phone', $identifier);
        } else {
            $query->where('email', $identifier);
        }

        return $query->exists();
    }

    /**
     * Clean up expired verifications
     */
    public function cleanupExpired(): int
    {
        return OtpVerification::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDay())
            ->delete();
    }
}