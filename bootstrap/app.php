<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function ($router) {
            Route::middleware('api')
                ->prefix('api/admin')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Remove Sanctum middleware from API routes to fix CSRF issues
        // API routes are public and don't need stateful authentication

        $middleware->alias([
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'auth.client' => \App\Http\Middleware\AuthenticateClient::class,
        ]);

        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Configure API authentication to return JSON instead of redirecting
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Return null for API routes to avoid redirect
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
