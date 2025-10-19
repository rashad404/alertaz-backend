<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use App\Services\OTPService;

class AuthController extends Controller
{
    protected $otpService;

    public function __construct(OTPService $otpService)
    {
        $this->otpService = $otpService;
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
            'phone' => 'required|string|regex:/^\+994[0-9]{9}$/',
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
        $purpose = $request->purpose ?? 'login';

        // Generate and send OTP
        $result = $this->otpService->sendOTP($phone, $purpose);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => 'OTP sent successfully',
                'data' => [
                    'phone' => $phone,
                    'expires_in' => 600, // 10 minutes
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
            'phone' => 'required|string|regex:/^\+994[0-9]{9}$/',
            'code' => 'required|string|size:6',
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
        $code = $request->code;

        // Verify OTP
        $result = $this->otpService->verifyOTP($phone, $code);

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message']
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
            ]
        ]);
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
            'phone' => 'string|regex:/^\+994[0-9]{9}$/|unique:users,phone,' . $user->id,
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

        $user->available_notification_channels = $user->getAvailableNotificationChannels();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $user
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