<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\SavedSegment;
use App\Models\SmsMessage;
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

        $channel = $request->input('channel', 'sms');
        $maxMessageLength = config('app.sms_max_message_length', 500);

        // Build validation rules based on channel
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'in:sms,email,both'],
            'segment_filter' => ['required', 'array'],
            'segment_filter.logic' => ['nullable', 'in:AND,OR'],
            'segment_filter.conditions' => ['required', 'array', 'min:1'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'is_test' => ['nullable', 'boolean'],
            // Automated campaign fields
            'type' => ['nullable', 'in:one_time,automated'],
            'check_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'], // max 1 week
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'run_start_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'run_end_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
        ];

        // SMS-specific validation
        if ($channel === 'sms' || $channel === 'both') {
            $rules['sender'] = ['required', 'string', 'max:11', 'in:' . implode(',', $availableSenders)];
            $rules['message_template'] = ['required', 'string', 'max:' . $maxMessageLength];
        } else {
            $rules['sender'] = ['nullable', 'string', 'max:255']; // Used as from_name for email
            $rules['message_template'] = ['nullable', 'string', 'max:' . $maxMessageLength];
        }

        // Email-specific validation
        if ($channel === 'email' || $channel === 'both') {
            $rules['email_subject_template'] = ['required', 'string', 'max:500'];
            $rules['email_body_template'] = ['required', 'string', 'max:50000'];
        } else {
            $rules['email_subject_template'] = ['nullable', 'string', 'max:500'];
            $rules['email_body_template'] = ['nullable', 'string', 'max:50000'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for Unicode characters in message_template (only GSM-7 allowed for SMS)
        if (($channel === 'sms' || $channel === 'both') && $request->has('message_template')) {
            $messageTemplate = $request->input('message_template');
            if (!empty($messageTemplate) && mb_strlen($messageTemplate) !== strlen($messageTemplate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SMS message contains special characters. Only Latin characters are allowed for SMS.',
                ], 422);
            }
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

        // Determine campaign type
        $type = $request->input('type', 'one_time');
        $isAutomated = $type === 'automated';

        // Validate automated campaign requires check_interval_minutes
        if ($isAutomated && !$request->input('check_interval_minutes')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Automated campaigns require check_interval_minutes',
            ], 422);
        }

        // Determine initial status
        $status = 'draft';
        if (!$isAutomated && $request->input('scheduled_at')) {
            $status = 'scheduled';
        }

        // Create campaign
        $campaign = Campaign::create([
            'client_id' => $client->id,
            'name' => $request->input('name'),
            'channel' => $channel,
            'sender' => $request->input('sender'),
            'message_template' => $request->input('message_template'),
            'email_subject_template' => $request->input('email_subject_template'),
            'email_body_template' => $request->input('email_body_template'),
            'status' => $status,
            'type' => $type,
            'check_interval_minutes' => $isAutomated ? $request->input('check_interval_minutes') : null,
            'cooldown_days' => $request->input('cooldown_days', 30),
            'ends_at' => $isAutomated ? $request->input('ends_at') : null,
            'run_start_hour' => $isAutomated ? $request->input('run_start_hour') : null,
            'run_end_hour' => $isAutomated ? $request->input('run_end_hour') : null,
            'segment_filter' => $segmentFilter,
            'scheduled_at' => !$isAutomated ? $request->input('scheduled_at') : null,
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

        // Only draft or paused campaigns can be updated
        if (!in_array($campaign->status, ['draft', 'paused'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft or paused campaigns can be updated',
            ], 422);
        }

        // Get available senders for validation
        $availableSenders = UserSender::getAvailableSenders($client->user_id);

        // Determine channel (use existing if not changing)
        $channel = $request->input('channel', $campaign->channel ?? 'sms');

        $maxMessageLength = config('app.sms_max_message_length', 500);
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'in:sms,email,both'],
            'sender' => ['nullable', 'string', 'max:255'],
            'message_template' => ['nullable', 'string', 'max:' . $maxMessageLength],
            'email_subject_template' => ['nullable', 'string', 'max:500'],
            'email_body_template' => ['nullable', 'string', 'max:50000'],
            'segment_filter' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'is_test' => ['nullable', 'boolean'],
            // Automated campaign fields
            'check_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'run_start_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'run_end_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for Unicode characters in message_template (only GSM-7 allowed for SMS)
        if ($request->has('message_template') && ($channel === 'sms' || $channel === 'both')) {
            $messageTemplate = $request->input('message_template');
            if (!empty($messageTemplate) && mb_strlen($messageTemplate) !== strlen($messageTemplate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SMS message contains special characters. Only Latin characters are allowed for SMS.',
                ], 422);
            }
        }

        // Update fields
        if ($request->has('name')) {
            $campaign->name = $request->input('name');
        }

        if ($request->has('channel')) {
            $campaign->channel = $request->input('channel');
        }

        if ($request->has('sender')) {
            $campaign->sender = $request->input('sender');
        }

        if ($request->has('message_template')) {
            $campaign->message_template = $request->input('message_template');
        }

        if ($request->has('email_subject_template')) {
            $campaign->email_subject_template = $request->input('email_subject_template');
        }

        if ($request->has('email_body_template')) {
            $campaign->email_body_template = $request->input('email_body_template');
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

        // Automated campaign specific fields
        if ($campaign->type === 'automated') {
            if ($request->has('check_interval_minutes')) {
                $campaign->check_interval_minutes = $request->input('check_interval_minutes');
            }

            if ($request->has('cooldown_days')) {
                $campaign->cooldown_days = $request->input('cooldown_days');
            }

            if ($request->has('ends_at')) {
                $campaign->ends_at = $request->input('ends_at');
            }

            $runHoursChanged = false;

            if ($request->has('run_start_hour')) {
                if ($campaign->run_start_hour !== $request->input('run_start_hour')) {
                    $runHoursChanged = true;
                }
                $campaign->run_start_hour = $request->input('run_start_hour');
            }

            if ($request->has('run_end_hour')) {
                if ($campaign->run_end_hour !== $request->input('run_end_hour')) {
                    $runHoursChanged = true;
                }
                $campaign->run_end_hour = $request->input('run_end_hour');
            }

            // Recalculate next_run_at if run hours changed and campaign is active
            if ($runHoursChanged && $campaign->status === Campaign::STATUS_ACTIVE) {
                $campaign->next_run_at = $campaign->calculateNextRunTime(now());
            }
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

        // SMS stats
        $smsStats = [
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'failed_count' => $campaign->failed_count,
            'total_cost' => (float) $campaign->total_cost,
            'delivery_rate' => $campaign->sent_count > 0
                ? round(($campaign->delivered_count / $campaign->sent_count) * 100, 2)
                : 0,
        ];

        // Email stats
        $emailStats = [
            'sent_count' => $campaign->email_sent_count ?? 0,
            'delivered_count' => $campaign->email_delivered_count ?? 0,
            'failed_count' => $campaign->email_failed_count ?? 0,
            'total_cost' => (float) ($campaign->email_total_cost ?? 0),
            'delivery_rate' => ($campaign->email_sent_count ?? 0) > 0
                ? round((($campaign->email_delivered_count ?? 0) / $campaign->email_sent_count) * 100, 2)
                : 0,
        ];

        // Combined stats
        $totalSent = $campaign->sent_count + ($campaign->email_sent_count ?? 0);
        $totalDelivered = $campaign->delivered_count + ($campaign->email_delivered_count ?? 0);
        $totalFailed = $campaign->failed_count + ($campaign->email_failed_count ?? 0);
        $totalCost = (float) $campaign->total_cost + (float) ($campaign->email_total_cost ?? 0);

        $stats = [
            'target_count' => $campaign->target_count,
            'sent_count' => $totalSent,
            'delivered_count' => $totalDelivered,
            'failed_count' => $totalFailed,
            'total_cost' => $totalCost,
            'delivery_rate' => $totalSent > 0
                ? round(($totalDelivered / $totalSent) * 100, 2)
                : 0,
            'success_rate' => $campaign->target_count > 0
                ? round(($totalDelivered / $campaign->target_count) * 100, 2)
                : 0,
            'sms' => $smsStats,
            'email' => $emailStats,
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

        $previewData = $this->executionEngine->previewMessages($campaign, $limit);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_count' => $previewData['total_count'],
                'previews' => $previewData['previews'],
                'campaign' => $campaign,
            ],
        ], 200);
    }

    /**
     * Get planned messages for next campaign run (contacts matching filter - cooldown)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function planned(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 10);

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        $plannedData = $this->executionEngine->getPlannedMessages($campaign, $page, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $plannedData,
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
     * Duplicate campaign (create a copy as draft)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function duplicate(Request $request, int $id): JsonResponse
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

        // Recalculate target count (contacts may have changed)
        $targetCount = $this->queryBuilder->countMatches($client->id, $campaign->segment_filter);

        // Create a copy as draft (includes automated campaign settings)
        $newCampaign = Campaign::create([
            'client_id' => $client->id,
            'name' => $campaign->name . ' (copy)',
            'channel' => $campaign->channel ?? 'sms',
            'sender' => $campaign->sender,
            'message_template' => $campaign->message_template,
            'email_subject_template' => $campaign->email_subject_template,
            'email_body_template' => $campaign->email_body_template,
            'status' => 'draft',
            'type' => $campaign->type,
            'segment_filter' => $campaign->segment_filter,
            'scheduled_at' => null,
            'target_count' => $targetCount,
            'created_by' => $client->user_id,
            'is_test' => $campaign->is_test,
            // Automated campaign settings
            'check_interval_minutes' => $campaign->check_interval_minutes,
            'cooldown_days' => $campaign->cooldown_days,
            'ends_at' => null, // Reset end date for new campaign
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign duplicated successfully',
            'data' => [
                'campaign' => $newCampaign,
            ],
        ], 201);
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

        $messages = SmsMessage::where('campaign_id', $id)
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

    /**
     * Activate an automated campaign (start running it)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');
        $confirmed = $request->input('confirm', false);

        $campaign = Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$campaign) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign not found',
            ], 404);
        }

        // Only automated campaigns can be activated
        if ($campaign->type !== 'automated') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only automated campaigns can be activated',
            ], 422);
        }

        // Only draft or paused campaigns can be activated
        if (!in_array($campaign->status, ['draft', 'paused'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft or paused campaigns can be activated',
            ], 422);
        }

        // Validate template with a sample contact
        $sampleContact = $this->queryBuilder->getMatches($client->id, $campaign->segment_filter, 1)->first();
        if ($sampleContact) {
            $templateRenderer = app(\App\Services\TemplateRenderer::class);
            try {
                $templateRenderer->renderStrict($campaign->message_template, $sampleContact);
            } catch (\App\Exceptions\TemplateRenderException $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Template validation failed',
                    'code' => 'TEMPLATE_VALIDATION_FAILED',
                    'data' => [
                        'unresolved_variables' => $e->getUnresolvedVariables(),
                        'error' => $e->getMessage(),
                    ],
                ], 422);
            }
        }

        // Get cost estimate
        $costEstimate = $campaign->estimateTotalCost();
        $user = $campaign->getOwnerUser();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign owner not found',
            ], 500);
        }

        // Check if test mode (skip balance check)
        $globalTestMode = config('services.quicksms.test_mode', false);
        $isTestMode = $campaign->is_test || $globalTestMode;

        if (!$isTestMode) {
            // Check balance
            $estimatedCost = $costEstimate['estimated_total_cost'];
            $currentBalance = (float) $user->balance;

            if ($currentBalance < $estimatedCost) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance',
                    'code' => 'INSUFFICIENT_BALANCE',
                    'data' => [
                        'target_count' => $costEstimate['target_count'],
                        'segments_per_message' => $costEstimate['segments_per_message'],
                        'estimated_cost' => $estimatedCost,
                        'current_balance' => $currentBalance,
                        'shortfall' => round($estimatedCost - $currentBalance, 2),
                    ],
                ], 402);
            }

            // Large campaign confirmation (1000+ contacts)
            $confirmationThreshold = config('app.campaign_confirmation_threshold', 1000);
            if ($campaign->target_count >= $confirmationThreshold && !$confirmed) {
                return response()->json([
                    'status' => 'confirmation_required',
                    'message' => "This campaign will send to {$campaign->target_count} contacts. Please confirm to proceed.",
                    'code' => 'CONFIRMATION_REQUIRED',
                    'data' => [
                        'target_count' => $costEstimate['target_count'],
                        'segments_per_message' => $costEstimate['segments_per_message'],
                        'estimated_cost' => $estimatedCost,
                        'current_balance' => $currentBalance,
                        'confirmation_threshold' => $confirmationThreshold,
                    ],
                ], 412);
            }
        }

        // Activate the campaign
        $campaign->activate();

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign activated successfully',
            'data' => [
                'campaign' => $campaign->fresh(),
                'cost_estimate' => $costEstimate,
            ],
        ], 200);
    }

    /**
     * Pause an automated campaign
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function pause(Request $request, int $id): JsonResponse
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

        // Only automated campaigns can be paused
        if ($campaign->type !== 'automated') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only automated campaigns can be paused',
            ], 422);
        }

        // Only active campaigns can be paused
        if ($campaign->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only active campaigns can be paused',
            ], 422);
        }

        // Pause the campaign
        $campaign->pause();

        return response()->json([
            'status' => 'success',
            'message' => 'Campaign paused successfully',
            'data' => [
                'campaign' => $campaign->fresh(),
            ],
        ], 200);
    }

    /**
     * Test send to X customers (partial send preview)
     * Sends real SMS to first N matching contacts
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function testSend(Request $request, int $id): JsonResponse
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

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $count = $request->input('count');
        $user = $campaign->getOwnerUser();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign owner not found',
            ], 500);
        }

        // Get matching contacts
        $contacts = $this->queryBuilder->getMatches(
            $client->id,
            $campaign->segment_filter,
            $count
        );

        if ($contacts->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No contacts match the segment filter',
            ], 422);
        }

        $templateRenderer = app(\App\Services\TemplateRenderer::class);
        $smsService = app(\App\Services\QuickSmsService::class);
        $costPerSms = config('app.sms_cost_per_message', 0.04);

        // Check if test mode
        $globalTestMode = config('services.quicksms.test_mode', false);

        $results = [];
        $totalCost = 0;
        $sentCount = 0;
        $failedCount = 0;

        foreach ($contacts as $contact) {
            // Render message
            try {
                $message = $templateRenderer->renderStrict($campaign->message_template, $contact);
            } catch (\App\Exceptions\TemplateRenderException $e) {
                $results[] = [
                    'phone' => $contact->phone,
                    'message' => null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $failedCount++;
                continue;
            }

            $message = $templateRenderer->sanitizeForSMS($message);
            $segments = $templateRenderer->calculateSMSSegments($message);
            $cost = $segments * $costPerSms;

            // Check balance
            if (!$globalTestMode && $user->balance < $cost) {
                $results[] = [
                    'phone' => $contact->phone,
                    'message' => $message,
                    'status' => 'failed',
                    'error' => 'Insufficient balance',
                ];
                $failedCount++;
                continue;
            }

            // Send SMS
            if ($globalTestMode) {
                $status = 'sent';
                $error = null;
            } else {
                $unicode = $smsService->requiresUnicode($message);
                $result = $smsService->sendSMS($contact->phone, $message, $campaign->sender, $unicode);

                if ($result['success']) {
                    $status = 'sent';
                    $error = null;
                    $user->deductBalance($cost);
                    $totalCost += $cost;

                    // Record in sms_messages as test
                    \App\Models\SmsMessage::create([
                        'user_id' => $campaign->created_by,
                        'source' => 'campaign',
                        'client_id' => $campaign->client_id,
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'phone' => $contact->phone,
                        'message' => $message,
                        'sender' => $campaign->sender,
                        'cost' => $cost,
                        'status' => 'sent',
                        'is_test' => true,
                        'provider_transaction_id' => $result['transaction_id'] ?? null,
                        'sent_at' => now(),
                    ]);

                    // Mark contact as sent so they won't receive duplicate when campaign runs
                    \App\Models\CampaignContactLog::recordSend($campaign->id, $contact->id);
                } else {
                    $status = 'failed';
                    $error = $result['error_message'] ?? 'Unknown error';
                }
            }

            $results[] = [
                'phone' => $contact->phone,
                'message' => $message,
                'segments' => $segments,
                'cost' => $cost,
                'status' => $status,
                'error' => $error,
            ];

            if ($status === 'sent') {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Test send completed: {$sentCount} sent, {$failedCount} failed",
            'data' => [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'total_cost' => round($totalCost, 2),
                'messages' => $results,
                'is_test_mode' => $globalTestMode,
            ],
        ], 200);
    }

    /**
     * Test send to custom phone number
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function testSendCustom(Request $request, int $id): JsonResponse
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

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^994[0-9]{9}$/'],
            'sample_contact_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        $sampleContactId = $request->input('sample_contact_id');
        $user = $campaign->getOwnerUser();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign owner not found',
            ], 500);
        }

        // Get sample contact for attributes
        if ($sampleContactId) {
            $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                ->where('id', $sampleContactId)
                ->first();
        } else {
            // Use first matching contact
            $sampleContact = $this->queryBuilder->getMatches(
                $client->id,
                $campaign->segment_filter,
                1
            )->first();
        }

        if (!$sampleContact) {
            return response()->json([
                'status' => 'error',
                'message' => 'No sample contact found for template rendering',
            ], 422);
        }

        $templateRenderer = app(\App\Services\TemplateRenderer::class);
        $smsService = app(\App\Services\QuickSmsService::class);
        $costPerSms = config('app.sms_cost_per_message', 0.04);

        // Render message
        try {
            $message = $templateRenderer->renderStrict($campaign->message_template, $sampleContact);
        } catch (\App\Exceptions\TemplateRenderException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template rendering failed',
                'code' => 'TEMPLATE_ERROR',
                'data' => [
                    'unresolved_variables' => $e->getUnresolvedVariables(),
                ],
            ], 422);
        }

        $message = $templateRenderer->sanitizeForSMS($message);
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * $costPerSms;

        // Check if test mode
        $globalTestMode = config('services.quicksms.test_mode', false);

        // Check balance
        if (!$globalTestMode && $user->balance < $cost) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance',
                'code' => 'INSUFFICIENT_BALANCE',
                'data' => [
                    'required' => $cost,
                    'available' => (float) $user->balance,
                ],
            ], 402);
        }

        // Send SMS
        if ($globalTestMode) {
            $status = 'sent';
            $error = null;
            $transactionId = 'test_' . time();
        } else {
            $unicode = $smsService->requiresUnicode($message);
            $result = $smsService->sendSMS($phone, $message, $campaign->sender, $unicode);

            if ($result['success']) {
                $status = 'sent';
                $error = null;
                $transactionId = $result['transaction_id'] ?? null;
                $user->deductBalance($cost);

                // Record in sms_messages as test
                \App\Models\SmsMessage::create([
                    'user_id' => $campaign->created_by,
                    'source' => 'campaign',
                    'client_id' => $campaign->client_id,
                    'campaign_id' => $campaign->id,
                    'phone' => $phone,
                    'message' => $message,
                    'sender' => $campaign->sender,
                    'cost' => $cost,
                    'status' => 'sent',
                    'is_test' => true,
                    'provider_transaction_id' => $transactionId,
                    'sent_at' => now(),
                ]);
            } else {
                $status = 'failed';
                $error = $result['error_message'] ?? 'Unknown error';
                $transactionId = null;
            }
        }

        return response()->json([
            'status' => $status === 'sent' ? 'success' : 'error',
            'message' => $status === 'sent' ? 'Test SMS sent successfully' : 'Failed to send test SMS',
            'data' => [
                'phone' => $phone,
                'message' => $message,
                'segments' => $segments,
                'cost' => $cost,
                'status' => $status,
                'error' => $error,
                'sample_contact_id' => $sampleContact->id,
                'is_test_mode' => $globalTestMode,
            ],
        ], $status === 'sent' ? 200 : 500);
    }

    /**
     * Retry failed messages (only temporary errors)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function retryFailed(Request $request, int $id): JsonResponse
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

        $smsService = app(\App\Services\QuickSmsService::class);

        // Get failed messages that are retryable (not permanent errors)
        $failedMessages = \App\Models\SmsMessage::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->whereNotNull('contact_id')
            ->get();

        if ($failedMessages->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No failed messages to retry',
                'data' => [
                    'queued' => 0,
                    'skipped' => 0,
                ],
            ], 200);
        }

        $queued = 0;
        $skipped = 0;
        $skippedReasons = [];

        foreach ($failedMessages as $msg) {
            // Check if it's a permanent error (should not retry)
            // We check error_message for patterns indicating permanent failure
            $errorMessage = $msg->error_message ?? '';
            $isPermanent = str_contains($errorMessage, 'Invalid phone') ||
                          str_contains($errorMessage, 'blacklisted') ||
                          str_contains($errorMessage, 'Invalid sender') ||
                          str_contains($errorMessage, 'Template error');

            if ($isPermanent) {
                $skipped++;
                $skippedReasons[] = [
                    'phone' => $msg->phone,
                    'reason' => 'Permanent error: ' . $errorMessage,
                ];
                continue;
            }

            // Get the contact
            $contact = \App\Models\Contact::find($msg->contact_id);
            if (!$contact) {
                $skipped++;
                $skippedReasons[] = [
                    'phone' => $msg->phone,
                    'reason' => 'Contact not found',
                ];
                continue;
            }

            // Dispatch new job
            \App\Jobs\SendCampaignMessage::dispatch($campaign, $contact);
            $queued++;

            // Mark original message as retried
            $msg->update([
                'error_message' => $msg->error_message . ' [Retry queued at ' . now()->format('Y-m-d H:i:s') . ']',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Retry queued: {$queued} messages, skipped: {$skipped}",
            'data' => [
                'queued' => $queued,
                'skipped' => $skipped,
                'skipped_details' => $skippedReasons,
            ],
        ], 200);
    }
}
