<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PersonalAlertController;
use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AlertParseController;

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

// Public alert parsing (no auth required for better UX)
Route::post('/alerts/parse', [AlertParseController::class, 'parse']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // User Profile
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
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

    // Push Notifications
    Route::prefix('notifications')->group(function () {
        // Push subscription management
        Route::post('/subscribe', [NotificationController::class, 'subscribe']);
        Route::post('/unsubscribe', [NotificationController::class, 'unsubscribe']);

        // Notification history
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);

        // Mark as read
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);

        // Test notification
        Route::post('/test', [NotificationController::class, 'sendTestNotification']);
    });
});

// Public notification routes (no auth required)
// VAPID public key needs to be accessible before user subscribes
Route::get('/notifications/vapid-public-key', [NotificationController::class, 'getVapidPublicKey']);

// Cryptocurrency Routes (public)
Route::get('/cryptos', [CryptoController::class, 'getCryptoList']);

// Stock Routes (public)
Route::prefix('stocks')->group(function () {
    Route::get('/', [StockController::class, 'getStockList']);
    Route::get('/markets', [StockController::class, 'getMarkets']);
    Route::get('/search', [StockController::class, 'searchStocks']);
    Route::get('/top-gainers', [StockController::class, 'getTopGainers']);
    Route::get('/top-losers', [StockController::class, 'getTopLosers']);
    Route::get('/{symbol}', [StockController::class, 'getStockDetails']);
});

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
