<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAlert;
use App\Models\AlertType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonalAlertController extends Controller
{
    /**
     * Get all alert types.
     */
    public function getAlertTypes()
    {
        $alertTypes = AlertType::active()->get();

        // Add available assets for each type
        $alertTypes->map(function ($type) {
            $type->assets = $type->getAvailableAssets();
            $type->operators = $type->getAvailableOperators();
            return $type;
        });

        return response()->json([
            'status' => 'success',
            'data' => $alertTypes
        ]);
    }

    /**
     * Validate user notification channels.
     */
    public function validateChannels(Request $request)
    {
        $user = $request->user();
        $requestedChannels = $request->input('channels', []);

        $validation = [];

        foreach ($requestedChannels as $channel) {
            $validation[$channel] = [
                'available' => $user->hasNotificationChannel($channel),
                'status' => $user->hasNotificationChannel($channel) ? 'ready' : 'missing',
                'message' => $this->getChannelMessage($channel, $user)
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'channels' => $validation,
                'available_channels' => $user->getAvailableNotificationChannels(),
                'all_channels_ready' => count(array_filter($validation, fn($v) => $v['status'] === 'ready')) === count($requestedChannels)
            ]
        ]);
    }

    /**
     * Get user's personal alerts.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $alerts = PersonalAlert::where('user_id', $user->id)
            ->with('alertType')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $alerts
        ]);
    }

    /**
     * Get single alert details.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $alert = PersonalAlert::where('user_id', $user->id)
            ->with(['alertType', 'history' => function ($query) {
                $query->orderBy('triggered_at', 'desc')->limit(10);
            }])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $alert
        ]);
    }

    /**
     * Create new personal alert.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'alert_type_id' => 'required|exists:alert_types,id',
            'name' => 'required|string|max:255',
            'asset' => 'string|nullable',
            'conditions' => 'required|array',
            'conditions.field' => 'required|string',
            'conditions.operator' => 'required|in:equals,greater,greater_equal,less,less_equal,not_equals',
            'conditions.value' => 'required',
            'notification_channels' => 'required|array|min:1',
            'notification_channels.*' => 'in:email,sms,telegram,whatsapp,slack,push',
            'check_frequency' => 'integer|min:60|max:86400',
            'is_recurring' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify user has all requested notification channels
        $requestedChannels = $request->input('notification_channels', []);
        $availableChannels = $user->getAvailableNotificationChannels();
        $missingChannels = array_diff($requestedChannels, $availableChannels);

        if (!empty($missingChannels)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Some notification channels are not configured',
                'missing_channels' => $missingChannels
            ], 400);
        }

        // Create the alert
        $alert = PersonalAlert::create([
            'user_id' => $user->id,
            'alert_type_id' => $request->alert_type_id,
            'name' => $request->name,
            'asset' => $request->asset,
            'conditions' => $request->conditions,
            'notification_channels' => $request->notification_channels,
            'check_frequency' => $request->check_frequency ?? 300,
            'is_active' => true,
            'is_recurring' => $request->is_recurring ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Alert created successfully',
            'data' => $alert
        ], 201);
    }

    /**
     * Update personal alert.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $alert = PersonalAlert::where('user_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'conditions' => 'array',
            'conditions.field' => 'string',
            'conditions.operator' => 'in:equals,greater,greater_equal,less,less_equal,not_equals',
            'notification_channels' => 'array|min:1',
            'notification_channels.*' => 'in:email,sms,telegram,whatsapp,slack,push',
            'check_frequency' => 'integer|min:60|max:86400',
            'is_active' => 'boolean',
            'is_recurring' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify notification channels if updated
        if ($request->has('notification_channels')) {
            $requestedChannels = $request->input('notification_channels', []);
            $availableChannels = $user->getAvailableNotificationChannels();
            $missingChannels = array_diff($requestedChannels, $availableChannels);

            if (!empty($missingChannels)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some notification channels are not configured',
                    'missing_channels' => $missingChannels
                ], 400);
            }
        }

        $alert->update($request->only([
            'name',
            'conditions',
            'notification_channels',
            'check_frequency',
            'is_active',
            'is_recurring',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Alert updated successfully',
            'data' => $alert
        ]);
    }

    /**
     * Toggle alert active status.
     */
    public function toggle(Request $request, $id)
    {
        $user = $request->user();

        $alert = PersonalAlert::where('user_id', $user->id)->findOrFail($id);

        $alert->update(['is_active' => !$alert->is_active]);

        return response()->json([
            'status' => 'success',
            'message' => 'Alert status updated',
            'data' => $alert
        ]);
    }

    /**
     * Delete personal alert.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $alert = PersonalAlert::where('user_id', $user->id)->findOrFail($id);

        $alert->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Alert deleted successfully'
        ]);
    }

    /**
     * Get channel-specific message.
     */
    private function getChannelMessage($channel, $user)
    {
        switch ($channel) {
            case 'email':
                if (empty($user->email)) {
                    return 'Please add your email address';
                }
                if (!$user->email_verified_at) {
                    return 'Please verify your email address';
                }
                return 'Email notifications will be sent to: ' . $user->email;

            case 'sms':
                if (empty($user->phone)) {
                    return 'Please add your phone number';
                }
                if (!$user->phone_verified_at) {
                    return 'Please verify your phone number';
                }
                return 'SMS notifications will be sent to: ' . $user->phone;

            case 'telegram':
                if (empty($user->telegram_chat_id)) {
                    return 'Please connect your Telegram account';
                }
                return 'Telegram notifications are enabled';

            case 'whatsapp':
                if (empty($user->whatsapp_number)) {
                    return 'Please add your WhatsApp number';
                }
                return 'WhatsApp notifications will be sent to: ' . $user->whatsapp_number;

            case 'slack':
                if (empty($user->slack_webhook)) {
                    return 'Please configure Slack webhook';
                }
                return 'Slack notifications are enabled';

            case 'push':
                if (empty($user->push_token)) {
                    return 'Please enable push notifications in your browser/app';
                }
                return 'Push notifications are enabled';

            default:
                return 'Channel not configured';
        }
    }
}