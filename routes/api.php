<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PersonalAlertController;
use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AlertParseController;
use App\Http\Controllers\Api\SmsApiController;
use App\Http\Controllers\Api\ClientSchemaController;
use App\Http\Controllers\Api\CampaignContactController;
use App\Http\Controllers\Api\SegmentController;
use App\Http\Controllers\Api\SavedSegmentController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\UserClientController;

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

    // SMS API Routes
    Route::prefix('sms')->group(function () {
        Route::post('/send', [SmsApiController::class, 'send']);
        Route::get('/balance', [SmsApiController::class, 'getBalance']);
        Route::get('/history', [SmsApiController::class, 'history']);
        Route::get('/messages/{id}', [SmsApiController::class, 'show']);
    });

    // User Project Management (SMS Campaign Projects)
    Route::prefix('projects')->group(function () {
        Route::get('/', [UserClientController::class, 'index']);
        Route::post('/', [UserClientController::class, 'store']);
        Route::get('/{id}', [UserClientController::class, 'show']);
        Route::put('/{id}', [UserClientController::class, 'update']);
        Route::delete('/{id}', [UserClientController::class, 'destroy']);
        Route::post('/{id}/regenerate-token', [UserClientController::class, 'regenerateToken']);
    });

    // Balance Management Routes
    Route::post('/balance/add', function (Request $request) {
        $user = $request->user();
        $amount = $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Amount must be greater than 0'
            ], 400);
        }

        $user->addBalance($amount);

        return response()->json([
            'status' => 'success',
            'message' => 'Balance added successfully',
            'data' => [
                'balance' => (float) $user->fresh()->balance,
                'amount_added' => (float) $amount
            ]
        ]);
    });
});

// Public notification routes (no auth required)
// VAPID public key needs to be accessible before user subscribes
Route::get('/notifications/vapid-public-key', [NotificationController::class, 'getVapidPublicKey']);

// SMS Webhook (public - for delivery reports from QuickSMS)
Route::post('/webhooks/sms/delivery', [SmsApiController::class, 'handleWebhook']);

// Campaign Management API (Client Token Authentication)
Route::middleware('auth.client')->group(function () {
    // Available Senders for the user
    Route::get('/senders', [CampaignController::class, 'getSenders']);

    // Schema Management
    Route::post('/clients/schema', [ClientSchemaController::class, 'register']);
    Route::get('/clients/schema', [ClientSchemaController::class, 'get']);

    // Contact Management
    Route::post('/contacts/sync', [CampaignContactController::class, 'sync']);
    Route::post('/contacts/sync/bulk', [CampaignContactController::class, 'bulkSync']);
    Route::get('/contacts', [CampaignContactController::class, 'index']);
    Route::post('/contacts', [CampaignContactController::class, 'store']);
    Route::put('/contacts/{phone}', [CampaignContactController::class, 'update']);
    Route::delete('/contacts/{phone}', [CampaignContactController::class, 'destroy']);
    Route::post('/contacts/bulk-delete', [CampaignContactController::class, 'bulkDestroy']);
    Route::get('/contacts/export', [CampaignContactController::class, 'export']);

    // Segment Builder
    Route::get('/segments/attributes', [SegmentController::class, 'getAttributes']);
    Route::post('/segments/preview', [SegmentController::class, 'preview']);
    Route::post('/segments/validate', [SegmentController::class, 'validate']);

    // Saved Segments
    Route::get('/saved-segments', [SavedSegmentController::class, 'index']);
    Route::post('/saved-segments', [SavedSegmentController::class, 'store']);
    Route::get('/saved-segments/{id}', [SavedSegmentController::class, 'show']);
    Route::put('/saved-segments/{id}', [SavedSegmentController::class, 'update']);
    Route::delete('/saved-segments/{id}', [SavedSegmentController::class, 'destroy']);

    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);
    Route::post('/campaigns/{id}/cancel', [CampaignController::class, 'cancel']);
    Route::get('/campaigns/{id}/stats', [CampaignController::class, 'stats']);
    Route::post('/campaigns/{id}/execute', [CampaignController::class, 'execute']);
    Route::post('/campaigns/{id}/execute-test', [CampaignController::class, 'executeTest']);
    Route::get('/campaigns/{id}/preview', [CampaignController::class, 'preview']);
    Route::get('/campaigns/{id}/planned', [CampaignController::class, 'planned']);
    Route::post('/campaigns/{id}/validate', [CampaignController::class, 'validate']);
    Route::get('/campaigns/{id}/messages', [CampaignController::class, 'messages']);
    Route::post('/campaigns/{id}/duplicate', [CampaignController::class, 'duplicate']);
    Route::post('/campaigns/{id}/activate', [CampaignController::class, 'activate']);
    Route::post('/campaigns/{id}/pause', [CampaignController::class, 'pause']);
    Route::post('/campaigns/{id}/test-send', [CampaignController::class, 'testSend']);
    Route::post('/campaigns/{id}/test-send-custom', [CampaignController::class, 'testSendCustom']);
    Route::post('/campaigns/{id}/retry-failed', [CampaignController::class, 'retryFailed']);
});

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
