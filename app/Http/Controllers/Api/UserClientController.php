<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Template;
use App\Models\Campaign;
use App\Services\MessageSender;
use App\Services\TemplateRenderer;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserClientController extends Controller
{
    /**
     * List user's projects (clients)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $clients = Client::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'status' => $client->status,
                    'api_token' => $client->api_token,
                    'settings' => $client->settings,
                    'created_at' => $client->created_at->toIso8601String(),
                    'stats' => [
                        'customers_count' => $client->customers()->count(),
                        'services_count' => $client->services()->count(),
                        'campaigns_count' => $client->campaigns()->count(),
                        'active_campaigns' => $client->campaigns()->whereIn('status', ['active', 'sending'])->count(),
                    ],
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'projects' => $clients,
            ],
        ], 200);
    }

    /**
     * Get single project
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $client = Client::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'project' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'status' => $client->status,
                    'api_token' => $client->api_token,
                    'settings' => $client->settings,
                    'created_at' => $client->created_at->toIso8601String(),
                    'stats' => [
                        'customers_count' => $client->customers()->count(),
                        'services_count' => $client->services()->count(),
                        'campaigns_count' => $client->campaigns()->count(),
                        'service_types_count' => $client->serviceTypes()->count(),
                    ],
                ],
            ],
        ], 200);
    }

    /**
     * Create new project
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = Client::create([
            'name' => $request->input('name'),
            'api_token' => Client::generateApiToken(),
            'user_id' => $user->id,
            'status' => 'active',
            'settings' => [
                'description' => $request->input('description'),
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Project created successfully',
            'data' => [
                'project' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'status' => $client->status,
                    'api_token' => $client->api_token,
                    'settings' => $client->settings,
                    'created_at' => $client->created_at->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update project
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $client = Client::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $client->name = $request->input('name');
        }

        if ($request->has('description')) {
            $settings = $client->settings ?? [];
            $settings['description'] = $request->input('description');
            $client->settings = $settings;
        }

        if ($request->has('status')) {
            $client->status = $request->input('status');
        }

        $client->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Project updated successfully',
            'data' => [
                'project' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'status' => $client->status,
                    'api_token' => $client->api_token,
                    'settings' => $client->settings,
                ],
            ],
        ], 200);
    }

    /**
     * Delete project
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $client = Client::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        // Check if project has active campaigns
        $activeCampaigns = $client->campaigns()
            ->whereIn('status', ['sending', 'scheduled'])
            ->count();

        if ($activeCampaigns > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete project with active or scheduled campaigns',
            ], 422);
        }

        $client->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Project deleted successfully',
        ], 200);
    }

    /**
     * Get user's default/primary API token
     * Creates a default project if none exists
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDefaultToken(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get first active client, or create one if none exists
        $client = Client::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$client) {
            // Auto-create a default project for the user
            $client = Client::create([
                'name' => 'Default Project',
                'api_token' => Client::generateApiToken(),
                'user_id' => $user->id,
                'status' => 'active',
                'settings' => [
                    'description' => 'Auto-created default project',
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'api_token' => $client->api_token,
                'project_id' => $client->id,
                'project_name' => $client->name,
            ],
        ], 200);
    }

    /**
     * Regenerate API token
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function regenerateToken(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $client = Client::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        $client->api_token = Client::generateApiToken();
        $client->save();

        return response()->json([
            'status' => 'success',
            'message' => 'API token regenerated successfully',
            'data' => [
                'api_token' => $client->api_token,
            ],
        ], 200);
    }

    /**
     * Get project's service types
     */
    public function serviceTypes(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $types = $client->serviceTypes()->orderBy('display_order')->get();

        return response()->json([
            'status' => 'success',
            'data' => $types,
        ]);
    }

    /**
     * Get project's customers
     */
    public function customers(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $query = $client->customers();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $customers->items(),
            'meta' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    /**
     * Get project's services by type
     */
    public function services(Request $request, $id, string $type): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $type)->first();
        if (!$serviceType) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service type not found',
            ], 404);
        }

        $query = $client->services()->where('service_type_id', $serviceType->id);

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->input('per_page', 20);
        $services = $query->with('customer')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $services->items(),
            'meta' => [
                'total' => $services->total(),
                'per_page' => $services->perPage(),
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
            ],
        ]);
    }

    /**
     * Get project's campaigns
     */
    public function campaigns(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $query = $client->campaigns();

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Type filter
        if ($type = $request->input('type')) {
            $query->where('campaign_type', $type);
        }

        $perPage = $request->input('per_page', 20);
        $campaigns = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $campaigns->items(),
            'meta' => [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
            ],
        ]);
    }

    /**
     * Get project's templates
     */
    public function templates(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $query = $client->templates();

        // Channel filter
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }

    /**
     * Get service stats for a type
     */
    public function serviceStats(Request $request, $id, string $type): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $type)->first();
        if (!$serviceType) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service type not found',
            ], 404);
        }

        $query = $client->services()->where('service_type_id', $serviceType->id);

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'suspended' => (clone $query)->where('status', 'suspended')->count(),
            'expired' => (clone $query)->where('status', 'expired')->count(),
            'expiring_7_days' => (clone $query)->where('status', 'active')
                ->whereNotNull('expiry_at')
                ->whereDate('expiry_at', '<=', now()->addDays(7))
                ->whereDate('expiry_at', '>=', now())
                ->count(),
            'expiring_30_days' => (clone $query)->where('status', 'active')
                ->whereNotNull('expiry_at')
                ->whereDate('expiry_at', '<=', now()->addDays(30))
                ->whereDate('expiry_at', '>=', now())
                ->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Helper: Get client for authenticated user
     */
    private function getClientForUser(Request $request, int $id): ?Client
    {
        return Client::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();
    }

    /**
     * Helper: Not found response
     */
    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Project not found',
        ], 404);
    }

    // ============================================
    // Dashboard Write Operations (Sanctum Auth)
    // ============================================

    /**
     * Send message to a customer
     */
    public function sendToCustomer(Request $request, $id, $customerId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $customer = $client->customers()->find($customerId);
        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Customer not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
            'sender' => 'nullable|string|max:50',
            'email_sender' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $result = $this->sendMessageToTarget(
            $client,
            $request,
            $customer->getTemplateVariables(),
            $customer->phone,
            $customer->email,
            $customerId,
            null
        );

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    /**
     * Delete a customer
     */
    public function deleteCustomer(Request $request, $id, $customerId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $customer = $client->customers()->find($customerId);
        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Customer not found'], 404);
        }

        $customer->delete();

        return response()->json(['status' => 'success', 'message' => 'Customer deleted']);
    }

    /**
     * Bulk delete customers
     */
    public function bulkDeleteCustomers(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|max:1000',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $deleted = $client->customers()->whereIn('id', $request->input('ids'))->delete();

        return response()->json(['status' => 'success', 'data' => ['deleted' => $deleted]]);
    }

    /**
     * Bulk send to customers
     */
    public function bulkSendToCustomers(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'customer_ids' => 'required|array|max:1000',
            'customer_ids.*' => 'required|integer',
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $customers = $client->customers()->whereIn('id', $request->input('customer_ids'))->get();
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($customers as $customer) {
            $result = $this->sendMessageToTarget(
                $client,
                $request,
                $customer->getTemplateVariables(),
                $customer->phone,
                $customer->email,
                $customer->id,
                null
            );

            if (($result['sms']['status'] ?? null) === 'sent' || ($result['email']['status'] ?? null) === 'sent') {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'customer_id' => $customer->id,
                    'sms_error' => $result['sms']['error'] ?? null,
                    'email_error' => $result['email']['error'] ?? null,
                ];
            }
        }

        return response()->json(['status' => 'success', 'data' => $results]);
    }

    /**
     * Send message to a service
     */
    public function sendToService(Request $request, $id, string $type, $serviceId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $type)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        $service = $client->services()
            ->where('service_type_id', $serviceType->id)
            ->with('customer')
            ->find($serviceId);

        if (!$service) {
            return response()->json(['status' => 'error', 'message' => 'Service not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
            'sender' => 'nullable|string|max:50',
            'email_sender' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $variables = $service->getTemplateVariables();
        $phone = $service->customer?->phone;
        $email = $service->customer?->email;

        $result = $this->sendMessageToTarget(
            $client,
            $request,
            $variables,
            $phone,
            $email,
            $service->customer_id,
            $serviceId
        );

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    /**
     * Delete a service
     */
    public function deleteService(Request $request, $id, string $type, $serviceId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $type)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        $service = $client->services()
            ->where('service_type_id', $serviceType->id)
            ->find($serviceId);

        if (!$service) {
            return response()->json(['status' => 'error', 'message' => 'Service not found'], 404);
        }

        $service->delete();

        return response()->json(['status' => 'success', 'message' => 'Service deleted']);
    }

    /**
     * Bulk delete services
     */
    public function bulkDeleteServices(Request $request, $id, string $type): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $type)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|max:1000',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $deleted = $client->services()
            ->where('service_type_id', $serviceType->id)
            ->whereIn('id', $request->input('ids'))
            ->delete();

        return response()->json(['status' => 'success', 'data' => ['deleted' => $deleted]]);
    }

    /**
     * Bulk send to services
     */
    public function bulkSendToServices(Request $request, $id, string $type): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $type)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_ids' => 'required|array|max:1000',
            'service_ids.*' => 'required|integer',
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $services = $client->services()
            ->where('service_type_id', $serviceType->id)
            ->whereIn('id', $request->input('service_ids'))
            ->with('customer')
            ->get();

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($services as $service) {
            if (!$service->customer) {
                $results['skipped']++;
                continue;
            }

            $result = $this->sendMessageToTarget(
                $client,
                $request,
                $service->getTemplateVariables(),
                $service->customer->phone,
                $service->customer->email,
                $service->customer_id,
                $service->id
            );

            if (($result['sms']['status'] ?? null) === 'sent' || ($result['email']['status'] ?? null) === 'sent') {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'service_id' => $service->id,
                    'sms_error' => $result['sms']['error'] ?? null,
                    'email_error' => $result['email']['error'] ?? null,
                ];
            }
        }

        return response()->json(['status' => 'success', 'data' => $results]);
    }

    /**
     * Create a template
     */
    public function createTemplate(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'channel' => 'required|in:sms,email,both',
            'message_template' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $template = Template::create([
            'client_id' => $client->id,
            'name' => $request->input('name'),
            'channel' => $request->input('channel'),
            'message_template' => $request->input('message_template'),
            'email_subject' => $request->input('email_subject'),
            'email_body' => $request->input('email_body'),
            'is_default' => $request->boolean('is_default', false),
        ]);

        return response()->json(['status' => 'success', 'data' => $template], 201);
    }

    /**
     * Update a template
     */
    public function updateTemplate(Request $request, $id, $templateId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $template = $client->templates()->find($templateId);
        if (!$template) {
            return response()->json(['status' => 'error', 'message' => 'Template not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'channel' => 'sometimes|required|in:sms,email,both',
            'message_template' => 'nullable|string|max:1000',
            'email_subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $template->update($request->only([
            'name', 'channel', 'message_template', 'email_subject', 'email_body', 'is_default'
        ]));

        return response()->json(['status' => 'success', 'data' => $template->fresh()]);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(Request $request, $id, $templateId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $template = $client->templates()->find($templateId);
        if (!$template) {
            return response()->json(['status' => 'error', 'message' => 'Template not found'], 404);
        }

        $template->delete();

        return response()->json(['status' => 'success', 'message' => 'Template deleted']);
    }

    /**
     * Get a single service type
     */
    public function getServiceType(Request $request, $id, string $key): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $key)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $serviceType]);
    }

    /**
     * Preview message with variable substitution
     */
    public function previewMessage(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string',
            'email_subject' => 'nullable|string',
            'email_body' => 'nullable|string',
            'variables' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $variables = $request->input('variables', []);
        $renderer = new TemplateRenderer();

        $result = [
            'message' => null,
            'email_subject' => null,
            'email_body' => null,
            'sms_segments' => 0,
        ];

        if ($request->has('message')) {
            $result['message'] = $renderer->render($request->input('message'), $variables);
            // Calculate SMS segments (160 chars for GSM-7, 70 for UCS-2)
            $messageLength = mb_strlen($result['message']);
            $result['sms_segments'] = $messageLength <= 160 ? 1 : ceil($messageLength / 153);
        }

        if ($request->has('email_subject')) {
            $result['email_subject'] = $renderer->render($request->input('email_subject'), $variables);
        }

        if ($request->has('email_body')) {
            $result['email_body'] = $renderer->render($request->input('email_body'), $variables);
        }

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    /**
     * Create a service type
     */
    public function createServiceType(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'label' => 'required|array',
            'label.az' => 'required|string|max:255',
            'label.en' => 'nullable|string|max:255',
            'label.ru' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'user_link_field' => 'nullable|in:phone,email,external_id',
            'fields' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Check if key already exists
        if ($client->serviceTypes()->where('key', $request->input('key'))->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Service type key already exists'], 422);
        }

        $serviceType = ServiceType::create([
            'client_id' => $client->id,
            'key' => $request->input('key'),
            'label' => $request->input('label'),
            'icon' => $request->input('icon', 'package'),
            'user_link_field' => $request->input('user_link_field', 'phone'),
            'fields' => $request->input('fields', []),
            'display_order' => $client->serviceTypes()->count() + 1,
        ]);

        return response()->json(['status' => 'success', 'data' => $serviceType], 201);
    }

    /**
     * Update a service type
     */
    public function updateServiceType(Request $request, $id, string $key): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $key)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|required|array',
            'label.az' => 'required_with:label|string|max:255',
            'label.en' => 'nullable|string|max:255',
            'label.ru' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'user_link_field' => 'nullable|in:phone,email,external_id',
            'fields' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $serviceType->update($request->only([
            'label', 'icon', 'user_link_field', 'fields'
        ]));

        return response()->json(['status' => 'success', 'data' => $serviceType->fresh()]);
    }

    /**
     * Delete a service type
     */
    public function deleteServiceType(Request $request, $id, string $key): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $serviceType = $client->serviceTypes()->where('key', $key)->first();
        if (!$serviceType) {
            return response()->json(['status' => 'error', 'message' => 'Service type not found'], 404);
        }

        // Check if there are services using this type
        $servicesCount = $client->services()->where('service_type_id', $serviceType->id)->count();
        if ($servicesCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot delete service type with {$servicesCount} existing services"
            ], 422);
        }

        $serviceType->delete();

        return response()->json(['status' => 'success', 'message' => 'Service type deleted']);
    }

    /**
     * Create a campaign
     */
    public function createCampaign(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'channel' => 'required|in:sms,email,both',
            'campaign_type' => 'required|in:one_time,automated,immediate,scheduled,recurring',
            'target_type' => 'required|in:customer,service,all,filter,segment',
            'message_template' => 'nullable|string',
            'email_subject' => 'nullable|string',
            'email_body' => 'nullable|string',
            'filter' => 'nullable|array',
            'segment_filter' => 'nullable|array',
            'segment_id' => 'nullable|integer',
            'service_type_key' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'schedule_config' => 'nullable|array',
            'check_interval_minutes' => 'nullable|integer',
            'cooldown_days' => 'nullable|integer',
            'run_start_hour' => 'nullable|integer|min:0|max:23',
            'run_end_hour' => 'nullable|integer|min:0|max:23',
            'ends_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Validate message content based on channel
        $channel = $request->input('channel');
        if (($channel === 'sms' || $channel === 'both') && empty($request->input('message_template'))) {
            return response()->json(['status' => 'error', 'message' => 'SMS message template is required'], 422);
        }
        if (($channel === 'email' || $channel === 'both') && (empty($request->input('email_subject')) || empty($request->input('email_body')))) {
            return response()->json(['status' => 'error', 'message' => 'Email subject and body are required'], 422);
        }

        // Get filter - accept both 'segment_filter' (new) and 'filter' (legacy)
        $filter = $request->input('segment_filter') ?? $request->input('filter');

        // Look up service_type_id from service_type_key
        $serviceTypeId = null;
        $targetType = $request->input('target_type');
        if ($targetType === 'service' && $request->input('service_type_key')) {
            $serviceType = \App\Models\ServiceType::where('client_id', $client->id)
                ->where('key', $request->input('service_type_key'))
                ->first();
            $serviceTypeId = $serviceType?->id;
        }

        // Set defaults for automated campaign fields
        $campaignType = $request->input('campaign_type');
        $isAutomated = $campaignType === 'automated' || $campaignType === 'recurring';

        $campaign = Campaign::create([
            'client_id' => $client->id,
            'name' => $request->input('name'),
            'channel' => $request->input('channel'),
            'campaign_type' => $campaignType,
            'target_type' => $request->input('target_type'),
            'service_type_id' => $serviceTypeId,
            'message_template' => $request->input('message_template'),
            'email_subject' => $request->input('email_subject'),
            'email_body' => $request->input('email_body'),
            'filter' => $filter,
            'segment_id' => $request->input('segment_id'),
            'scheduled_at' => $request->input('scheduled_at'),
            'schedule_config' => $request->input('schedule_config'),
            'check_interval_minutes' => $request->input('check_interval_minutes', $isAutomated ? 60 : 0),
            'cooldown_days' => $request->input('cooldown_days', $isAutomated ? 7 : 0),
            'run_start_hour' => $request->input('run_start_hour', 9),
            'run_end_hour' => $request->input('run_end_hour', 18),
            'ends_at' => $request->input('ends_at'),
            'status' => 'draft',
        ]);

        return response()->json(['status' => 'success', 'data' => $campaign], 201);
    }

    /**
     * Update a campaign
     */
    public function updateCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign->status, ['draft', 'paused'])) {
            return response()->json(['status' => 'error', 'message' => 'Cannot update campaign with status: ' . $campaign->status], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'channel' => 'sometimes|required|in:sms,email,both',
            'campaign_type' => 'sometimes|required|in:immediate,scheduled,recurring,one_time,automated',
            'target_type' => 'sometimes|required|in:all,filter,segment,customer,service',
            'message_template' => 'nullable|string',
            'email_subject' => 'nullable|string',
            'email_body' => 'nullable|string',
            'email_subject_template' => 'nullable|string',
            'email_body_template' => 'nullable|string',
            'filter' => 'nullable|array',
            'segment_filter' => 'nullable|array',
            'segment_id' => 'nullable|integer',
            'service_type_key' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'schedule_config' => 'nullable|array',
            'type' => 'nullable|in:one_time,automated',
            'check_interval_minutes' => 'nullable|integer',
            'cooldown_days' => 'nullable|integer',
            'run_start_hour' => 'nullable|integer|min:0|max:23',
            'run_end_hour' => 'nullable|integer|min:0|max:23',
            'ends_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Handle type field (frontend sends 'type', backend uses 'campaign_type')
        $data = $request->only([
            'name', 'channel', 'campaign_type', 'target_type', 'message_template',
            'email_subject', 'email_body', 'filter', 'segment_id', 'service_type_key',
            'scheduled_at', 'schedule_config', 'check_interval_minutes', 'cooldown_days',
            'run_start_hour', 'run_end_hour', 'ends_at'
        ]);

        // Map 'type' to 'campaign_type' if present
        if ($request->has('type') && !$request->has('campaign_type')) {
            $data['campaign_type'] = $request->input('type');
        }

        // Map segment_filter to filter if present
        if ($request->has('segment_filter') && !$request->has('filter')) {
            $data['filter'] = $request->input('segment_filter');
        }

        // Map email_subject_template to email_subject if present
        if ($request->has('email_subject_template') && !$request->has('email_subject')) {
            $data['email_subject'] = $request->input('email_subject_template');
        }

        // Map email_body_template to email_body if present
        if ($request->has('email_body_template') && !$request->has('email_body')) {
            $data['email_body'] = $request->input('email_body_template');
        }

        $campaign->update($data);

        return response()->json(['status' => 'success', 'data' => $campaign->fresh()]);
    }

    /**
     * Delete a campaign
     */
    public function deleteCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        if ($campaign->status === 'sending') {
            return response()->json(['status' => 'error', 'message' => 'Cannot delete campaign that is currently sending'], 422);
        }

        $campaign->delete();

        return response()->json(['status' => 'success', 'message' => 'Campaign deleted']);
    }

    /**
     * Execute a campaign
     */
    public function executeCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign->status, ['draft', 'active', 'paused'])) {
            return response()->json(['status' => 'error', 'message' => 'Cannot execute campaign with status: ' . $campaign->status], 422);
        }

        // Dispatch campaign execution job
        $campaign->update(['status' => 'sending', 'started_at' => now()]);

        // Queue the campaign for execution
        \App\Jobs\ExecuteCampaignJob::dispatch($campaign);

        return response()->json(['status' => 'success', 'message' => 'Campaign execution started', 'data' => $campaign->fresh()]);
    }

    /**
     * Activate a campaign
     */
    public function activateCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign->status, ['draft', 'paused'])) {
            return response()->json(['status' => 'error', 'message' => 'Cannot activate campaign with status: ' . $campaign->status], 422);
        }

        $campaign->update(['status' => 'active']);

        return response()->json(['status' => 'success', 'message' => 'Campaign activated', 'data' => $campaign->fresh()]);
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign->status, ['active', 'sending'])) {
            return response()->json(['status' => 'error', 'message' => 'Cannot pause campaign with status: ' . $campaign->status], 422);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json(['status' => 'success', 'message' => 'Campaign paused', 'data' => $campaign->fresh()]);
    }

    /**
     * Preview a campaign (estimate recipients)
     */
    public function previewCampaign(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $validator = Validator::make($request->all(), [
            'target_type' => 'required|in:customer,service,all,filter,segment',
            'filter' => 'nullable|array',
            'segment_id' => 'nullable|integer',
            'service_type_key' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $targetType = $request->input('target_type');
        $serviceTypeKey = $request->input('service_type_key');
        $filter = $request->input('filter');

        $count = 0;
        $sample = [];
        $debugSql = null;
        $debugFilter = null;

        // Check if user is admin (ID 1) for debug info
        $user = $request->user();
        $isAdmin = $user && $user->id === 1;

        if ($serviceTypeKey || $targetType === 'service') {
            // Targeting services
            $serviceType = $client->serviceTypes()->where('key', $serviceTypeKey)->first();
            if ($serviceType) {
                $query = $client->services()->where('service_type_id', $serviceType->id)->with('customer');

                if ($filter && isset($filter['conditions']) && count($filter['conditions']) > 0) {
                    $query->applyFilter($filter);
                }

                // Get debug SQL before executing
                if ($isAdmin) {
                    $debugSql = $query->toSql();
                    $debugFilter = $filter;
                }

                $count = $query->count();

                // Get sample items
                $sampleItems = (clone $query)->limit(10)->get();
                $sample = $sampleItems->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'expiry_at' => $service->expiry_at?->format('Y-m-d'),
                        'status' => $service->status,
                        'customer' => $service->customer ? [
                            'id' => $service->customer->id,
                            'name' => $service->customer->name,
                            'phone' => $service->customer->phone,
                            'email' => $service->customer->email,
                        ] : null,
                    ];
                })->toArray();
            }
        } else {
            // Targeting customers
            $query = $client->customers();

            if ($filter && isset($filter['conditions']) && count($filter['conditions']) > 0) {
                $query->applyFilter($filter);
            }

            // Get debug SQL before executing
            if ($isAdmin) {
                $debugSql = $query->toSql();
                $debugFilter = $filter;
            }

            $count = $query->count();

            // Get sample items
            $sampleItems = (clone $query)->limit(10)->get();
            $sample = $sampleItems->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ];
            })->toArray();
        }

        $response = [
            'count' => $count,
            'sample' => $sample,
        ];

        // Include debug info only for admin
        if ($isAdmin) {
            $response['debug_sql'] = $debugSql;
            $response['debug_filter'] = $debugFilter;
        }

        return response()->json([
            'status' => 'success',
            'data' => $response,
        ]);
    }

    /**
     * Cancel a campaign
     */
    public function cancelCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign->status, ['scheduled', 'sending', 'active'])) {
            return response()->json(['status' => 'error', 'message' => 'Cannot cancel campaign with status: ' . $campaign->status], 422);
        }

        $campaign->update(['status' => 'cancelled']);

        return response()->json(['status' => 'success', 'message' => 'Campaign cancelled', 'data' => $campaign->fresh()]);
    }

    /**
     * Duplicate a campaign
     */
    public function duplicateCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        $newCampaign = $campaign->replicate();
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->status = 'draft';
        $newCampaign->started_at = null;
        $newCampaign->completed_at = null;
        $newCampaign->save();

        return response()->json(['status' => 'success', 'message' => 'Campaign duplicated', 'data' => $newCampaign]);
    }

    /**
     * Get project's sent messages
     */
    public function messages(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $query = Message::where('client_id', $client->id);

        // Channel filter (sms/email)
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search (recipient, content, subject)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($from = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = $request->input('per_page', 20);
        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get counts for tabs
        $smsCount = Message::where('client_id', $client->id)->where('channel', 'sms')->count();
        $emailCount = Message::where('client_id', $client->id)->where('channel', 'email')->count();

        return response()->json([
            'status' => 'success',
            'data' => $messages->items(),
            'meta' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'sms_count' => $smsCount,
                'email_count' => $emailCount,
            ],
        ]);
    }

    /**
     * Get a single customer with their services
     */
    public function getCustomer(Request $request, $id, $customerId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $customer = $client->customers()
            ->with(['services.serviceType'])
            ->find($customerId);

        if (!$customer) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'status' => 'success',
            'data' => $customer,
        ]);
    }

    /**
     * Get messages for a specific customer
     */
    public function customerMessages(Request $request, $id, $customerId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $customer = $client->customers()->find($customerId);
        if (!$customer) {
            return $this->notFoundResponse();
        }

        $query = Message::where('client_id', $client->id)
            ->where('customer_id', $customerId);

        // Channel filter (sms/email)
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }

        $perPage = $request->input('per_page', 20);
        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get counts for tabs
        $smsCount = Message::where('client_id', $client->id)
            ->where('customer_id', $customerId)
            ->where('channel', 'sms')
            ->count();
        $emailCount = Message::where('client_id', $client->id)
            ->where('customer_id', $customerId)
            ->where('channel', 'email')
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => $messages->items(),
            'meta' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'sms_count' => $smsCount,
                'email_count' => $emailCount,
            ],
        ]);
    }

    /**
     * Helper: Send message to a target
     */
    private function sendMessageToTarget(
        Client $client,
        Request $request,
        array $variables,
        ?string $phone,
        ?string $email,
        ?int $customerId,
        ?int $serviceId
    ): array {
        $messageSender = app(MessageSender::class);
        $templateRenderer = app(TemplateRenderer::class);
        $channel = $request->input('channel');

        $result = [
            'sms' => ['status' => 'skipped'],
            'email' => ['status' => 'skipped'],
        ];

        // Send SMS
        if (in_array($channel, ['sms', 'both']) && $phone) {
            $messageText = $templateRenderer->render($request->input('message'), $variables);
            // Get SMS sender with proper fallback chain
            $sender = $request->input('sender')
                ?? $client->getSetting('default_sms_sender')
                ?? 'Alert.az';

            try {
                $smsResult = $messageSender->sendSms($phone, $messageText, $sender);

                $message = Message::createSms([
                    'client_id' => $client->id,
                    'customer_id' => $customerId,
                    'service_id' => $serviceId,
                    'recipient' => $phone,
                    'content' => $messageText,
                    'sender' => $sender,
                    'status' => $smsResult['success'] ? Message::STATUS_SENT : Message::STATUS_FAILED,
                    'provider_message_id' => $smsResult['message_id'] ?? null,
                    'error_message' => $smsResult['error'] ?? null,
                    'cost' => $smsResult['cost'] ?? 0,
                    'segments' => $templateRenderer->calculateSMSSegments($messageText),
                    'sent_at' => now(),
                ]);

                $result['sms'] = [
                    'status' => $smsResult['success'] ? 'sent' : 'failed',
                    'message_id' => $message->id,
                    'error' => $smsResult['error'] ?? null,
                ];
            } catch (\Exception $e) {
                $result['sms'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Send Email
        if (in_array($channel, ['email', 'both']) && $email) {
            $subject = $templateRenderer->render($request->input('email_subject'), $variables);
            $body = $templateRenderer->render($request->input('email_body'), $variables);
            // Get email sender with proper fallback chain
            $emailSender = $request->input('email_sender')
                ?? $client->getSetting('default_email_sender')
                ?? config('mail.from.address', 'noreply@alert.az');

            try {
                $emailResult = $messageSender->sendEmail($email, $subject, $body, $emailSender);

                $message = Message::createEmail([
                    'client_id' => $client->id,
                    'customer_id' => $customerId,
                    'service_id' => $serviceId,
                    'recipient' => $email,
                    'subject' => $subject,
                    'content' => $body,
                    'sender' => $emailSender, // Now always has a value
                    'status' => $emailResult['success'] ? Message::STATUS_SENT : Message::STATUS_FAILED,
                    'provider_message_id' => $emailResult['message_id'] ?? null,
                    'error_message' => $emailResult['error'] ?? null,
                    'cost' => $emailResult['cost'] ?? 0,
                    'sent_at' => now(),
                ]);

                $result['email'] = [
                    'status' => $emailResult['success'] ? 'sent' : 'failed',
                    'message_id' => $message->id,
                    'error' => $emailResult['error'] ?? null,
                ];
            } catch (\Exception $e) {
                $result['email'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    // ============================================
    // Campaign Detail Operations (Sanctum Auth)
    // ============================================

    /**
     * Get a single campaign
     */
    public function getCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $campaign,
        ]);
    }

    /**
     * Get segment attributes for filter builder
     */
    public function segmentAttributes(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        // Operator sets for different types
        $stringConditions = ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_set', 'is_not_set'];
        $numberConditions = ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal', 'is_set', 'is_not_set'];
        $dateConditions = ['equals', 'not_equals', 'before', 'after', 'is_set', 'is_not_set'];
        $selectConditions = ['equals', 'not_equals', 'in', 'not_in'];

        // Base customer attributes
        $attributes = [
            ['key' => 'name', 'label' => 'Name', 'type' => 'string', 'conditions' => $stringConditions],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'string', 'conditions' => $stringConditions],
            ['key' => 'email', 'label' => 'Email', 'type' => 'string', 'conditions' => $stringConditions],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active', 'inactive', 'suspended'], 'conditions' => $selectConditions],
            ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date', 'conditions' => $dateConditions],
        ];

        // Service-specific attributes
        $serviceAttributes = [
            ['key' => 'expiry_at', 'label' => 'Expiry Date', 'type' => 'date', 'conditions' => $dateConditions],
            ['key' => 'days_until_expiry', 'label' => 'Days Until Expiry', 'type' => 'number', 'conditions' => $numberConditions],
        ];

        // Add custom fields from service types
        // Track added keys to avoid duplicates
        $addedKeys = [];
        $serviceTypes = $client->serviceTypes()->get();
        foreach ($serviceTypes as $type) {
            if (!empty($type->fields)) {
                foreach ($type->fields as $fieldKey => $field) {
                    // Skip if this key was already added
                    if (in_array($fieldKey, $addedKeys)) {
                        continue;
                    }
                    $addedKeys[] = $fieldKey;

                    $fieldType = $field['type'] ?? 'string';
                    $conditions = match($fieldType) {
                        'number', 'integer', 'float' => $numberConditions,
                        'date', 'datetime' => $dateConditions,
                        'select', 'enum' => $selectConditions,
                        default => $stringConditions,
                    };
                    $serviceAttributes[] = [
                        'key' => $fieldKey,
                        'label' => $field['label'] ?? $fieldKey,
                        'type' => $fieldType,
                        'service_type' => $type->key,
                        'conditions' => $conditions,
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer' => $attributes,
                'service' => $serviceAttributes,
                'service_types' => $serviceTypes,
            ],
        ]);
    }

    /**
     * Preview campaign messages with template rendering
     */
    public function previewCampaignMessages(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        $limit = $request->input('limit', 5);
        $executionEngine = app(\App\Services\CampaignExecutionEngine::class);
        $previewData = $executionEngine->previewMessages($campaign, $limit);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_count' => $previewData['total_count'],
                'sms_total' => $previewData['sms_total'] ?? 0,
                'email_total' => $previewData['email_total'] ?? 0,
                'previews' => $previewData['previews'],
                'campaign' => $campaign,
            ],
        ]);
    }

    /**
     * Get planned messages for next campaign run
     */
    public function plannedCampaignMessages(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 10);

        $executionEngine = app(\App\Services\CampaignExecutionEngine::class);
        $plannedData = $executionEngine->getPlannedMessages($campaign, $page, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $plannedData,
        ]);
    }

    /**
     * Get campaign message history
     */
    public function campaignMessages(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        // Fetch messages from the messages table using the Message model
        $query = \App\Models\Message::where('campaign_id', $campaignId)
            ->orderBy('created_at', 'desc');

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $messages = collect($paginated->items())->map(function ($msg) {
            return [
                'id' => $msg->id,
                'type' => $msg->channel,
                'recipient' => $msg->recipient,
                'content' => $msg->content,
                'subject' => $msg->subject,
                'status' => $msg->status,
                'cost' => $msg->cost,
                'segments' => $msg->segments,
                'sent_at' => $msg->sent_at,
                'created_at' => $msg->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'messages' => $messages,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ],
        ]);
    }

    /**
     * Test send to N matching contacts
     */
    public function testSendCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'count' => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $count = $request->input('count');
        $queryBuilder = app(\App\Services\SegmentQueryBuilder::class);
        $templateRenderer = app(\App\Services\TemplateRenderer::class);
        $smsService = app(\App\Services\QuickSmsService::class);

        $user = $campaign->getOwnerUser();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Campaign owner not found'], 500);
        }

        // Get matching contacts
        $contacts = $queryBuilder->getMatches($client->id, $campaign->segment_filter, $count);

        if ($contacts->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No contacts match the segment filter'], 422);
        }

        $costPerSms = config('app.sms_cost_per_message', 0.04);
        $globalTestMode = config('services.quicksms.test_mode', false);

        $results = [];
        $totalCost = 0;
        $sentCount = 0;
        $failedCount = 0;

        foreach ($contacts as $contact) {
            try {
                $message = $templateRenderer->renderStrict($campaign->message_template, $contact);
            } catch (\App\Exceptions\TemplateRenderException $e) {
                $results[] = [
                    'phone' => $contact->phone,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $failedCount++;
                continue;
            }

            $message = $templateRenderer->sanitizeForSMS($message);
            $segments = $templateRenderer->calculateSMSSegments($message);
            $cost = $segments * $costPerSms;

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
        ]);
    }

    /**
     * Test send to custom phone/email
     */
    public function testSendCampaignCustom(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string|regex:/^994[0-9]{9}$/',
            'email' => 'nullable|email|max:255',
            'sample_contact_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $phone = $request->input('phone');
        $email = $request->input('email');

        if (empty($phone) && empty($email)) {
            return response()->json(['status' => 'error', 'message' => 'Phone or email is required'], 422);
        }

        $user = $campaign->getOwnerUser();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Campaign owner not found'], 500);
        }

        // Get sample contact for template variables
        $sampleContactId = $request->input('sample_contact_id');
        $sampleContact = null;

        if ($sampleContactId) {
            $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                ->where('id', $sampleContactId)
                ->first();
        } else {
            if ($phone) {
                $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                    ->where('phone', $phone)
                    ->first();
            }
            if (!$sampleContact && $email) {
                $sampleContact = \App\Models\Contact::where('client_id', $client->id)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.email')) = ?", [$email])
                    ->first();
            }
            if (!$sampleContact) {
                $sampleContact = \App\Models\Contact::createSampleInstance($client->id, $phone, $email);
            }
        }

        $templateRenderer = app(\App\Services\TemplateRenderer::class);
        $results = ['sms' => null, 'email' => null];

        // Send SMS
        if ($phone && ($campaign->channel === 'sms' || $campaign->channel === 'both')) {
            $results['sms'] = $this->sendCampaignTestSms($campaign, $user, $sampleContact, $phone, $templateRenderer);
        }

        // Send Email
        if ($email && ($campaign->channel === 'email' || $campaign->channel === 'both')) {
            $results['email'] = $this->sendCampaignTestEmail($campaign, $user, $sampleContact, $email, $templateRenderer);
        }

        $anySuccess = ($results['sms']['status'] ?? null) === 'sent' || ($results['email']['status'] ?? null) === 'sent';

        return response()->json([
            'status' => $anySuccess ? 'success' : 'error',
            'message' => $anySuccess ? 'Test sent successfully' : 'Failed to send test',
            'data' => $results,
        ], $anySuccess ? 200 : 500);
    }

    /**
     * Helper: Send test SMS for campaign
     */
    private function sendCampaignTestSms(Campaign $campaign, $user, $sampleContact, string $phone, $templateRenderer): array
    {
        $smsService = app(\App\Services\QuickSmsService::class);
        $costPerSms = config('app.sms_cost_per_message', 0.04);
        $globalTestMode = config('services.quicksms.test_mode', false);

        $message = $templateRenderer->renderWithFallback($campaign->message_template ?? '', $sampleContact);
        $message = $templateRenderer->sanitizeForSMS($message);
        $segments = $templateRenderer->calculateSMSSegments($message);
        $cost = $segments * $costPerSms;

        if (!$globalTestMode && $user->balance < $cost) {
            return ['phone' => $phone, 'message' => $message, 'status' => 'failed', 'error' => 'Insufficient balance'];
        }

        if ($globalTestMode) {
            $status = 'sent';
            $error = null;
        } else {
            $unicode = $smsService->requiresUnicode($message);
            $result = $smsService->sendSMS($phone, $message, $campaign->sender, $unicode);

            if ($result['success']) {
                $status = 'sent';
                $error = null;
                $user->deductBalance($cost);

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
                    'provider_transaction_id' => $result['transaction_id'] ?? null,
                    'sent_at' => now(),
                ]);
            } else {
                $status = 'failed';
                $error = $result['error_message'] ?? 'Unknown error';
            }
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
     * Helper: Send test Email for campaign
     */
    private function sendCampaignTestEmail(Campaign $campaign, $user, $sampleContact, string $email, $templateRenderer): array
    {
        $costPerEmail = config('app.email_cost_per_message', 0.01);
        $globalTestMode = config('services.quicksms.test_mode', false);

        $subject = $templateRenderer->renderWithFallback($campaign->email_subject_template ?? '', $sampleContact);
        $bodyText = $templateRenderer->renderWithFallback($campaign->email_body_template ?? '', $sampleContact);

        $emailSenderDetails = \App\Models\UserEmailSender::getByEmail($campaign->email_sender ?? '');
        if (!$emailSenderDetails) {
            $emailSenderDetails = \App\Models\UserEmailSender::getDefault();
        }

        $cost = $costPerEmail;

        if (!$globalTestMode && $user->balance < $cost) {
            return ['email' => $email, 'subject' => $subject, 'status' => 'failed', 'error' => 'Insufficient balance'];
        }

        if ($globalTestMode) {
            $status = 'sent';
            $error = null;

            \App\Models\EmailMessage::create([
                'user_id' => $campaign->created_by,
                'source' => 'campaign',
                'client_id' => $campaign->client_id,
                'campaign_id' => $campaign->id,
                'to_email' => $email,
                'subject' => $subject,
                'body_text' => $bodyText,
                'from_email' => $emailSenderDetails['email'],
                'from_name' => $emailSenderDetails['name'],
                'cost' => 0,
                'status' => 'sent',
                'is_test' => true,
                'sent_at' => now(),
            ]);
        } else {
            try {
                $executionEngine = app(\App\Services\CampaignExecutionEngine::class);
                $emailService = $executionEngine->getEmailService();
                $result = $emailService->send(
                    $user,
                    $email,
                    $subject,
                    $bodyText,
                    $bodyText,
                    null,
                    $emailSenderDetails['email'],
                    $emailSenderDetails['name'],
                    'campaign',
                    $campaign->client_id,
                    $campaign->id,
                    $sampleContact->id ?: null
                );

                if ($result['success']) {
                    $status = 'sent';
                    $error = null;
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
     * Retry failed campaign messages
     */
    public function retryFailedCampaign(Request $request, $id, $campaignId): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $campaign = $client->campaigns()->find($campaignId);
        if (!$campaign) {
            return response()->json(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }

        // Get failed messages that are retryable
        $failedMessages = \App\Models\SmsMessage::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->whereNotNull('contact_id')
            ->get();

        if ($failedMessages->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No failed messages to retry',
                'data' => ['queued' => 0, 'skipped' => 0],
            ]);
        }

        $queued = 0;
        $skipped = 0;
        $skippedReasons = [];

        foreach ($failedMessages as $msg) {
            $errorMessage = $msg->error_message ?? '';
            $isPermanent = str_contains($errorMessage, 'Invalid phone') ||
                          str_contains($errorMessage, 'blacklisted') ||
                          str_contains($errorMessage, 'Invalid sender') ||
                          str_contains($errorMessage, 'Template error');

            if ($isPermanent) {
                $skipped++;
                $skippedReasons[] = ['phone' => $msg->phone, 'reason' => 'Permanent error'];
                continue;
            }

            $contact = \App\Models\Contact::find($msg->contact_id);
            if (!$contact) {
                $skipped++;
                $skippedReasons[] = ['phone' => $msg->phone, 'reason' => 'Contact not found'];
                continue;
            }

            \App\Jobs\SendCampaignMessage::dispatch($campaign, $contact);
            $queued++;

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
        ]);
    }

    /**
     * Get available SMS senders for the project
     */
    public function getSenders(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $senders = \App\Models\UserSender::getAvailableSenders($client->user_id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'senders' => $senders,
                'default' => \App\Models\UserSender::DEFAULT_SENDER,
            ],
        ]);
    }

    /**
     * Get available email senders for the project
     */
    public function getEmailSenders(Request $request, $id): JsonResponse
    {
        $client = $this->getClientForUser($request, (int) $id);
        if (!$client) {
            return $this->notFoundResponse();
        }

        $senders = \App\Models\UserEmailSender::getAvailableSenders($client->user_id);
        $default = \App\Models\UserEmailSender::getDefault();

        return response()->json([
            'status' => 'success',
            'data' => [
                'senders' => $senders,
                'default' => $default,
            ],
        ]);
    }
}
