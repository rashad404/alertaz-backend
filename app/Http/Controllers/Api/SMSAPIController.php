<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\UserAllowedSender;
use App\Services\QuickSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SmsApiController extends Controller
{
    private QuickSmsService $smsService;

    public function __construct(QuickSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send SMS message
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^994[0-9]{9}$/'],
            'message' => ['required', 'string', 'max:1000'],
            'sender' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $phone = $request->input('phone');
        $message = $request->input('message');
        $sender = $request->input('sender');

        // Determine sender
        $senderToUse = $this->determineSender($user->id, $sender);

        if ($senderToUse === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sender not allowed. You do not have permission to use this sender.',
                'code' => 'SENDER_NOT_ALLOWED',
            ], 403);
        }

        // Get SMS cost
        $cost = config('app.sms_cost_per_message', 0.04);

        // Check user balance
        if (!$user->hasEnoughBalance($cost)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance',
                'code' => 'INSUFFICIENT_BALANCE',
                'current_balance' => number_format($user->balance, 2, '.', ''),
                'required' => number_format($cost, 2, '.', ''),
            ], 402);
        }

        // Check if Unicode is needed
        $unicode = $this->smsService->requiresUnicode($message);

        // Create SMS record
        $smsMessage = SmsMessage::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'message' => $message,
            'sender' => $senderToUse,
            'cost' => $cost,
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        // Send SMS via QuickSMS
        $result = $this->smsService->sendSMS($phone, $message, $senderToUse, $unicode);

        if ($result['success']) {
            // Deduct from user balance
            $user->deductBalance($cost);

            // Update SMS record
            $smsMessage->markAsSent($result['transaction_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'SMS sent successfully',
                'data' => [
                    'id' => $smsMessage->id,
                    'transaction_id' => $result['transaction_id'],
                    'phone' => $phone,
                    'sender' => $senderToUse,
                    'cost' => number_format($cost, 2, '.', ''),
                    'remaining_balance' => number_format($user->fresh()->balance, 2, '.', ''),
                ],
            ], 200);
        }

        // Failed to send
        $smsMessage->markAsFailed($result['error_message'], $result['error_code'] ?? null);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send SMS',
            'code' => 'SMS_SEND_FAILED',
            'error' => $result['error_message'] ?? 'Unknown error',
        ], 500);
    }

    /**
     * Get user balance
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBalance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'balance' => number_format($user->balance, 2, '.', ''),
                'total_spent' => number_format($user->total_spent, 2, '.', ''),
                'cost_per_sms' => number_format(config('app.sms_cost_per_message', 0.04), 2, '.', ''),
            ],
        ], 200);
    }

    /**
     * Get SMS message history with filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $query = SmsMessage::forUser($user->id);

        // Filter by source (api/campaign)
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        // Filter by campaign ID
        if ($campaignId = $request->input('campaign_id')) {
            $query->where('campaign_id', $campaignId);
        }

        // Filter by client/project ID
        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        // Filter by phone (partial match)
        if ($phone = $request->input('phone')) {
            $query->where('phone', 'like', "%{$phone}%");
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by date range
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $messages = $query
            ->with(['campaign:id,name', 'client:id,name'])
            ->recent()
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'messages' => $messages->items(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ],
        ], 200);
    }

    /**
     * Get specific SMS message details
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $message = SmsMessage::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$message) {
            return response()->json([
                'status' => 'error',
                'message' => 'SMS message not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $message,
        ], 200);
    }

    /**
     * Handle delivery webhook from QuickSMS
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $transactionId = $request->input('trans_id');
        $statusCode = (int) $request->input('status');

        if (!$transactionId || !$statusCode) {
            return response()->json(['status' => 'error', 'message' => 'Invalid webhook data'], 400);
        }

        $message = SmsMessage::where('provider_transaction_id', $transactionId)->first();

        if (!$message) {
            Log::warning('Webhook received for unknown transaction', [
                'transaction_id' => $transactionId,
                'status' => $statusCode,
            ]);
            return response()->json(['status' => 'ok'], 200);
        }

        // Update delivery status based on status code
        if ($statusCode === 101) {
            // Delivered
            $message->markAsDelivered($statusCode);
        } elseif (in_array($statusCode, [102, 103, 104, 105, 106, 109])) {
            // Failed statuses
            $message->markAsFailed('Delivery failed', $statusCode);
        } else {
            // Other statuses (in queue, sent, unknown, etc.)
            $message->update(['delivery_status_code' => $statusCode]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Determine which sender to use
     *
     * @param int $userId
     * @param string|null $requestedSender
     * @return string|null Sender name or null if not allowed
     */
    private function determineSender(int $userId, ?string $requestedSender): ?string
    {
        $defaultSenders = config('app.sms_default_senders', ['Alert.az', 'Sayt.az', 'Task.az']);

        // If no sender requested, use default
        if (empty($requestedSender)) {
            return 'Alert.az';
        }

        // Check if requested sender is a default sender
        if (in_array($requestedSender, $defaultSenders)) {
            return $requestedSender;
        }

        // Check if user has custom sender approved
        $allowed = UserAllowedSender::forUser($userId)
            ->active()
            ->where('sender_name', $requestedSender)
            ->exists();

        if ($allowed) {
            return $requestedSender;
        }

        // Sender not allowed
        return null;
    }
}
