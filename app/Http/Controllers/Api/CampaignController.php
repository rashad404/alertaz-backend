<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\SavedSegment;
use App\Models\SMSMessage;
use App\Models\UserSender;
use App\Services\SegmentQueryBuilder;
use App\Services\CampaignExecutionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignController extends Controller
{
    protected SegmentQueryBuilder $queryBuilder;
    protected CampaignExecutionEngine $executionEngine;

    public function __construct(
        SegmentQueryBuilder $queryBuilder,
        CampaignExecutionEngine $executionEngine
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->executionEngine = $executionEngine;
    }

    /**
     * List campaigns
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $perPage = $request->input('per_page', 20);
        $status = $request->input('status'); // Optional filter

        $query = Campaign::where('client_id', $client->id);

        if ($status) {
            $query->where('status', $status);
        }

        $campaigns = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'campaigns' => $campaigns->items(),
                'pagination' => [
                    'current_page' => $campaigns->currentPage(),
                    'last_page' => $campaigns->lastPage(),
                    'per_page' => $campaigns->perPage(),
                    'total' => $campaigns->total(),
                ],
            ],
        ], 200);
    }

    /**
     * Get single campaign
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'campaign' => $campaign,
            ],
        ], 200);
    }

    /**
     * Get available senders for the user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSenders(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $senders = UserSender::getAvailableSenders($client->user_id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'senders' => $senders,
                'default' => UserSender::DEFAULT_SENDER,
            ],
        ], 200);
    }

    /**
     * Create campaign
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        // Get available senders for validation
        $availableSenders = UserSender::getAvailableSenders($client->user_id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'sender' => ['required', 'string', 'max:11', 'in:' . implode(',', $availableSenders)],
            'message_template' => ['required', 'string', 'max:500'],
            'segment_filter' => ['required', 'array'],
            'segment_filter.logic' => ['nullable', 'in:AND,OR'],
            'segment_filter.conditions' => ['required', 'array', 'min:1'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'is_test' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Calculate target count
        $segmentFilter = $request->input('segment_filter');
        $targetCount = $this->queryBuilder->countMatches($client->id, $segmentFilter);

        if ($targetCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No contacts match the segment filter',
            ], 422);
        }

        // Create campaign
        $campaign = Campaign::create([
            'client_id' => $client->id,
            'name' => $request->input('name'),
            'sender' => $request->input('sender'),
            'message_template' => $request->input('message_template'),
            'status' => $request->input('scheduled_at') ? 'scheduled' : 'draft',
            'segment_filter' => $segmentFilter,
            'scheduled_at' => $request->input('scheduled_at'),
            'target_count' => $targetCount,
            'created_by' => $client->user_id,
            'is_test' => $request->input('is_test', false),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign created successfully',
            'data' => [
                'campaign' => $campaign,
            ],
        ], 201);
    }

    /**
     * Update campaign (only drafts can be updated)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Only draft campaigns can be updated
        if ($campaign->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft campaigns can be updated',
            ], 422);
        }

        // Get available senders for validation
        $availableSenders = UserSender::getAvailableSenders($client->user_id);

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:11', 'in:' . implode(',', $availableSenders)],
            'message_template' => ['nullable', 'string', 'max:500'],
            'segment_filter' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'is_test' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update fields
        if ($request->has('name')) {
            $campaign->name = $request->input('name');
        }

        if ($request->has('sender')) {
            $campaign->sender = $request->input('sender');
        }

        if ($request->has('message_template')) {
            $campaign->message_template = $request->input('message_template');
        }

        if ($request->has('segment_filter')) {
            $campaign->segment_filter = $request->input('segment_filter');
            // Recalculate target count
            $campaign->target_count = $this->queryBuilder->countMatches(
                $client->id,
                $campaign->segment_filter
            );
        }

        if ($request->has('scheduled_at')) {
            $campaign->scheduled_at = $request->input('scheduled_at');
            if ($campaign->scheduled_at) {
                $campaign->status = 'scheduled';
            }
        }

        if ($request->has('is_test')) {
            $campaign->is_test = $request->input('is_test');
        }

        $campaign->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign updated successfully',
            'data' => [
                'campaign' => $campaign,
            ],
        ], 200);
    }

    /**
     * Delete campaign (only drafts can be deleted)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Only draft campaigns can be deleted
        if (!in_array($campaign->status, ['draft', 'cancelled'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft or cancelled campaigns can be deleted',
            ], 422);
        }

        $campaign->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign deleted successfully',
        ], 200);
    }

    /**
     * Cancel scheduled campaign
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Only scheduled campaigns can be cancelled
        if ($campaign->status !== 'scheduled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only scheduled campaigns can be cancelled',
            ], 422);
        }

        $campaign->status = 'cancelled';
        $campaign->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign cancelled successfully',
            'data' => [
                'campaign' => $campaign,
            ],
        ], 200);
    }

    /**
     * Get campaign statistics
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        $stats = [
            'target_count' => $campaign->target_count,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'failed_count' => $campaign->failed_count,
            'total_cost' => (float) $campaign->total_cost,
            'delivery_rate' => $campaign->sent_count > 0
                ? round(($campaign->delivered_count / $campaign->sent_count) * 100, 2)
                : 0,
            'success_rate' => $campaign->target_count > 0
                ? round(($campaign->delivered_count / $campaign->target_count) * 100, 2)
                : 0,
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'campaign' => $campaign,
                'stats' => $stats,
            ],
        ], 200);
    }

    /**
     * Execute campaign (send SMS)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Check if global test mode is enabled
        $globalTestMode = config('services.quicksms.test_mode', false);
        $isTestMode = $campaign->is_test || $globalTestMode;

        // Validate campaign (skip balance check for test campaigns or global test mode)
        $errors = $this->executionEngine->validateCampaign($campaign, $isTestMode);
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign validation failed',
                'errors' => $errors,
            ], 422);
        }

        // Execute campaign (use test mode if is_test flag is set or global test mode)
        $result = $this->executionEngine->execute($campaign, $isTestMode);

        // Build appropriate message based on test mode
        $message = 'Campaign executed successfully';
        if ($result['global_test_mode'] ?? false) {
            $message = 'Campaign executed in TEST MODE (global). No SMS was actually sent.';
        } elseif ($result['mock_mode'] ?? false) {
            $message = 'Campaign executed in TEST MODE. No SMS was actually sent.';
        }

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $result,
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign execution failed',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }
    }

    /**
     * Execute campaign in test mode (mock sending)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function executeTest(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Execute in test mode
        $result = $this->executionEngine->executeTest($campaign);

        return response()->json([
            'status' => 'success',
            'message' => 'Test execution completed',
            'data' => $result,
        ], 200);
    }

    /**
     * Preview campaign messages
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');
        $limit = $request->input('limit', 5);

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        $previews = $this->executionEngine->previewMessages($campaign, $limit);

        return response()->json([
            'status' => 'success',
            'data' => [
                'previews' => $previews,
                'campaign' => $campaign,
            ],
        ], 200);
    }

    /**
     * Validate campaign
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function validate(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        $errors = $this->executionEngine->validateCampaign($campaign);

        if (empty($errors)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Campaign is valid and ready to execute',
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign validation failed',
                'errors' => $errors,
            ], 422);
        }
    }

    /**
     * Get campaign message history
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');
        $perPage = $request->input('per_page', 20);

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        $messages = SMSMessage::where('campaign_id', $id)
            ->with('contact:id,phone,attributes')
            ->orderBy('created_at', 'desc')
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
}
