<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClient
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
                'message' => 'API token required',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $client = \App\Models\Client::where('api_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API token',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        // Attach client to request
        $request->merge(['client' => $client]);
        $request->attributes->set('client', $client);

        return $next($request);
    }
}
