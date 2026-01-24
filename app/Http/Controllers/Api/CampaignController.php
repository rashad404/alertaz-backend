<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\SavedSegment;
use App\Models\SmsMessage;
use App\Models\EmailMessage;
use App\Models\UserSender;
use App\Models\UserEmailSender;
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
     * Get available email senders for the user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmailSenders(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $senders = UserEmailSender::getAvailableSenders($client->user_id);
        $default = UserEmailSender::getDefault();

        return response()->json([
            'status' => 'success',
            'data' => [
                'senders' => $senders,
                'default' => $default,
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
        $availableEmailSenders = UserEmailSender::getAvailableSenders($client->user_id);
        $availableEmailAddresses = array_column($availableEmailSenders, 'email');

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
            $rules['sender'] = ['nullable', 'string', 'max:255'];
            $rules['message_template'] = ['nullable', 'string', 'max:' . $maxMessageLength];
        }

        // Email-specific validation
        if ($channel === 'email' || $channel === 'both') {
            $rules['email_sender'] = ['required', 'string', 'in:' . implode(',', $availableEmailAddresses)];
            $rules['email_subject_template'] = ['required', 'string', 'max:500'];
            $rules['email_body_template'] = ['required', 'string', 'max:50000'];
        } else {
            $rules['email_sender'] = ['nullable', 'string'];
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
            'email_sender' => $request->input('email_sender'),
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
        $availableEmailSenders = UserEmailSender::getAvailableSenders($client->user_id);
        $availableEmailAddresses = array_column($availableEmailSenders, 'email');

        // Determine channel (use existing if not changing)
        $channel = $request->input('channel', $campaign->channel ?? 'sms');

        $maxMessageLength = config('app.sms_max_message_length', 500);
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'in:sms,email,both'],
            'sender' => ['nullable', 'string', 'max:255'],
            'email_sender' => ['nullable', 'string', 'in:' . implode(',', $availableEmailAddresses)],
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

        if ($request->has('email_sender')) {
            $campaign->email_sender = $request->input('email_sender');
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

        // Fetch messages based on campaign channel
        $smsMessages = collect();
        $emailMessages = collect();
        $smsPagination = null;
        $emailPagination = null;

        if ($campaign->channel === 'sms' || $campaign->channel === 'both') {
            $smsQuery = SmsMessage::where('campaign_id', $id)
                ->with('contact:id,phone,attributes')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $smsMessages = collect($smsQuery->items())->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'type' => 'sms',
                    'recipient' => $msg->phone,
                    'content' => $msg->message,
                    'status' => $msg->status,
                    'cost' => $msg->cost,
                    'is_test' => $msg->is_test,
                    'sent_at' => $msg->sent_at,
                    'created_at' => $msg->created_at,
                    'contact' => $msg->contact,
                ];
            });
            $smsPagination = $smsQuery;
        }

        if ($campaign->channel === 'email' || $campaign->channel === 'both') {
            $emailQuery = EmailMessage::where('campaign_id', $id)
                ->with('contact:id,phone,attributes')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $emailMessages = collect($emailQuery->items())->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'type' => 'email',
                    'recipient' => $msg->to_email,
                    'content' => $msg->subject,
                    'body' => $msg->body_html,
                    'status' => $msg->status,
                    'cost' => $msg->cost,
                    'is_test' => $msg->is_test,
                    'sent_at' => $msg->sent_at,
                    'created_at' => $msg->created_at,
                    'contact' => $msg->contact,
                ];
            });
            $emailPagination = $emailQuery;
        }

        // Merge and sort by created_at
        $allMessages = $smsMessages->concat($emailMessages)
            ->sortByDesc('created_at')
            ->values()
            ->take($perPage);

        // Calculate combined pagination
        $totalSms = $smsPagination ? $smsPagination->total() : 0;
        $totalEmail = $emailPagination ? $emailPagination->total() : 0;
        $total = $totalSms + $totalEmail;

        return response()->json([
            'status' => 'success',
            'data' => [
                'messages' => $allMessages,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => max(1, ceil($total / $perPage)),
                    'per_page' => $perPage,
                    'total' => $total,
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

        // Build validation rules based on campaign channel
        $rules = [
            'sample_contact_id' => ['nullable', 'integer'],
        ];

        if ($campaign->requiresPhone()) {
            $rules['phone'] = ['nullable', 'string', 'regex:/^994[0-9]{9}$/'];
        }
        if ($campaign->requiresEmail()) {
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        $email = $request->input('email');
        $sampleContactId = $request->input('sample_contact_id');

        // Validate that at least one target is provided based on channel
        if ($campaign->requiresPhone() && $campaign->requiresEmail()) {
            if (empty($phone) && empty($email)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phone or email is required for this campaign',
                ], 422);
            }
        } elseif ($campaign->requiresPhone() && empty($phone)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phone is required for SMS campaigns',
            ], 422);
        } elseif ($campaign->requiresEmail() && empty($email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email is required for email campaigns',
            ], 422);
        }

        $user = $campaign->getOwnerUser();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Campaign owner not found',
            ], 500);
        }

        // Get sample contact for attributes
        $sampleContact = null;

        if ($sampleContactId) {
            // User specified a sample contact to use for template rendering
            $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                ->where('id', $sampleContactId)
                ->first();
        } else {
            // Try to find contact by the custom email/phone provided (email is stored in attributes JSON)
            if ($email) {
                $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.email')) = ?", [$email])
                    ->first();
            }

            if (!$sampleContact && $phone) {
                $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                    ->where('phone', $phone)
                    ->first();
            }

            // If custom email/phone not found in contacts, create temporary sample contact
            if (!$sampleContact) {
                $sampleContact = \App\Models\Contact::createSampleInstance(
                    $client->id,
                    $phone,
                    $email
                );
            }
        }

        if (!$sampleContact) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sample contact not found.',
            ], 422);
        }

        $templateRenderer = app(\App\Services\TemplateRenderer::class);
        $results = [
            'sms' => null,
            'email' => null,
        ];

        // Send SMS if campaign requires phone and phone provided
        if ($campaign->requiresPhone() && $phone) {
            $results['sms'] = $this->sendTestSms($campaign, $user, $sampleContact, $phone, $templateRenderer);
        }

        // Send Email if campaign requires email and email provided
        if ($campaign->requiresEmail() && $email) {
            $results['email'] = $this->sendTestEmail($campaign, $user, $sampleContact, $email, $templateRenderer);
        }

        // Determine overall status
        $allSuccess = true;
        $anySuccess = false;
        foreach ($results as $result) {
            if ($result !== null) {
                if ($result['status'] === 'sent') {
                    $anySuccess = true;
                } else {
                    $allSuccess = false;
                }
            }
        }

        return response()->json([
            'status' => $anySuccess ? 'success' : 'error',
            'message' => $allSuccess ? 'Test sent successfully' : ($anySuccess ? 'Partial success' : 'Failed to send test'),
            'data' => [
                'sms' => $results['sms'],
                'email' => $results['email'],
                'sample_contact_id' => $sampleContact->id ?: null,
            ],
        ], $anySuccess ? 200 : 500);
    }

    /**
     * Send test SMS
     */
    protected function sendTestSms(Campaign $campaign, $user, $sampleContact, string $phone, $templateRenderer): array
    {
        $smsService = app(\App\Services\QuickSmsService::class);
        $costPerSms = config('app.sms_cost_per_message', 0.04);
        $globalTestMode = config('services.quicksms.test_mode', false);

        // Render message with fallback for missing variables (for test sends)
        $message = $templateRenderer->renderWithFallback($campaign->message_template ?? '', $sampleContact);
        $message = $templateRenderer->sanitizeForSMS($message);
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * $costPerSms;

        // Check balance
        if (!$globalTestMode && $user->balance < $cost) {
            return [
                'phone' => $phone,
                'message' => $message,
                'segments' => $segments,
                'cost' => $cost,
                'status' => 'failed',
                'error' => 'Insufficient balance',
            ];
        }

        // Send SMS
        $providerTransactionId = null;
        if ($globalTestMode) {
            $status = 'sent';
            $error = null;
            // Don't deduct balance in test mode
        } else {
            $unicode = $smsService->requiresUnicode($message);
            $result = $smsService->sendSMS($phone, $message, $campaign->sender, $unicode);

            if ($result['success']) {
                $status = 'sent';
                $error = null;
                $providerTransactionId = $result['transaction_id'] ?? null;
                $user->deductBalance($cost);
            } else {
                $status = 'failed';
                $error = $result['error_message'] ?? 'Unknown error';
            }
        }

        // Log the test SMS message (both in test mode and real mode)
        if ($status === 'sent') {
            \App\Models\SmsMessage::create([
                'user_id' => $campaign->created_by,
                'source' => 'campaign',
                'client_id' => $campaign->client_id,
                'campaign_id' => $campaign->id,
                'phone' => $phone,
                'message' => $message,
                'sender' => $campaign->sender,
                'cost' => $globalTestMode ? 0 : $cost,
                'status' => 'sent',
                'is_test' => true,
                'provider_transaction_id' => $providerTransactionId,
                'sent_at' => now(),
            ]);
        }

        return [
            'phone' => $phone,
            'message' => $message,
            'segments' => $segments,
            'cost' => ($status === 'sent' && !$globalTestMode) ? $cost : 0,
            'status' => $status,
            'error' => $error,
            'test_mode' => $globalTestMode,
        ];
    }

    /**
     * Send test Email
     */
    protected function sendTestEmail(Campaign $campaign, $user, $sampleContact, string $email, $templateRenderer): array
    {
        $costPerEmail = config('app.email_cost_per_message', 0.01);
        $globalTestMode = config('services.quicksms.test_mode', false);

        // Render subject and body
        // Render templates with fallback for missing variables (for test sends)
        $subject = $templateRenderer->renderWithFallback($campaign->email_subject_template ?? '', $sampleContact);
        $bodyText = $templateRenderer->renderWithFallback($campaign->email_body_template ?? '', $sampleContact);

        // Get email sender details from campaign or use default
        $emailSenderDetails = UserEmailSender::getByEmail($campaign->email_sender ?? '');
        if (!$emailSenderDetails) {
            $emailSenderDetails = UserEmailSender::getDefault();
        }
        $emailSenderEmail = $emailSenderDetails['email'];
        $emailSenderName = $emailSenderDetails['name'];

        // Convert plain text to HTML email
        $bodyHtml = $this->convertToHtmlEmail($bodyText, $subject, $emailSenderName);

        $cost = $costPerEmail;

        // Check balance
        if (!$globalTestMode && $user->balance < $cost) {
            return [
                'email' => $email,
                'subject' => $subject,
                'status' => 'failed',
                'error' => 'Insufficient balance',
            ];
        }

        // Send Email
        $providerMessageId = null;
        if ($globalTestMode) {
            $status = 'sent';
            $error = null;
            // Don't deduct balance in test mode - log manually
            \App\Models\EmailMessage::create([
                'user_id' => $campaign->created_by,
                'source' => 'campaign',
                'client_id' => $campaign->client_id,
                'campaign_id' => $campaign->id,
                'contact_id' => $sampleContact->id ?: null,
                'to_email' => $email,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'from_email' => $emailSenderEmail,
                'from_name' => $emailSenderName,
                'cost' => 0,
                'status' => 'sent',
                'is_test' => true,
                'sent_at' => now(),
            ]);
        } else {
            try {
                $emailService = $this->executionEngine->getEmailService();
                $result = $emailService->send(
                    $user,
                    $email,
                    $subject,
                    $bodyHtml,
                    $bodyText, // plain text version
                    null, // toName
                    $emailSenderEmail, // fromEmail
                    $emailSenderName, // fromName
                    'campaign',
                    $campaign->client_id,
                    $campaign->id, // campaignId
                    $sampleContact->id ?: null // contactId (null if temporary contact)
                );

                if ($result['success']) {
                    $status = 'sent';
                    $error = null;
                    $providerMessageId = $result['message_id'] ?? null;
                    // Balance already deducted by EmailService
                } else {
                    $status = 'failed';
                    $error = $result['error'] ?? 'Unknown error';
                }
            } catch (\Exception $e) {
                $status = 'failed';
                $error = $e->getMessage();
            }
        }

        return [
            'email' => $email,
            'subject' => $subject,
            'cost' => ($status === 'sent' && !$globalTestMode) ? $cost : 0,
            'status' => $status,
            'error' => $error,
            'test_mode' => $globalTestMode,
        ];
    }

    /**
     * Convert plain text email body to HTML with proper formatting
     */
    protected function convertToHtmlEmail(string $body, string $subject, ?string $senderName = null): string
    {
        // Convert newlines to <br> and escape HTML entities
        $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        $senderDisplay = $senderName ?? 'Alert.az';
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="background-color: #515BC3; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">{$senderDisplay}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            <div style="color: #4B5563; font-size: 15px; line-height: 1.6;">{$htmlBody}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">&copy; {$year} {$senderDisplay}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
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
