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
use App\Http\Controllers\Api\EmailApiController;
use App\Http\Controllers\Api\ClientSchemaController;
use App\Http\Controllers\Api\CampaignContactController;
use App\Http\Controllers\Api\SegmentController;
use App\Http\Controllers\Api\SavedSegmentController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\UserClientController;
use App\Http\Controllers\Api\WalletController;

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

    // Kimlik.az OAuth
    Route::post('/wallet/callback', [AuthController::class, 'walletCallback']);
});

// Kimlik.az Webhook (public - for payment notifications)
Route::post('/webhooks/wallet', [WalletController::class, 'webhook']);

// Public alert parsing (no auth required for better UX)
Route::post('/alerts/parse', [AlertParseController::class, 'parse']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // User Profile
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::post('/user/send-phone-verification', [AuthController::class, 'sendPhoneVerificationForUser']);
    Route::post('/user/verify-phone', [AuthController::class, 'verifyPhoneForUser']);
    Route::post('/user/verify-email', [AuthController::class, 'verifyEmailForUser']);
    Route::post('/user/sync-from-wallet', [AuthController::class, 'syncFromWallet']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Kimlik.az Top Up
    Route::post('/wallet/topup', [WalletController::class, 'topup']);

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

// Note: SMS/Email API routes moved outside auth:sanctum to use hybrid auth (see below)

    // User Project Management (SMS Campaign Projects)
    Route::prefix('projects')->group(function () {
        Route::get('/', [UserClientController::class, 'index']);
        Route::post('/', [UserClientController::class, 'store']);
        Route::get('/default-token', [UserClientController::class, 'getDefaultToken']);
        Route::get('/{id}', [UserClientController::class, 'show']);
        Route::put('/{id}', [UserClientController::class, 'update']);
        Route::delete('/{id}', [UserClientController::class, 'destroy']);
        Route::post('/{id}/regenerate-token', [UserClientController::class, 'regenerateToken']);

        // Project Data (for frontend dashboard)
        Route::get('/{id}/service-types', [UserClientController::class, 'serviceTypes']);
        Route::get('/{id}/customers', [UserClientController::class, 'customers']);
        Route::get('/{id}/services/{type}/stats', [UserClientController::class, 'serviceStats']);
        Route::get('/{id}/services/{type}', [UserClientController::class, 'services']);
        Route::get('/{id}/campaigns', [UserClientController::class, 'campaigns']);
        Route::get('/{id}/templates', [UserClientController::class, 'templates']);

        // Dashboard Write Operations (Sanctum auth - for dashboard users)
        // Customer actions
        Route::post('/{id}/customers/{customerId}/send', [UserClientController::class, 'sendToCustomer']);
        Route::delete('/{id}/customers/{customerId}', [UserClientController::class, 'deleteCustomer']);
        Route::post('/{id}/customers/bulk-delete', [UserClientController::class, 'bulkDeleteCustomers']);
        Route::post('/{id}/customers/bulk-send', [UserClientController::class, 'bulkSendToCustomers']);

        // Service actions
        Route::post('/{id}/services/{type}/{serviceId}/send', [UserClientController::class, 'sendToService']);
        Route::delete('/{id}/services/{type}/{serviceId}', [UserClientController::class, 'deleteService']);
        Route::post('/{id}/services/{type}/bulk-delete', [UserClientController::class, 'bulkDeleteServices']);
        Route::post('/{id}/services/{type}/bulk-send', [UserClientController::class, 'bulkSendToServices']);

        // Template CRUD
        Route::post('/{id}/templates', [UserClientController::class, 'createTemplate']);
        Route::put('/{id}/templates/{templateId}', [UserClientController::class, 'updateTemplate']);
        Route::delete('/{id}/templates/{templateId}', [UserClientController::class, 'deleteTemplate']);

        // Service Type CRUD
        Route::get('/{id}/service-types/{key}', [UserClientController::class, 'getServiceType']);
        Route::post('/{id}/service-types', [UserClientController::class, 'createServiceType']);
        Route::put('/{id}/service-types/{key}', [UserClientController::class, 'updateServiceType']);
        Route::delete('/{id}/service-types/{key}', [UserClientController::class, 'deleteServiceType']);

        // Quick Send Preview
        Route::post('/{id}/send/preview', [UserClientController::class, 'previewMessage']);

        // Campaign actions
        Route::post('/{id}/campaigns', [UserClientController::class, 'createCampaign']);
        Route::get('/{id}/campaigns/{campaignId}', [UserClientController::class, 'getCampaign']);
        Route::put('/{id}/campaigns/{campaignId}', [UserClientController::class, 'updateCampaign']);
        Route::delete('/{id}/campaigns/{campaignId}', [UserClientController::class, 'deleteCampaign']);
        Route::post('/{id}/campaigns/{campaignId}/execute', [UserClientController::class, 'executeCampaign']);
        Route::post('/{id}/campaigns/{campaignId}/activate', [UserClientController::class, 'activateCampaign']);
        Route::post('/{id}/campaigns/{campaignId}/pause', [UserClientController::class, 'pauseCampaign']);
        Route::post('/{id}/campaigns/{campaignId}/cancel', [UserClientController::class, 'cancelCampaign']);
        Route::post('/{id}/campaigns/{campaignId}/duplicate', [UserClientController::class, 'duplicateCampaign']);
        Route::post('/{id}/campaigns/preview', [UserClientController::class, 'previewCampaign']);
        Route::get('/{id}/campaigns/{campaignId}/preview-messages', [UserClientController::class, 'previewCampaignMessages']);
        Route::get('/{id}/campaigns/{campaignId}/planned', [UserClientController::class, 'plannedCampaignMessages']);
        Route::get('/{id}/campaigns/{campaignId}/messages', [UserClientController::class, 'campaignMessages']);
        Route::post('/{id}/campaigns/{campaignId}/test-send', [UserClientController::class, 'testSendCampaign']);
        Route::post('/{id}/campaigns/{campaignId}/test-send-custom', [UserClientController::class, 'testSendCampaignCustom']);
        Route::post('/{id}/campaigns/{campaignId}/retry-failed', [UserClientController::class, 'retryFailedCampaign']);
        Route::get('/{id}/segment-attributes', [UserClientController::class, 'segmentAttributes']);
        Route::get('/{id}/senders', [UserClientController::class, 'getSenders']);
        Route::get('/{id}/email-senders', [UserClientController::class, 'getEmailSenders']);

        // Messages (Sent Messages history)
        Route::get('/{id}/messages', [UserClientController::class, 'messages']);

        // Customer details with messages
        Route::get('/{id}/customers/{customerId}', [UserClientController::class, 'getCustomer']);
        Route::get('/{id}/customers/{customerId}/messages', [UserClientController::class, 'customerMessages']);
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

// SMS/Email API Routes (supports both Sanctum session tokens AND permanent client tokens)
// This allows partners like kimlik.az to use permanent API tokens
Route::middleware('auth.sms')->group(function () {
    // SMS API Routes
    Route::prefix('sms')->group(function () {
        Route::post('/send', [SmsApiController::class, 'send']);
        Route::get('/balance', [SmsApiController::class, 'getBalance']);
        Route::get('/history', [SmsApiController::class, 'history']);
        Route::get('/messages/{id}', [SmsApiController::class, 'show']);
    });

    // Email API Routes
    Route::prefix('email')->group(function () {
        Route::post('/send', [EmailApiController::class, 'send']);
        Route::get('/balance', [EmailApiController::class, 'getBalance']);
        Route::get('/history', [EmailApiController::class, 'history']);
        Route::get('/messages/{id}', [EmailApiController::class, 'show']);
    });
});

// Campaign Management API (Client Token Authentication)
Route::middleware('auth.client')->group(function () {
    // Available Senders for the user
    Route::get('/senders', [CampaignController::class, 'getSenders']);
    Route::get('/email-senders', [CampaignController::class, 'getEmailSenders']);

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
    Route::post('/segments/preview-messages', [SegmentController::class, 'previewMessages']);
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

// V1 API Routes (Clean Architecture)
Route::prefix('v1')->middleware('auth.client')->group(function () {
    // Service Types
    Route::get('/service-types', [\App\Http\Controllers\Api\V1\ServiceTypeController::class, 'index']);
    Route::post('/service-types', [\App\Http\Controllers\Api\V1\ServiceTypeController::class, 'store']);
    Route::get('/service-types/{key}', [\App\Http\Controllers\Api\V1\ServiceTypeController::class, 'show']);
    Route::put('/service-types/{key}', [\App\Http\Controllers\Api\V1\ServiceTypeController::class, 'update']);
    Route::delete('/service-types/{key}', [\App\Http\Controllers\Api\V1\ServiceTypeController::class, 'destroy']);

    // Customers
    Route::get('/customers', [\App\Http\Controllers\Api\V1\CustomerController::class, 'index']);
    Route::post('/customers', [\App\Http\Controllers\Api\V1\CustomerController::class, 'store']);
    Route::post('/customers/sync', [\App\Http\Controllers\Api\V1\CustomerController::class, 'sync']);
    Route::get('/customers/{id}', [\App\Http\Controllers\Api\V1\CustomerController::class, 'show']);
    Route::put('/customers/{id}', [\App\Http\Controllers\Api\V1\CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [\App\Http\Controllers\Api\V1\CustomerController::class, 'destroy']);

    // Services
    Route::get('/services/{type}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'index']);
    Route::post('/services/{type}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'store']);
    Route::post('/services/{type}/sync', [\App\Http\Controllers\Api\V1\ServiceController::class, 'sync']);
    Route::get('/services/{type}/{id}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'show']);
    Route::put('/services/{type}/{id}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'update']);
    Route::delete('/services/{type}/{id}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'destroy']);
    Route::get('/services/{type}/stats', [\App\Http\Controllers\Api\V1\ServiceController::class, 'stats']);

    // Templates
    Route::get('/templates', [\App\Http\Controllers\Api\V1\TemplateController::class, 'index']);
    Route::post('/templates', [\App\Http\Controllers\Api\V1\TemplateController::class, 'store']);
    Route::get('/templates/{id}', [\App\Http\Controllers\Api\V1\TemplateController::class, 'show']);
    Route::put('/templates/{id}', [\App\Http\Controllers\Api\V1\TemplateController::class, 'update']);
    Route::delete('/templates/{id}', [\App\Http\Controllers\Api\V1\TemplateController::class, 'destroy']);

    // Quick Send
    Route::post('/send/customer/{id}', [\App\Http\Controllers\Api\V1\QuickSendController::class, 'sendToCustomer']);
    Route::post('/send/service/{type}/{id}', [\App\Http\Controllers\Api\V1\QuickSendController::class, 'sendToService']);
    Route::post('/send/bulk', [\App\Http\Controllers\Api\V1\QuickSendController::class, 'sendBulk']);

    // V1 Campaigns
    Route::get('/campaigns', [\App\Http\Controllers\Api\V1\CampaignController::class, 'index']);
    Route::post('/campaigns', [\App\Http\Controllers\Api\V1\CampaignController::class, 'store']);
    Route::get('/campaigns/{id}', [\App\Http\Controllers\Api\V1\CampaignController::class, 'show']);
    Route::put('/campaigns/{id}', [\App\Http\Controllers\Api\V1\CampaignController::class, 'update']);
    Route::delete('/campaigns/{id}', [\App\Http\Controllers\Api\V1\CampaignController::class, 'destroy']);
    Route::post('/campaigns/{id}/execute', [\App\Http\Controllers\Api\V1\CampaignController::class, 'execute']);
    Route::post('/campaigns/{id}/activate', [\App\Http\Controllers\Api\V1\CampaignController::class, 'activate']);
    Route::post('/campaigns/{id}/pause', [\App\Http\Controllers\Api\V1\CampaignController::class, 'pause']);
    Route::post('/campaigns/{id}/cancel', [\App\Http\Controllers\Api\V1\CampaignController::class, 'cancel']);
    Route::post('/campaigns/{id}/duplicate', [\App\Http\Controllers\Api\V1\CampaignController::class, 'duplicate']);
    Route::post('/campaigns/preview', [\App\Http\Controllers\Api\V1\CampaignController::class, 'preview']);
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

// V1 API Routes (Clean Architecture)
require __DIR__ . '/api_v1.php';
