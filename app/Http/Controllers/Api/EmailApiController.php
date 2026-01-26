<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailApiController extends Controller
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send email message
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => ['required', 'email', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:500'],
            'body_html' => ['required_without:body_text', 'nullable', 'string', 'max:100000'],
            'body_text' => ['required_without:body_html', 'nullable', 'string', 'max:50000'],
            'from' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Build HTML from text if only text provided
        $bodyHtml = $request->input('body_html');
        $bodyText = $request->input('body_text');

        if (!$bodyHtml && $bodyText) {
            $bodyHtml = '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($bodyText))
                . '</div>';
        }

        // Get client from middleware (if authenticated via client token)
        $client = $request->attributes->get('client');
        $clientId = $client ? $client->id : null;

        // If no client from token, try to get user's default client
        if (!$clientId) {
            $defaultClient = $user->clients()->first();
            $clientId = $defaultClient ? $defaultClient->id : null;
        }

        $result = $this->emailService->send(
            user: $user,
            toEmail: $request->input('to'),
            subject: $request->input('subject'),
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            toName: $request->input('to_name'),
            fromEmail: $request->input('from'),
            fromName: $request->input('from_name'),
            source: 'api',
            clientId: $clientId
        );

        if (!$result['success']) {
            // Handle insufficient balance
            if (($result['error_code'] ?? null) === 'insufficient_balance') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance',
                    'code' => 'INSUFFICIENT_BALANCE',
                    'current_balance' => number_format($result['current_balance'] ?? 0, 2, '.', ''),
                    'required' => number_format($result['required_amount'] ?? 0, 2, '.', ''),
                ], 402);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send email',
                'code' => 'EMAIL_SEND_FAILED',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email sent successfully',
            'data' => [
                'message_id' => $result['message_id'] ?? null,
                'to' => $request->input('to'),
                'subject' => $request->input('subject'),
                'cost' => number_format($result['cost'] ?? 0, 2, '.', ''),
                'remaining_balance' => number_format($result['new_balance'] ?? 0, 2, '.', ''),
                'is_test' => $result['is_test'] ?? false,
            ],
        ], 200);
    }

    /**
     * Get user email balance info
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
                'cost_per_email' => number_format($this->emailService->getCostPerEmail(), 2, '.', ''),
            ],
        ], 200);
    }

    /**
     * Get email message history with filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $query = Message::email()->forUser($user->id);

        // Filter by source
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        // Filter by client/project ID
        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        // Filter by recipient email (partial match)
        if ($email = $request->input('email')) {
            $query->where('recipient', 'like', "%{$email}%");
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
            ->with(['client:id,name'])
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
     * Get specific email message details
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $message = Message::email()
            ->forUser($user->id)
            ->where('id', $id)
            ->first();

        if (!$message) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email message not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $message,
        ], 200);
    }
}
