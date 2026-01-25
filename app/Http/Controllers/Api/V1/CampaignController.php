<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Campaign;
use App\Models\ServiceType;
use App\Models\Customer;
use App\Models\Service;
use App\Services\CampaignExecutor;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignController extends BaseController
{
    protected CampaignExecutor $executor;
    protected TemplateRenderer $templateRenderer;

    public function __construct(CampaignExecutor $executor, TemplateRenderer $templateRenderer)
    {
        $this->executor = $executor;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * List campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $query = Campaign::forClient($this->getClientId($request))
            ->with('serviceType');

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('campaign_type', $type);
        }

        // Filter by target_type
        if ($targetType = $request->input('target_type')) {
            $query->where('target_type', $targetType);
        }

        // Filter by service_type
        if ($serviceTypeKey = $request->input('service_type')) {
            $serviceType = ServiceType::forClient($this->getClientId($request))
                ->where('key', $serviceTypeKey)
                ->first();
            if ($serviceType) {
                $query->where('service_type_id', $serviceType->id);
            }
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min($request->input('per_page', 25), 100);
        $campaigns = $query->paginate($perPage);

        return $this->paginated($campaigns->through(fn($c) => $this->formatCampaign($c)));
    }

    /**
     * Create a campaign
     */
    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateCampaign($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $clientId = $this->getClientId($request);
        $data = $this->prepareCampaignData($request, $clientId);

        $campaign = Campaign::create(array_merge($data, [
            'client_id' => $clientId,
            'created_by' => $request->user()?->id,
        ]));

        return $this->created($this->formatCampaign($campaign->load('serviceType')));
    }

    /**
     * Get a campaign
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))
            ->with('serviceType')
            ->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        return $this->success($this->formatCampaign($campaign, true));
    }

    /**
     * Update a campaign
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        // Only draft campaigns can be fully updated
        if (!$campaign->isDraft()) {
            // Allow limited updates for non-draft campaigns
            $allowedFields = ['name', 'run_start_hour', 'run_end_hour', 'ends_at', 'cooldown_days'];
            $data = $request->only($allowedFields);

            if (empty($data)) {
                return $this->error('Only draft campaigns can be fully updated');
            }

            $campaign->update($data);
            return $this->success($this->formatCampaign($campaign->fresh()->load('serviceType')));
        }

        $validator = $this->validateCampaign($request, true);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $this->prepareCampaignData($request, $this->getClientId($request));
        $campaign->update($data);

        return $this->success($this->formatCampaign($campaign->fresh()->load('serviceType')));
    }

    /**
     * Delete a campaign
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        // Can't delete active/sending campaigns
        if (in_array($campaign->status, [Campaign::STATUS_ACTIVE, Campaign::STATUS_SENDING])) {
            return $this->error('Cannot delete active or sending campaigns');
        }

        $campaign->delete();

        return $this->success(null, 'Campaign deleted');
    }

    /**
     * Preview campaign targets
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))
            ->with('serviceType')
            ->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        $targets = $this->getTargets($campaign, 10);
        $totalCount = $this->countTargets($campaign);

        return $this->success([
            'total_count' => $totalCount,
            'preview' => $targets->map(fn($t) => $this->formatTarget($campaign, $t)),
        ]);
    }

    /**
     * Execute campaign now
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        if (!in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED])) {
            return $this->error('Campaign cannot be executed in current status');
        }

        try {
            $result = $this->executor->execute($campaign);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error('Execution failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activate automated campaign
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        if (!$campaign->isAutomated()) {
            return $this->error('Only automated campaigns can be activated');
        }

        $campaign->activate();

        return $this->success($this->formatCampaign($campaign->fresh()->load('serviceType')));
    }

    /**
     * Pause automated campaign
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        if (!$campaign->isAutomated()) {
            return $this->error('Only automated campaigns can be paused');
        }

        $campaign->pause($request->input('reason'));

        return $this->success($this->formatCampaign($campaign->fresh()->load('serviceType')));
    }

    /**
     * Get campaign stats
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        $stats = [
            'target_count' => $campaign->target_count,
            'sms' => [
                'sent' => $campaign->sent_count,
                'delivered' => $campaign->delivered_count,
                'failed' => $campaign->failed_count,
                'cost' => (float) $campaign->total_cost,
            ],
            'email' => [
                'sent' => $campaign->email_sent_count,
                'delivered' => $campaign->email_delivered_count,
                'failed' => $campaign->email_failed_count,
                'cost' => (float) $campaign->email_total_cost,
            ],
            'total_cost' => $campaign->grand_total_cost,
            'run_count' => $campaign->run_count,
            'last_run_at' => $campaign->last_run_at?->toIso8601String(),
            'next_run_at' => $campaign->next_run_at?->toIso8601String(),
        ];

        return $this->success($stats);
    }

    /**
     * Duplicate a campaign
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::forClient($this->getClientId($request))->find($id);

        if (!$campaign) {
            return $this->notFound('Campaign not found');
        }

        $newCampaign = $campaign->replicate([
            'status',
            'started_at',
            'completed_at',
            'last_run_at',
            'next_run_at',
            'run_count',
            'sent_count',
            'delivered_count',
            'failed_count',
            'total_cost',
            'email_sent_count',
            'email_delivered_count',
            'email_failed_count',
            'email_total_cost',
            'balance_warning_sent',
            'pause_reason',
        ]);

        $newCampaign->name = $campaign->name . ' (copy)';
        $newCampaign->status = Campaign::STATUS_DRAFT;
        $newCampaign->created_by = $request->user()?->id;
        $newCampaign->save();

        return $this->created($this->formatCampaign($newCampaign->load('serviceType')));
    }

    /**
     * Validate campaign request
     */
    private function validateCampaign(Request $request, bool $isUpdate = false): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'target_type' => ($isUpdate ? 'sometimes|' : '') . 'required|in:customer,service',
            'service_type' => 'required_if:target_type,service|nullable|string',
            'channel' => ($isUpdate ? 'sometimes|' : '') . 'required|in:sms,email,both',
            'message_template' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
            'sender' => 'nullable|string|max:50',
            'email_sender' => 'nullable|email|max:255',
            'email_display_name' => 'nullable|string|max:255',
            'filter' => 'nullable|array',
            'campaign_type' => ($isUpdate ? 'sometimes|' : '') . 'required|in:one_time,automated',
            'scheduled_at' => 'nullable|date',
            'check_interval_minutes' => 'required_if:campaign_type,automated|nullable|integer|min:1',
            'cooldown_days' => 'nullable|integer|min:0|max:365',
            'run_start_hour' => 'nullable|integer|min:0|max:23',
            'run_end_hour' => 'nullable|integer|min:0|max:23',
            'ends_at' => 'nullable|date',
        ];

        return Validator::make($request->all(), $rules);
    }

    /**
     * Prepare campaign data from request
     */
    private function prepareCampaignData(Request $request, int $clientId): array
    {
        $data = [
            'name' => $request->input('name'),
            'target_type' => $request->input('target_type'),
            'channel' => $request->input('channel'),
            'message_template' => $request->input('message_template'),
            'email_subject' => $request->input('email_subject'),
            'email_body' => $request->input('email_body'),
            'sender' => $request->input('sender'),
            'email_sender' => $request->input('email_sender'),
            'email_display_name' => $request->input('email_display_name'),
            'filter' => $request->input('filter'),
            'campaign_type' => $request->input('campaign_type'),
            'scheduled_at' => $request->input('scheduled_at'),
            'check_interval_minutes' => $request->input('check_interval_minutes'),
            'cooldown_days' => $request->input('cooldown_days', 30),
            'run_start_hour' => $request->input('run_start_hour'),
            'run_end_hour' => $request->input('run_end_hour'),
            'ends_at' => $request->input('ends_at'),
        ];

        // Resolve service type
        if ($request->input('target_type') === 'service' && $request->input('service_type')) {
            $serviceType = ServiceType::forClient($clientId)
                ->where('key', $request->input('service_type'))
                ->first();
            $data['service_type_id'] = $serviceType?->id;
        }

        return array_filter($data, fn($v) => $v !== null);
    }

    /**
     * Get targets for campaign
     */
    private function getTargets(Campaign $campaign, int $limit = null)
    {
        if ($campaign->targetsCustomers()) {
            $query = Customer::forClient($campaign->client_id)
                ->applyFilter($campaign->filter);
        } else {
            $query = Service::forClient($campaign->client_id)
                ->where('service_type_id', $campaign->service_type_id)
                ->with('customer')
                ->applyFilter($campaign->filter)
                ->notInCooldown($campaign->id, $campaign->cooldown_days);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Count targets for campaign
     */
    private function countTargets(Campaign $campaign): int
    {
        if ($campaign->targetsCustomers()) {
            return Customer::forClient($campaign->client_id)
                ->applyFilter($campaign->filter)
                ->count();
        }

        return Service::forClient($campaign->client_id)
            ->where('service_type_id', $campaign->service_type_id)
            ->applyFilter($campaign->filter)
            ->notInCooldown($campaign->id, $campaign->cooldown_days)
            ->count();
    }

    /**
     * Format target for response
     */
    private function formatTarget(Campaign $campaign, $target): array
    {
        if ($campaign->targetsCustomers()) {
            return [
                'type' => 'customer',
                'id' => $target->id,
                'name' => $target->name,
                'phone' => $target->phone,
                'email' => $target->email,
            ];
        }

        return [
            'type' => 'service',
            'id' => $target->id,
            'name' => $target->name,
            'expiry_at' => $target->expiry_at?->toDateString(),
            'status' => $target->status,
            'customer' => $target->customer ? [
                'name' => $target->customer->name,
                'phone' => $target->customer->phone,
                'email' => $target->customer->email,
            ] : null,
        ];
    }

    /**
     * Format campaign for response
     */
    private function formatCampaign(Campaign $campaign, bool $detailed = false): array
    {
        $data = [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'target_type' => $campaign->target_type,
            'service_type' => $campaign->serviceType ? [
                'key' => $campaign->serviceType->key,
                'label' => $campaign->serviceType->label,
            ] : null,
            'channel' => $campaign->channel,
            'status' => $campaign->status,
            'campaign_type' => $campaign->campaign_type,
            'stats' => [
                'target_count' => $campaign->target_count,
                'total_sent' => $campaign->total_sent,
                'total_delivered' => $campaign->total_delivered,
                'total_failed' => $campaign->total_failed,
                'total_cost' => $campaign->grand_total_cost,
            ],
            'created_at' => $campaign->created_at->toIso8601String(),
            'updated_at' => $campaign->updated_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['message_template'] = $campaign->message_template;
            $data['email_subject'] = $campaign->email_subject;
            $data['email_body'] = $campaign->email_body;
            $data['sender'] = $campaign->sender;
            $data['email_sender'] = $campaign->email_sender;
            $data['email_display_name'] = $campaign->email_display_name;
            $data['filter'] = $campaign->filter;
            $data['scheduled_at'] = $campaign->scheduled_at?->toIso8601String();
            $data['check_interval_minutes'] = $campaign->check_interval_minutes;
            $data['cooldown_days'] = $campaign->cooldown_days;
            $data['run_start_hour'] = $campaign->run_start_hour;
            $data['run_end_hour'] = $campaign->run_end_hour;
            $data['ends_at'] = $campaign->ends_at?->toIso8601String();
            $data['next_run_at'] = $campaign->next_run_at?->toIso8601String();
            $data['last_run_at'] = $campaign->last_run_at?->toIso8601String();
            $data['run_count'] = $campaign->run_count;
            $data['pause_reason'] = $campaign->pause_reason;
        }

        return $data;
    }
}
