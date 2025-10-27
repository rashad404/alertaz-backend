<?php

namespace App\Services;

use App\Models\User;
use App\Models\OTPVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VerificationService
{
    /**
     * Check if we're in local/development mode
     */
    private function isLocalMode(): bool
    {
        return app()->environment('local', 'development') ||
               config('app.verification_mock', false) ||
               env('VERIFICATION_MOCK_MODE', false);
    }

    /**
     * Get the mock verification code for local testing
     */
    private function getMockCode(): string
    {
        return config('app.mock_verification_code', '123456');
    }

    /**
     * Send SMS verification code
     */
    public function sendSMSVerification(string $phone, ?User $user = null): array
    {
        try {
            // Generate code
            $code = $this->isLocalMode() ? $this->getMockCode() : $this->generateCode();

            // Store verification record
            $verification = OTPVerification::create([
                'phone' => $phone,
                'email' => null,
                'code' => $code,
                'type' => 'sms',
                'purpose' => 'login',
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // In local mode, just log the code
            if ($this->isLocalMode()) {
                Log::info("📱 [LOCAL MODE] SMS Verification Code for {$phone}: {$code}");

                return [
                    'success' => true,
                    'message' => 'Verification code sent successfully (LOCAL MODE: use ' . $code . ')',
                    'expires_in' => 600, // 10 minutes in seconds
                    'debug' => app()->environment('local') ? [
                        'code' => $code,
                        'phone' => $phone,
                        'mode' => 'mock',
                    ] : null,
                ];
            }

            // Production mode - send actual SMS
            $sent = $this->sendActualSMS($phone, $code);

            if (!$sent) {
                throw new \Exception('Failed to send SMS');
            }

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
            // Generate code
            $code = $this->isLocalMode() ? $this->getMockCode() : $this->generateCode();

            // Store verification record
            $verification = OTPVerification::create([
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

            // In local mode, just log the code
            if ($this->isLocalMode()) {
                Log::info("📧 [LOCAL MODE] Email Verification Code for {$email}: {$code}");

                return [
                    'success' => true,
                    'message' => 'Verification code sent successfully (LOCAL MODE: use ' . $code . ')',
                    'expires_in' => 900, // 15 minutes in seconds
                    'debug' => app()->environment('local') ? [
                        'code' => $code,
                        'email' => $email,
                        'mode' => 'mock',
                    ] : null,
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
            // In local mode, always accept the mock code
            if ($this->isLocalMode() && $code === $this->getMockCode()) {
                Log::info("[LOCAL MODE] Auto-accepting mock verification code {$code} for {$identifier}");

                // Clean up any existing verifications for this identifier
                if ($type === 'sms') {
                    OTPVerification::where('phone', $identifier)->delete();
                } else {
                    OTPVerification::where('email', $identifier)->delete();
                }

                return [
                    'success' => true,
                    'message' => 'Verification successful (LOCAL MODE)',
                    'debug' => app()->environment('local') ? [
                        'mode' => 'mock',
                        'accepted_code' => $code,
                    ] : null,
                ];
            }

            // Find the most recent valid verification
            $query = OTPVerification::where('type', $type)
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
                OTPVerification::where('phone', $identifier)
                    ->where('id', '!=', $verification->id)
                    ->delete();
            } else {
                OTPVerification::where('email', $identifier)
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
     */
    public function resendCode(string $identifier, string $type = 'sms'): array
    {
        // Check for rate limiting
        $recentAttempt = OTPVerification::where('type', $type)
            ->where($type === 'sms' ? 'phone' : 'email', $identifier)
            ->where('created_at', '>', now()->subMinute())
            ->exists();

        if ($recentAttempt && !$this->isLocalMode()) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting another code',
                'retry_after' => 60,
            ];
        }

        // Delete old codes for this identifier
        OTPVerification::where('type', $type)
            ->where($type === 'sms' ? 'phone' : 'email', $identifier)
            ->where('verified_at', null)
            ->delete();

        // Send new code
        if ($type === 'sms') {
            return $this->sendSMSVerification($identifier);
        } else {
            return $this->sendEmailVerification($identifier);
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
     * Send actual SMS (production)
     */
    private function sendActualSMS(string $phone, string $code): bool
    {
        try {
            $provider = config('services.sms.provider', 'twilio');
            $message = "Your Alert.az verification code is: {$code}. Valid for 10 minutes.";

            switch ($provider) {
                case 'twilio':
                    return $this->sendViaTwilio($phone, $message);
                case 'nexmo':
                    return $this->sendViaNexmo($phone, $message);
                case 'azercell':
                    return $this->sendViaAzercell($phone, $message);
                default:
                    Log::warning('No SMS provider configured, unable to send verification');
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio(string $phone, string $message): bool
    {
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $from = env('TWILIO_FROM');

        if (!$sid || !$token || !$from) {
            return false;
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $this->formatPhone($phone),
                'Body' => $message,
            ]);

        return $response->successful();
    }

    /**
     * Send SMS via Nexmo
     */
    private function sendViaNexmo(string $phone, string $message): bool
    {
        $key = env('NEXMO_KEY');
        $secret = env('NEXMO_SECRET');

        if (!$key || !$secret) {
            return false;
        }

        $response = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key' => $key,
            'api_secret' => $secret,
            'from' => 'Alert.az',
            'to' => $this->formatPhone($phone),
            'text' => $message,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return isset($data['messages'][0]['status']) && $data['messages'][0]['status'] == '0';
        }

        return false;
    }

    /**
     * Send SMS via Azercell
     */
    private function sendViaAzercell(string $phone, string $message): bool
    {
        // Placeholder for Azercell implementation
        Log::info("Would send SMS via Azercell to {$phone}: {$message}");
        return false;
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
     * Format phone number
     */
    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 9 && in_array(substr($phone, 0, 2), ['50', '51', '55', '70', '77'])) {
            $phone = '994' . $phone;
        }

        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
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
        $query = OTPVerification::where('type', $type)
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
        return OTPVerification::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDay())
            ->delete();
    }
}