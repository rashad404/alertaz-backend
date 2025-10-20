<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PersonalAlertController;
use App\Http\Controllers\Api\CryptoController;

// Authentication Routes
Route::prefix('auth')->group(function () {
    // Email/Password Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Phone OTP Auth
    Route::post('/otp/send', [AuthController::class, 'sendOTP']);
    Route::post('/otp/verify', [AuthController::class, 'verifyOTP']);

    // Email Verification
    Route::post('/email/send', [AuthController::class, 'sendEmailVerification']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmailCode']);

    // Resend Verification Code (for both SMS and Email)
    Route::post('/resend-code', [AuthController::class, 'resendCode']);

    // Social OAuth
    Route::get('/{provider}', [AuthController::class, 'redirectToProvider'])
        ->where('provider', 'google|facebook');
    Route::get('/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
        ->where('provider', 'google|facebook');
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // User Profile
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Alert Types
    Route::get('/alert-types', [PersonalAlertController::class, 'getAlertTypes']);

    // Personal Alerts
    Route::prefix('alerts')->group(function () {
        Route::get('/', [PersonalAlertController::class, 'index']);
        Route::post('/', [PersonalAlertController::class, 'store']);
        Route::get('/{id}', [PersonalAlertController::class, 'show']);
        Route::put('/{id}', [PersonalAlertController::class, 'update']);
        Route::post('/{id}/toggle', [PersonalAlertController::class, 'toggle']);
        Route::delete('/{id}', [PersonalAlertController::class, 'destroy']);

        // Validate notification channels
        Route::post('/validate-channels', [PersonalAlertController::class, 'validateChannels']);
    });
});

// Cryptocurrency Routes (public)
Route::get('/cryptos', [CryptoController::class, 'getCryptoList']);

// Simple hello world endpoint
Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello from Alert.az API!',
        'status' => 'success',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'app' => config('app.name'),
        'version' => '1.0.0'
    ]);
});

// Language-specific hello endpoint
Route::get('/{locale}/hello', function ($locale) {
    $messages = [
        'az' => 'Salam Alert.az!',
        'en' => 'Hello Alert.az!',
        'ru' => 'Привет Alert.az!'
    ];

    return response()->json([
        'message' => $messages[$locale] ?? $messages['en'],
        'locale' => $locale,
        'status' => 'success'
    ]);
})->where('locale', 'az|en|ru');
