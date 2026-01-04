<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    protected string $walletApiUrl;

    public function __construct()
    {
        $this->walletApiUrl = env('WALLET_API_URL', 'http://100.89.150.50:8011/api');
    }

    /**
     * Initiate a topup from Kimlik.az
     */
    public function topup(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000',
        ]);

        $user = $request->user();

        if (!$user->wallet_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You need to connect your Kimlik.az account first'
            ], 400);
        }

        if (!$user->wallet_access_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kimlik.az session expired. Please reconnect your account.',
                'reconnect_required' => true
            ], 401);
        }

        try {
            // Try to refresh token if expired
            if ($user->wallet_token_expires_at && $user->wallet_token_expires_at < now()) {
                $this->refreshWalletToken($user);
            }

            // Create charge request to Kimlik.az
            $response = Http::withToken($user->wallet_access_token)
                ->post("{$this->walletApiUrl}/oauth/charge", [
                    'amount' => $validated['amount'],
                    'description' => 'Top up Alert.az balance',
                    'reference_id' => 'topup_' . $user->id . '_' . time(),
                ]);

            if (!$response->successful()) {
                Log::error('Kimlik.az charge request failed', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                $errorData = $response->json();

                // Check if token expired
                if ($response->status() === 401) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Kimlik.az session expired. Please reconnect your account.',
                        'reconnect_required' => true
                    ], 401);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $errorData['message'] ?? 'Failed to create charge request'
                ], 400);
            }

            $data = $response->json();

            Log::info('Kimlik.az charge created', [
                'user_id' => $user->id,
                'amount' => $validated['amount'],
                'charge_data' => $data
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Kimlik.az topup error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process topup request'
            ], 500);
        }
    }

    /**
     * Webhook handler for Kimlik.az charge events
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature
        $signature = $request->header('X-Wallet-Signature');
        $webhookSecret = env('WALLET_WEBHOOK_SECRET', '');

        if ($webhookSecret) {
            $expectedSignature = hash_hmac('sha256', json_encode($request->all()), $webhookSecret);

            if (!hash_equals($expectedSignature, $signature ?? '')) {
                Log::warning('Invalid webhook signature from Kimlik.az', [
                    'expected' => $expectedSignature,
                    'received' => $signature
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $event = $request->input('event');
        $chargeId = $request->input('charge_id');
        $amount = $request->input('amount');
        $status = $request->input('status');
        $userId = $request->input('user_id');
        $referenceId = $request->input('reference_id');

        Log::info('Kimlik.az webhook received', [
            'event' => $event,
            'charge_id' => $chargeId,
            'amount' => $amount,
            'status' => $status,
            'user_id' => $userId,
            'reference_id' => $referenceId
        ]);

        if ($event === 'charge.completed') {
            // Find user by wallet_id
            $user = User::where('wallet_id', $userId)->first();

            if (!$user) {
                Log::error('Wallet webhook: User not found', ['wallet_id' => $userId]);
                return response()->json(['error' => 'User not found'], 404);
            }

            // Add balance to user
            $user->addBalance((float) $amount);

            Log::info('Balance added to user', [
                'user_id' => $user->id,
                'amount' => $amount,
                'new_balance' => $user->fresh()->balance
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance updated'
            ]);
        }

        if ($event === 'charge.rejected') {
            Log::info('Charge was rejected', [
                'charge_id' => $chargeId,
                'user_id' => $userId
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Rejection noted'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Event received'
        ]);
    }

    /**
     * Refresh Kimlik.az access token
     */
    protected function refreshWalletToken(User $user): bool
    {
        if (!$user->wallet_refresh_token) {
            return false;
        }

        try {
            $response = Http::post("{$this->walletApiUrl}/oauth/token", [
                'grant_type' => 'refresh_token',
                'client_id' => env('WALLET_CLIENT_ID'),
                'refresh_token' => $user->wallet_refresh_token,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to refresh Kimlik.az token', [
                    'user_id' => $user->id,
                    'status' => $response->status()
                ]);
                return false;
            }

            $tokens = $response->json();

            $user->update([
                'wallet_access_token' => $tokens['access_token'],
                'wallet_refresh_token' => $tokens['refresh_token'],
                'wallet_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 2592000),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to refresh Kimlik.az token', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
