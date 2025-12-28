<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use App\Services\VerificationService;

class AuthController extends Controller
{
    protected $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Register with email and password.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'locale' => 'in:az,en,ru',
            'timezone' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'provider' => 'email',
            'locale' => $request->locale ?? 'az',
            'timezone' => $request->timezone ?? 'Asia/Baku',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'return_url' => $request->return_url ?? null,
            ]
        ], 201);
    }

    /**
     * Login with email and password.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'return_url' => $request->return_url ?? null,
            ]
        ]);
    }

    /**
     * Send OTP to phone number.
     */
    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^(\+994)?[0-9]{9,12}$/',
            'purpose' => 'in:login,verify'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        // Normalize phone number
        if (!str_starts_with($phone, '+')) {
            $phone = '+994' . ltrim($phone, '0');
        }

        // Check if user exists
        $user = User::where('phone', $phone)->first();

        // Send OTP using verification service
        $result = $this->verificationService->sendSMSVerification($phone, $user);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'phone' => $phone,
                    'expires_in' => $result['expires_in'] ?? 600,
                    'debug' => $result['debug'] ?? null, // Include debug info in local mode
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
    }

    /**
     * Verify OTP and login/register.
     */
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^(\+994)?[0-9]{9,12}$/',
            'code' => 'required|string|min:4|max:6',
            'name' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        // Normalize phone number
        if (!str_starts_with($phone, '+')) {
            $phone = '+994' . ltrim($phone, '0');
        }

        $code = $request->code;

        // Verify OTP using verification service
        $result = $this->verificationService->verifyCode($phone, $code, 'sms');

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
                'debug' => $result['debug'] ?? null, // Include debug info in local mode
            ], 400);
        }

        // Find or create user
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $user = User::create([
                'phone' => $phone,
                'name' => $request->name ?? 'User',
                'provider' => 'phone',
                'phone_verified_at' => now(),
                'locale' => $request->locale ?? 'az',
                'timezone' => $request->timezone ?? 'Asia/Baku',
            ]);
        } else {
            $user->update([
                'phone_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Phone verified successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'return_url' => $request->return_url ?? null,
                'debug' => $result['debug'] ?? null, // Include debug info in local mode
            ]
        ]);
    }

    /**
     * Verify phone for authenticated user.
     * Updates the current user's phone and marks it as verified.
     */
    public function verifyPhoneForUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^(\+994)?[0-9]{9,12}$/',
            'code' => 'required|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        if (!str_starts_with($phone, '+')) {
            $phone = '+994' . ltrim($phone, '0');
        }

        $result = $this->verificationService->verifyCode($phone, $request->code, 'sms');

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 400);
        }

        // Update the authenticated user's phone
        $user = $request->user();
        $user->update([
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);
        $user->refresh();
        $user->available_notification_channels = $user->getAvailableNotificationChannels();

        return response()->json([
            'status' => 'success',
            'message' => 'Phone verified successfully',
            'data' => [
                'user' => $user,
            ]
        ]);
    }

    /**
     * Send email verification code.
     */
    public function sendEmailVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;

        // Check if user exists
        $user = User::where('email', $email)->first();

        // Send verification code using verification service
        $result = $this->verificationService->sendEmailVerification($email, $user);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'email' => $email,
                    'expires_in' => $result['expires_in'] ?? 900,
                    'debug' => $result['debug'] ?? null, // Include debug info in local mode
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
    }

    /**
     * Verify email code.
     */
    public function verifyEmailCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'code' => 'required|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $code = $request->code;

        // Verify code using verification service
        $result = $this->verificationService->verifyCode($email, $code, 'email');

        Log::info('Email verification attempt', [
            'email' => $email,
            'code' => $code,
            'result' => $result,
            'is_authenticated' => Auth::check()
        ]);

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
                'debug' => $result['debug'] ?? null, // Include debug info in local mode
            ], 400);
        }

        // Update user email verification status if logged in
        if (Auth::check()) {
            $user = Auth::user();

            Log::info('Updating user email verification', [
                'user_id' => $user->id,
                'email_before' => $user->email,
                'email_verified_at_before' => $user->email_verified_at
            ]);

            $user->email_verified_at = now();
            $user->save();

            // Reload user to ensure we have fresh data including available channels
            $user->refresh();
            $user->available_notification_channels = $user->getAvailableNotificationChannels();

            Log::info('User email verified', [
                'user_id' => $user->id,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'available_channels' => $user->available_notification_channels
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Email verified successfully',
                'data' => [
                    'user' => $user,
                    'debug' => $result['debug'] ?? null, // Include debug info in local mode
                ]
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully',
            'data' => [
                'email' => $email,
                'debug' => $result['debug'] ?? null, // Include debug info in local mode
            ]
        ]);
    }

    /**
     * Resend verification code.
     */
    public function resendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'type' => 'required|in:sms,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = $request->identifier;
        $type = $request->type;

        // Normalize phone number if SMS
        if ($type === 'sms' && !str_starts_with($identifier, '+')) {
            $identifier = '+994' . ltrim($identifier, '0');
        }

        // Resend code using verification service
        $result = $this->verificationService->resendCode($identifier, $type);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'identifier' => $identifier,
                    'type' => $type,
                    'expires_in' => $result['expires_in'] ?? ($type === 'sms' ? 600 : 900),
                    'debug' => $result['debug'] ?? null, // Include debug info in local mode
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
            'data' => [
                'retry_after' => $result['retry_after'] ?? null,
            ]
        ], 400);
    }

    /**
     * Redirect to OAuth provider.
     */
    public function redirectToProvider($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provider not supported'
            ], 400);
        }

        $returnUrl = request()->query('return_url');

        if ($returnUrl) {
            session(['return_url' => $returnUrl]);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle OAuth callback.
     */
    public function handleProviderCallback($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provider not supported'
            ], 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed'
            ], 401);
        }

        // Find or create user
        $user = User::where($provider . '_id', $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                $provider . '_id' => $socialUser->getId(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'email_verified_at' => now(),
                'locale' => 'az',
                'timezone' => 'Asia/Baku',
            ]);
        } else {
            // Update provider info if needed
            $user->update([
                $provider . '_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        }

        Auth::login($user);
        $token = $user->createToken('auth-token')->plainTextToken;

        // Get return URL from session
        $returnUrl = session('return_url', '/dashboard');
        session()->forget('return_url');

        // Redirect to frontend with token
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return redirect($frontendUrl . '/auth/callback?token=' . $token . '&return_url=' . urlencode($returnUrl));
    }

    /**
     * Get current user.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->available_notification_channels = $user->getAvailableNotificationChannels();

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|regex:/^\+994[0-9]{9}$/|unique:users,phone,' . $user->id,
            'telegram_chat_id' => 'string|nullable',
            'whatsapp_number' => 'string|nullable',
            'slack_webhook' => 'url|nullable',
            'push_token' => 'string|nullable',
            'notification_preferences' => 'array|nullable',
            'timezone' => 'string',
            'locale' => 'in:az,en,ru',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if email is being changed
        $emailChanged = $request->has('email') && $request->email !== $user->email;

        $user->update($request->only([
            'name',
            'email',
            'phone',
            'telegram_chat_id',
            'whatsapp_number',
            'slack_webhook',
            'push_token',
            'notification_preferences',
            'timezone',
            'locale',
        ]));

        // Clear email verification if email was changed
        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
        }

        $user->available_notification_channels = $user->getAvailableNotificationChannels();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'new_password_confirmation' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user has a password (not OAuth user)
        if (!$user->password) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot change password for OAuth users'
            ], 400);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }
}