<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ServiceController extends BaseController
{
    /**
     * List services with pagination and filtering
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $query = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->with('customer');

        // Apply filters
        $this->applyFilters($query, $request);

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->input('per_page', 25), 100);
        $services = $query->paginate($perPage);

        return $this->paginated($services->through(fn($s) => $this->formatService($s)));
    }

    /**
     * Bulk sync services (upsert up to 1000)
     */
    public function sync(Request $request, string $type): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $validator = Validator::make($request->all(), [
            'services' => 'required|array|max:1000',
            'services.*.external_id' => 'nullable|string|max:255',
            'services.*.name' => 'required|string|max:255',
            'services.*.expiry_at' => 'nullable|date',
            'services.*.status' => 'nullable|string|max:50',
            'services.*.data' => 'nullable|array',
            // Link to customer by one of these fields
            'services.*.customer_phone' => 'nullable|string|max:20',
            'services.*.customer_email' => 'nullable|email|max:255',
            'services.*.customer_external_id' => 'nullable|string|max:255',
            'services.*.customer_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $services = $request->input('services');
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        DB::beginTransaction();
        try {
            foreach ($services as $index => $serviceData) {
                try {
                    $result = $this->upsertService($clientId, $serviceType, $serviceData);
                    $results[$result]++;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'index' => $index,
                        'name' => $serviceData['name'] ?? 'unknown',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Sync failed: ' . $e->getMessage(), 500);
        }

        return $this->success($results);
    }

    /**
     * Create or update a single service
     */
    public function upsert(Request $request, string $type, string $externalId): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'expiry_at' => 'nullable|date',
            'status' => 'nullable|string|max:50',
            'data' => 'nullable|array',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_external_id' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $serviceData = array_merge($request->all(), ['external_id' => $externalId]);
        $result = $this->upsertService($clientId, $serviceType, $serviceData);

        $service = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->where('external_id', $externalId)
            ->with('customer')
            ->first();

        return $result === 'created'
            ? $this->created($this->formatService($service))
            : $this->success($this->formatService($service), 'Service updated');
    }

    /**
     * Get a single service
     */
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $service = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->where('id', $id)
            ->with('customer')
            ->first();

        if (!$service) {
            return $this->notFound('Service not found');
        }

        return $this->success($this->formatService($service));
    }

    /**
     * Delete a service
     */
    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $service = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->where('id', $id)
            ->first();

        if (!$service) {
            return $this->notFound('Service not found');
        }

        $service->delete();

        return $this->success(null, 'Service deleted');
    }

    /**
     * Bulk delete services
     */
    public function bulkDestroy(Request $request, string $type): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|max:1000',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $deleted = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->whereIn('id', $request->input('ids'))
            ->delete();

        return $this->success(['deleted' => $deleted]);
    }

    /**
     * Get stats for a service type
     */
    public function stats(Request $request, string $type): JsonResponse
    {
        $clientId = $this->getClientId($request);
        $serviceType = $this->getServiceType($clientId, $type);

        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $baseQuery = Service::forClient($clientId)->where('service_type_id', $serviceType->id);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'suspended' => (clone $baseQuery)->where('status', 'suspended')->count(),
            'expired' => (clone $baseQuery)->expired()->count(),
            'expiring_7_days' => (clone $baseQuery)->expiringWithinDays(7)->count(),
            'expiring_30_days' => (clone $baseQuery)->expiringWithinDays(30)->count(),
        ];

        return $this->success($stats);
    }

    /**
     * Get service type helper
     */
    private function getServiceType(int $clientId, string $key): ?ServiceType
    {
        return ServiceType::forClient($clientId)->where('key', $key)->first();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, Request $request): void
    {
        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Expiry filters
        if ($request->has('expiring_within_days')) {
            $days = (int) $request->input('expiring_within_days');
            $query->expiringWithinDays($days);
        }

        if ($request->boolean('expired')) {
            $query->expired();
        }

        if ($request->boolean('not_expired')) {
            $query->whereNotNull('expiry_at')
                  ->whereDate('expiry_at', '>=', Carbon::now());
        }

        // Customer filter
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        // Apply custom filter from JSON
        if ($filter = $request->input('filter')) {
            if (is_string($filter)) {
                $filter = json_decode($filter, true);
            }
            $query->applyFilter($filter);
        }
    }

    /**
     * Upsert a single service
     */
    private function upsertService(int $clientId, ServiceType $serviceType, array $data): string
    {
        // Resolve customer
        $customerId = $this->resolveCustomerId($clientId, $serviceType, $data);

        // Find existing service
        $service = null;
        if (!empty($data['external_id'])) {
            $service = Service::forClient($clientId)
                ->where('service_type_id', $serviceType->id)
                ->where('external_id', $data['external_id'])
                ->first();
        }

        // Prepare service data
        $serviceData = [
            'name' => $data['name'],
            'expiry_at' => !empty($data['expiry_at']) ? Carbon::parse($data['expiry_at']) : null,
            'status' => $data['status'] ?? 'active',
            'customer_id' => $customerId,
        ];

        // Extract known fields from data, put the rest in data JSON
        $knownFields = ['external_id', 'name', 'expiry_at', 'status', 'customer_phone', 'customer_email', 'customer_external_id', 'customer_id', 'data'];
        $extraData = array_diff_key($data, array_flip($knownFields));
        $serviceData['data'] = array_merge($data['data'] ?? [], $extraData);

        if ($service) {
            // Update existing
            $service->update($serviceData);
            return 'updated';
        }

        // Create new
        Service::create(array_merge($serviceData, [
            'client_id' => $clientId,
            'service_type_id' => $serviceType->id,
            'external_id' => $data['external_id'] ?? null,
        ]));

        return 'created';
    }

    /**
     * Resolve customer ID from service data
     */
    private function resolveCustomerId(int $clientId, ServiceType $serviceType, array $data): ?int
    {
        // Direct customer ID
        if (!empty($data['customer_id'])) {
            $customer = Customer::forClient($clientId)->find($data['customer_id']);
            return $customer?->id;
        }

        // Resolve by user_link_field from service type
        $linkField = $serviceType->user_link_field;
        $lookupKey = "customer_{$linkField}";

        if (!empty($data[$lookupKey])) {
            $customer = Customer::forClient($clientId)
                ->where($linkField, $data[$lookupKey])
                ->first();
            return $customer?->id;
        }

        // Try other identifiers
        if (!empty($data['customer_phone'])) {
            $customer = Customer::forClient($clientId)->where('phone', $data['customer_phone'])->first();
            if ($customer) return $customer->id;
        }

        if (!empty($data['customer_email'])) {
            $customer = Customer::forClient($clientId)->where('email', $data['customer_email'])->first();
            if ($customer) return $customer->id;
        }

        if (!empty($data['customer_external_id'])) {
            $customer = Customer::forClient($clientId)->where('external_id', $data['customer_external_id'])->first();
            if ($customer) return $customer->id;
        }

        return null;
    }

    /**
     * Format service for response
     */
    private function formatService(Service $service): array
    {
        return [
            'id' => $service->id,
            'external_id' => $service->external_id,
            'name' => $service->name,
            'expiry_at' => $service->expiry_at?->toDateString(),
            'days_until_expiry' => $service->getDaysUntilExpiry(),
            'status' => $service->status,
            'data' => $service->data,
            'customer' => $service->customer ? [
                'id' => $service->customer->id,
                'name' => $service->customer->name,
                'phone' => $service->customer->phone,
                'email' => $service->customer->email,
            ] : null,
            'created_at' => $service->created_at->toIso8601String(),
            'updated_at' => $service->updated_at->toIso8601String(),
        ];
    }
}
