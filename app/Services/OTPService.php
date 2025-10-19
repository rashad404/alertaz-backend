<?php

namespace App\Services;

use App\Models\OTPVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OTPService
{
    /**
     * Send OTP to phone number.
     */
    public function sendOTP($phone, $purpose = 'login')
    {
        // Clean up old OTPs for this phone
        OTPVerification::where('phone', $phone)
            ->where('expires_at', '<', now())
            ->delete();

        // Check if there's a recent OTP (rate limiting)
        $recentOTP = OTPVerification::where('phone', $phone)
            ->where('created_at', '>', now()->subMinute())
            ->first();

        if ($recentOTP) {
            return [
                'success' => false,
                'message' => 'Please wait 1 minute before requesting a new OTP'
            ];
        }

        // Generate 6-digit OTP
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // In development, use a fixed code for testing
        if (config('app.env') === 'local') {
            $code = '123456';
        }

        // Create OTP record
        $otp = OTPVerification::create([
            'phone' => $phone,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Send SMS (integrate with your SMS provider)
        $sent = $this->sendSMS($phone, $code);

        if (!$sent) {
            return [
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.'
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP sent successfully'
        ];
    }

    /**
     * Verify OTP code.
     */
    public function verifyOTP($phone, $code)
    {
        $otp = OTPVerification::where('phone', $phone)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (!$otp) {
            // Increment attempts for the most recent OTP
            OTPVerification::where('phone', $phone)
                ->whereNull('verified_at')
                ->orderBy('created_at', 'desc')
                ->first()
                ?->increment('attempts');

            return [
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ];
        }

        // Check attempts
        if ($otp->attempts >= 5) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new OTP.'
            ];
        }

        // Mark as verified
        $otp->update(['verified_at' => now()]);

        return [
            'success' => true,
            'message' => 'OTP verified successfully'
        ];
    }

    /**
     * Send SMS via provider.
     */
    protected function sendSMS($phone, $code)
    {
        // In development, just log the OTP
        if (config('app.env') === 'local') {
            Log::info('OTP for ' . $phone . ': ' . $code);
            return true;
        }

        // Example SMS integration (replace with your provider)
        try {
            // Option 1: Twilio
            // $twilio = new \Twilio\Rest\Client(
            //     config('services.twilio.sid'),
            //     config('services.twilio.token')
            // );
            // $twilio->messages->create($phone, [
            //     'from' => config('services.twilio.from'),
            //     'body' => 'Your Alert.az verification code is: ' . $code
            // ]);

            // Option 2: Local SMS provider API
            $response = Http::post('https://sms-provider.az/api/send', [
                'api_key' => config('services.sms.api_key'),
                'to' => $phone,
                'text' => 'Alert.az təsdiq kodunuz: ' . $code,
                'from' => 'Alert.az'
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired OTPs.
     */
    public function cleanupExpiredOTPs()
    {
        return OTPVerification::where('expires_at', '<', now()->subHours(24))
            ->delete();
    }
}