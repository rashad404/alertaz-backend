<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get VAPID public key for client-side push subscription.
     */
    public function getVapidPublicKey()
    {
        $publicKey = config('services.push.public_key', env('VAPID_PUBLIC_KEY'));

        if (!$publicKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'VAPID keys not configured',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'public_key' => $publicKey,
            ],
        ]);
    }

    /**
     * Subscribe to push notifications.
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Check if subscription already exists
            $existing = PushSubscription::where('endpoint', $request->endpoint)->first();

            if ($existing) {
                // Update existing subscription
                $existing->update([
                    'user_id' => $user->id,
                    'public_key' => $request->input('keys.p256dh'),
                    'auth_token' => $request->input('keys.auth'),
                    'user_agent' => $request->header('User-Agent'),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Subscription updated successfully',
                    'data' => [
                        'subscription_id' => $existing->id,
                    ],
                ]);
            }

            // Create new subscription
            $subscription = PushSubscription::create([
                'user_id' => $user->id,
                'endpoint' => $request->endpoint,
                'public_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
                'user_agent' => $request->header('User-Agent'),
            ]);

            Log::info("Push subscription created for user {$user->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Subscribed to push notifications successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Push subscription error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to subscribe to push notifications',
            ], 500);
        }
    }

    /**
     * Unsubscribe from push notifications.
     */
    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            $deleted = PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $request->endpoint)
                ->delete();

            if ($deleted) {
                Log::info("Push subscription deleted for user {$user->id}");

                return response()->json([
                    'status' => 'success',
                    'message' => 'Unsubscribed successfully',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Push unsubscribe error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to unsubscribe',
            ], 500);
        }
    }

    /**
     * Get notification history for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $perPage = $request->input('per_page', 20);
            $type = $request->input('type'); // filter by type (push, email, sms, etc.)

            $query = NotificationLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($type) {
                $query->where('type', $type);
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            Log::error('Get notifications error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notifications',
            ], 500);
        }
    }

    /**
     * Get unread notification count.
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $user = $request->user();

            $count = NotificationLog::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'unread_count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get unread count error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get unread count',
            ], 500);
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();

            $notification = NotificationLog::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Mark as read error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();

            $updated = NotificationLog::where('user_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read',
                'data' => [
                    'updated_count' => $updated,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mark all as read error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read',
            ], 500);
        }
    }

    /**
     * Send a test push notification to the authenticated user.
     */
    public function sendTestNotification(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user has any push subscriptions
            $subscriptions = PushSubscription::where('user_id', $user->id)->get();

            if ($subscriptions->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No push subscriptions found. Please enable push notifications first.',
                ], 400);
            }

            // Create test notification payload
            $payload = [
                'title' => '🔔 Test Notification',
                'body' => 'This is a test push notification from Alert.az! If you can see this, push notifications are working perfectly.',
                'icon' => '/icon-192.png',
                'badge' => '/badge-72.png',
                'tag' => 'test-' . time(),
                'timestamp' => time() * 1000,
                'requireInteraction' => false,
                'data' => [
                    'type' => 'test',
                    'url' => '/alerts',
                    'timestamp' => now()->toIso8601String(),
                ],
            ];

            // Check if mock mode is enabled
            $isMockMode = config('app.notifications_mock', env('NOTIFICATIONS_MOCK_MODE', false));

            if ($isMockMode) {
                // In mock mode, just log to database
                Log::info("🔔 [MOCK] Test push notification to user {$user->id}");

                NotificationLog::create([
                    'user_id' => $user->id,
                    'type' => 'push',
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                    'data' => $payload['data'],
                    'is_mock' => true,
                    'is_read' => false,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Test notification logged successfully! (Mock mode: notification logged to database but not sent)',
                    'data' => [
                        'sent_to' => $subscriptions->count() . ' device(s)',
                        'mocked' => true,
                    ],
                ]);
            }

            // Real mode: Send to all subscriptions
            $pushChannel = app(\App\Services\Notifications\PushChannel::class);
            $sentCount = 0;
            $errors = [];

            foreach ($subscriptions as $subscription) {
                // Use reflection to access private sendToSubscription method
                // Or we can inline the logic here
                try {
                    $webPush = new \Minishlink\WebPush\WebPush([
                        'VAPID' => [
                            'subject' => config('app.url', 'https://alert.az'),
                            'publicKey' => config('services.push.public_key', env('VAPID_PUBLIC_KEY')),
                            'privateKey' => config('services.push.private_key', env('VAPID_PRIVATE_KEY')),
                        ],
                    ]);

                    $sub = \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => [
                            'p256dh' => $subscription->public_key,
                            'auth' => $subscription->auth_token,
                        ],
                        'contentEncoding' => 'aesgcm',
                    ]);

                    $report = $webPush->sendOneNotification(
                        $sub,
                        json_encode($payload),
                        ['TTL' => 86400]
                    );

                    if ($report->isSuccess()) {
                        $sentCount++;
                    } else {
                        $errors[] = $report->getReason();
                        // Remove expired subscription
                        if ($report->getStatusCode() === 410) {
                            $subscription->delete();
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to send test notification: " . $e->getMessage());
                    $errors[] = $e->getMessage();
                }
            }

            // Log to database
            NotificationLog::create([
                'user_id' => $user->id,
                'type' => 'push',
                'title' => $payload['title'],
                'body' => $payload['body'],
                'data' => array_merge($payload['data'], [
                    'sent_count' => $sentCount,
                    'total_subscriptions' => $subscriptions->count(),
                ]),
                'is_mock' => false,
                'is_read' => false,
            ]);

            if ($sentCount === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send test notification: ' . implode(', ', $errors),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Test notification sent successfully! Check your browser for the notification.',
                'data' => [
                    'sent_to' => $sentCount . ' device(s)',
                    'total_subscriptions' => $subscriptions->count(),
                    'mocked' => false,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Send test notification error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}
