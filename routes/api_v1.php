<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ServiceTypeController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\QuickSendController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\TemplateController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Clean architecture API routes for Alert.az
| All routes require client token authentication
|
*/

Route::middleware('auth.client')->prefix('v1')->group(function () {
    // Service Types (Schema Management)
    Route::prefix('service-types')->group(function () {
        Route::get('/', [ServiceTypeController::class, 'index']);
        Route::post('/', [ServiceTypeController::class, 'store']);
        Route::get('/{key}', [ServiceTypeController::class, 'show']);
        Route::put('/{key}', [ServiceTypeController::class, 'update']);
        Route::delete('/{key}', [ServiceTypeController::class, 'destroy']);
    });

    // Customers (Partner's users)
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/sync', [CustomerController::class, 'sync']);
        Route::get('/{identifier}', [CustomerController::class, 'show']);
        Route::put('/{identifier}', [CustomerController::class, 'upsert']);
        Route::delete('/{identifier}', [CustomerController::class, 'destroy']);
        Route::post('/bulk-delete', [CustomerController::class, 'bulkDestroy']);
    });

    // Services (per type)
    Route::prefix('services/{type}')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::get('/stats', [ServiceController::class, 'stats']);
        Route::post('/sync', [ServiceController::class, 'sync']);
        Route::get('/{id}', [ServiceController::class, 'show'])->whereNumber('id');
        Route::put('/{externalId}', [ServiceController::class, 'upsert']);
        Route::delete('/{id}', [ServiceController::class, 'destroy'])->whereNumber('id');
        Route::post('/bulk-delete', [ServiceController::class, 'bulkDestroy']);
    });

    // Quick Send
    Route::prefix('send')->group(function () {
        // Preview message
        Route::post('/preview', [QuickSendController::class, 'preview']);

        // Send to customer
        Route::post('/customer/{customerId}', [QuickSendController::class, 'sendToCustomer'])
            ->whereNumber('customerId');

        // Send to service
        Route::post('/service/{type}/{serviceId}', [QuickSendController::class, 'sendToService'])
            ->whereNumber('serviceId');

        // Bulk send
        Route::post('/bulk/customers', [QuickSendController::class, 'bulkSendToCustomers']);
        Route::post('/bulk/services/{type}', [QuickSendController::class, 'bulkSendToServices']);
    });

    // Campaigns
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index']);
        Route::post('/', [CampaignController::class, 'store']);
        Route::get('/{id}', [CampaignController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [CampaignController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [CampaignController::class, 'destroy'])->whereNumber('id');

        // Campaign actions
        Route::get('/{id}/preview', [CampaignController::class, 'preview'])->whereNumber('id');
        Route::get('/{id}/stats', [CampaignController::class, 'stats'])->whereNumber('id');
        Route::post('/{id}/execute', [CampaignController::class, 'execute'])->whereNumber('id');
        Route::post('/{id}/activate', [CampaignController::class, 'activate'])->whereNumber('id');
        Route::post('/{id}/pause', [CampaignController::class, 'pause'])->whereNumber('id');
        Route::post('/{id}/duplicate', [CampaignController::class, 'duplicate'])->whereNumber('id');
    });

    // Templates
    Route::prefix('templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index']);
        Route::post('/', [TemplateController::class, 'store']);
        Route::get('/{id}', [TemplateController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [TemplateController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [TemplateController::class, 'destroy'])->whereNumber('id');
    });
});
