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
use Illuminate\Support\Facades\Http;
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
     * Send phone verification SMS for authenticated user.
     * This is a protected endpoint that charges the user for SMS.
     */
    public function sendPhoneVerificationForUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^(\+994)?[0-9]{9,12}$/',
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

        $user = $request->user();

        // Send OTP using verification service with billing
        $result = $this->verificationService->sendSMSVerification($phone, $user, 'verify');

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'phone' => $phone,
                    'expires_in' => $result['expires_in'] ?? 600,
                    'cost' => $result['cost'] ?? null,
                    'debug' => $result['debug'] ?? null,
                ]
            ]);
        }

        // Handle insufficient balance error
        if (($result['error_code'] ?? null) === 'insufficient_balance') {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
                'error_code' => 'insufficient_balance',
                'data' => [
                    'required_amount' => $result['required_amount'] ?? null,
                    'current_balance' => $result['current_balance'] ?? null,
                ]
            ], 402);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
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
     * Verify email for authenticated user.
     * Updates the current user's email_verified_at.
     */
    public function verifyEmailForUser(Request $request)
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

        $result = $this->verificationService->verifyCode($request->email, $request->code, 'email');

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 400);
        }

        // Update the authenticated user's email verification
        $user = $request->user();
        $user->update([
            'email' => $request->email,
            'email_verified_at' => now(),
        ]);
        $user->refresh();
        $user->available_notification_channels = $user->getAvailableNotificationChannels();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully',
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
     * Handle Wallet.az OAuth callback.
     */
    public function walletCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'code_verifier' => 'required|string',
            'redirect_uri' => 'required|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $walletApiUrl = env('WALLET_API_URL', 'http://100.89.150.50:8011/api');
            $clientId = env('WALLET_CLIENT_ID');
            $clientSecret = env('WALLET_CLIENT_SECRET');

            // Exchange authorization code for tokens
            $tokenResponse = Http::post("{$walletApiUrl}/oauth/token", [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $request->code,
                'redirect_uri' => $request->redirect_uri,
                'code_verifier' => $request->code_verifier,
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Wallet OAuth token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body()
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to exchange authorization code'
                ], 400);
            }

            $tokens = $tokenResponse->json();

            // Fetch user data from Wallet.az
            $userResponse = Http::withToken($tokens['access_token'])
                ->get("{$walletApiUrl}/oauth/user");

            if (!$userResponse->successful()) {
                Log::error('Wallet OAuth user fetch failed', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body()
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to fetch user data'
                ], 400);
            }

            $walletUser = $userResponse->json()['data'];

            // Find or create user in our system
            $user = User::where('wallet_id', $walletUser['id'])
                ->orWhere('email', $walletUser['email'])
                ->first();

            $verification = $walletUser['verification'] ?? [];
            $emailVerified = $verification['email_verified'] ?? false;
            $phoneVerified = $verification['phone_verified'] ?? false;

            // Calculate token expiry (default 30 days if not provided)
            $tokenExpiresAt = now()->addSeconds($tokens['expires_in'] ?? 2592000);

            if (!$user) {
                $user = User::create([
                    'name' => $walletUser['name'],
                    'email' => $walletUser['email'] ?? null,
                    'phone' => $walletUser['phone'] ?? null,
                    'avatar' => $walletUser['avatar'] ?? null,
                    'wallet_id' => $walletUser['id'],
                    'wallet_access_token' => $tokens['access_token'],
                    'wallet_refresh_token' => $tokens['refresh_token'] ?? null,
                    'wallet_token_expires_at' => $tokenExpiresAt,
                    'provider' => 'wallet',
                    'provider_id' => (string) $walletUser['id'],
                    'email_verified_at' => $emailVerified ? now() : null,
                    'phone_verified_at' => $phoneVerified ? now() : null,
                    'locale' => 'az',
                    'timezone' => 'Asia/Baku',
                ]);
            } else {
                // Update user with latest data from Wallet.az
                $user->update([
                    'wallet_id' => $walletUser['id'],
                    'wallet_access_token' => $tokens['access_token'],
                    'wallet_refresh_token' => $tokens['refresh_token'] ?? null,
                    'wallet_token_expires_at' => $tokenExpiresAt,
                    'name' => $walletUser['name'],
                    'avatar' => $walletUser['avatar'] ?? $user->avatar,
                    'phone' => $walletUser['phone'] ?? $user->phone,
                    'email_verified_at' => $emailVerified ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
                    'phone_verified_at' => $phoneVerified ? ($user->phone_verified_at ?? now()) : $user->phone_verified_at,
                ]);
            }

            // Create token for our app
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Wallet OAuth error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync user profile from Wallet.az.
     * Called when user returns from editing profile on Wallet.az.
     */
    public function syncFromWallet(Request $request)
    {
        $user = $request->user();

        // Only for Wallet.az users
        if (!$user->wallet_id || !$user->wallet_access_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not a Wallet.az user'
            ], 400);
        }

        try {
            $walletApiUrl = env('WALLET_API_URL', 'http://100.89.150.50:8011/api');

            // Fetch fresh user data from Wallet.az
            $userResponse = Http::withToken($user->wallet_access_token)
                ->get("{$walletApiUrl}/oauth/user");

            if (!$userResponse->successful()) {
                // Token might be expired, try to refresh
                if ($user->wallet_refresh_token) {
                    $refreshed = $this->refreshWalletToken($user);
                    if (!$refreshed) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Failed to refresh Wallet.az token'
                        ], 401);
                    }

                    // Retry with new token
                    $userResponse = Http::withToken($user->wallet_access_token)
                        ->get("{$walletApiUrl}/oauth/user");

                    if (!$userResponse->successful()) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Failed to fetch user data from Wallet.az'
                        ], 400);
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to fetch user data from Wallet.az'
                    ], 400);
                }
            }

            $walletUser = $userResponse->json()['data'];
            $verification = $walletUser['verification'] ?? [];
            $emailVerified = $verification['email_verified'] ?? false;
            $phoneVerified = $verification['phone_verified'] ?? false;

            // Update user with fresh data from Wallet.az
            $user->update([
                'name' => $walletUser['name'],
                'avatar' => $walletUser['avatar'] ?? $user->avatar,
                'phone' => $walletUser['phone'] ?? $user->phone,
                'email_verified_at' => $emailVerified ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
                'phone_verified_at' => $phoneVerified ? ($user->phone_verified_at ?? now()) : $user->phone_verified_at,
            ]);

            $user->refresh();
            $user->available_notification_channels = $user->getAvailableNotificationChannels();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile synced from Wallet.az',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Wallet sync error', [
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync profile'
            ], 500);
        }
    }

    /**
     * Refresh Wallet.az access token.
     */
    protected function refreshWalletToken(User $user): bool
    {
        try {
            $walletApiUrl = env('WALLET_API_URL', 'http://100.89.150.50:8011/api');
            $clientId = env('WALLET_CLIENT_ID');
            $clientSecret = env('WALLET_CLIENT_SECRET');

            $tokenResponse = Http::post("{$walletApiUrl}/oauth/token", [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $user->wallet_refresh_token,
            ]);

            if (!$tokenResponse->successful()) {
                return false;
            }

            $tokens = $tokenResponse->json();
            $tokenExpiresAt = now()->addSeconds($tokens['expires_in'] ?? 2592000);

            $user->update([
                'wallet_access_token' => $tokens['access_token'],
                'wallet_refresh_token' => $tokens['refresh_token'] ?? $user->wallet_refresh_token,
                'wallet_token_expires_at' => $tokenExpiresAt,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Wallet token refresh failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);
            return false;
        }
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