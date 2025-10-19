<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyTypeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\HeroBannerController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\CompanyEavController;
use App\Models\News;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Test route without middleware
Route::get('/test-news', [NewsController::class, 'index']);
Route::get('/test-ads', [AdController::class, 'index']);

// Test with just sanctum
Route::middleware(['auth:sanctum'])->get('/test-news-auth', [NewsController::class, 'index']);

// Debug route without middleware
Route::get('/debug-news', function() {
    $user = auth('sanctum')->user();
    return response()->json([
        'user' => $user,
        'is_admin' => $user ? $user->is_admin : null,
        'news_count' => News::count(),
        'news_paginated' => News::paginate(10),
    ]);
});

// Server time endpoint (no auth required for admin panel to get time)
Route::get('/server-time', function() {
    $now = now();
    return response()->json([
        'datetime' => $now->format('Y-m-d\TH:i'),
        'timezone' => config('app.timezone'),
        'offset' => $now->format('P'),
        'timestamp' => $now->timestamp
    ]);
});

// Protected admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Test route
    Route::get('/test-method', [NewsController::class, 'test']);
    
    // Simple news list
    Route::get('/news-list', function() {
        return News::with('category')->paginate(10);
    });
    
    // Test list method
    Route::get('/news-list-method', [NewsController::class, 'list']);
    
    // News management - rename to avoid conflicts
    Route::get('news-items', [NewsController::class, 'list']);
    Route::post('news-items', [NewsController::class, 'store']);
    Route::get('news-items/{news}', [NewsController::class, 'show']);
    Route::put('news-items/{news}', [NewsController::class, 'update']);
    Route::delete('news-items/{news}', [NewsController::class, 'destroy']);
    Route::post('news-items/{id}/upload-image', [NewsController::class, 'uploadImage']);
    
    // Blog management
    Route::apiResource('blogs', BlogController::class);
    Route::post('blogs/{id}/upload-image', [BlogController::class, 'uploadImage']);
    
    // Category management
    Route::apiResource('categories', CategoryController::class);
    
    // Subscriber management
    Route::get('subscribers', [SubscriberController::class, 'index']);
    Route::post('subscribers', [SubscriberController::class, 'store']);
    Route::get('subscribers/stats', [SubscriberController::class, 'stats']);
    Route::get('subscribers/export', [SubscriberController::class, 'export']);
    Route::get('subscribers/{id}', [SubscriberController::class, 'show']);
    Route::put('subscribers/{id}', [SubscriberController::class, 'update']);
    Route::delete('subscribers/{id}', [SubscriberController::class, 'destroy']);
    
    // User management
    Route::apiResource('users', UserController::class);
    
    // Slider management
    Route::get('sliders', [SliderController::class, 'index']);
    Route::get('sliders/available-news', [SliderController::class, 'availableNews']);
    Route::post('sliders', [SliderController::class, 'store']);
    Route::put('sliders/{id}', [SliderController::class, 'update']);
    Route::delete('sliders/{id}', [SliderController::class, 'destroy']);
    Route::post('sliders/reorder', [SliderController::class, 'reorder']);
    
    // Ads management
    Route::get('ads', [AdController::class, 'index']);
    Route::post('ads', [AdController::class, 'store']);
    Route::get('ads/{id}', [AdController::class, 'show']);
    Route::put('ads/{id}', [AdController::class, 'update']);
    Route::delete('ads/{id}', [AdController::class, 'destroy']);
    Route::post('ads/{id}/upload-image', [AdController::class, 'uploadImage']);
    Route::post('ads/reorder', [AdController::class, 'reorder']);
    Route::patch('ads/{id}/toggle-status', [AdController::class, 'toggleStatus']);
    
    // Hero Banners management
    Route::get('hero-banners', [HeroBannerController::class, 'index']);
    Route::post('hero-banners', [HeroBannerController::class, 'store']);
    Route::get('hero-banners/{id}', [HeroBannerController::class, 'show']);
    Route::put('hero-banners/{id}', [HeroBannerController::class, 'update']);
    Route::delete('hero-banners/{id}', [HeroBannerController::class, 'destroy']);
    Route::post('hero-banners/{id}/upload-image', [HeroBannerController::class, 'uploadImage']);
    Route::post('hero-banners/reorder', [HeroBannerController::class, 'reorder']);
    Route::patch('hero-banners/{id}/toggle-status', [HeroBannerController::class, 'toggleStatus']);
    
    // Company Types management
    Route::get('company-types', [CompanyTypeController::class, 'index']);
    Route::post('company-types', [CompanyTypeController::class, 'store']);
    Route::get('company-types/{id}', [CompanyTypeController::class, 'show']);
    Route::put('company-types/{id}', [CompanyTypeController::class, 'update']);
    Route::delete('company-types/{id}', [CompanyTypeController::class, 'destroy']);
    
    // Companies management
    Route::get('companies', [CompanyController::class, 'index']);
    Route::post('companies', [CompanyController::class, 'store']);
    Route::get('companies/{id}', [CompanyController::class, 'show']);
    Route::put('companies/{id}', [CompanyController::class, 'update']);
    Route::delete('companies/{id}', [CompanyController::class, 'destroy']);
    Route::get('companies/list', [CompanyController::class, 'list']);
    
    // Menu management
    Route::get('menus', [MenuController::class, 'index']);
    Route::post('menus', [MenuController::class, 'store']);
    Route::get('menus/{id}', [MenuController::class, 'show']);
    Route::put('menus/{id}', [MenuController::class, 'update']);
    Route::delete('menus/{id}', [MenuController::class, 'destroy']);
    Route::post('menus/reorder', [MenuController::class, 'reorder']);
    Route::patch('menus/{id}/toggle-status', [MenuController::class, 'toggleStatus']);
    
    // Generic content image upload
    Route::post('/upload-content-image', [NewsController::class, 'uploadContentImage']);
    
    // Current user
    Route::get('/me', [AuthController::class, 'me']);
    
    // EAV Companies management
    Route::get('companies-eav', [CompanyEavController::class, 'index']);
    Route::post('companies-eav', [CompanyEavController::class, 'store']);
    Route::get('companies-eav/types', [CompanyEavController::class, 'getTypes']);
    Route::get('companies-eav/attribute-definitions/{companyTypeId}', [CompanyEavController::class, 'getAttributeDefinitions']);
    Route::get('companies-eav/{id}', [CompanyEavController::class, 'show']);
    Route::put('companies-eav/{id}', [CompanyEavController::class, 'update']);
    Route::delete('companies-eav/{id}', [CompanyEavController::class, 'destroy']);
    Route::post('companies-eav/{id}/toggle-status', [CompanyEavController::class, 'toggleStatus']);
    
    // Company entities
    Route::get('companies-eav/{companyId}/entities', [CompanyEavController::class, 'getEntities']);
    Route::get('companies-eav/{companyId}/entities/{entityId}', [CompanyEavController::class, 'getEntity']);
    Route::post('companies-eav/{companyId}/entities', [CompanyEavController::class, 'createEntity']);
    Route::put('companies-eav/{companyId}/entities/{entityId}', [CompanyEavController::class, 'updateEntity']);
    Route::delete('companies-eav/{companyId}/entities/{entityId}', [CompanyEavController::class, 'deleteEntity']);
});