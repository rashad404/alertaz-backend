<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hybrid authentication middleware for SMS/Email API
 *
 * Supports two authentication methods:
 * 1. Sanctum session tokens (for dashboard logged-in users)
 * 2. Client permanent API tokens (for partner services like kimlik.az)
 *
 * Partner tokens are permanent SHA256 hashes stored in clients.api_token
 * and do NOT change on each login.
 */
class AuthenticateSmsApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required. Provide Bearer token.',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        // Try 1: Check if it's a permanent client API token (SHA256 hash, 64 chars)
        if (strlen($token) === 64 && ctype_xdigit($token)) {
            $client = Client::where('api_token', $token)
                ->where('status', 'active')
                ->with('user')
                ->first();

            if ($client && $client->user) {
                // Set the user for controllers that call $request->user()
                Auth::setUser($client->user);

                // Also attach client info for reference
                $request->attributes->set('client', $client);
                $request->attributes->set('auth_method', 'client_token');

                return $next($request);
            }
        }

        // Try 2: Check if it's a Sanctum session token (format: "id|token")
        if (str_contains($token, '|')) {
            // Use Sanctum's guard to authenticate
            $user = Auth::guard('sanctum')->user();

            if ($user) {
                Auth::setUser($user);
                $request->attributes->set('auth_method', 'sanctum');

                return $next($request);
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid API token',
            'code' => 'UNAUTHORIZED',
        ], 401);
    }
}
