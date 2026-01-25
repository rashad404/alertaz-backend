<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerController extends BaseController
{
    /**
     * List customers with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::forClient($this->getClientId($request));

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('external_id', 'LIKE', "%{$search}%");
            });
        }

        // Filter by has phone
        if ($request->has('has_phone')) {
            if ($request->boolean('has_phone')) {
                $query->whereNotNull('phone');
            } else {
                $query->whereNull('phone');
            }
        }

        // Filter by has email
        if ($request->has('has_email')) {
            if ($request->boolean('has_email')) {
                $query->whereNotNull('email');
            } else {
                $query->whereNull('email');
            }
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->input('per_page', 25), 100);
        $customers = $query->paginate($perPage);

        return $this->paginated($customers->through(fn($c) => $this->formatCustomer($c)));
    }

    /**
     * Bulk sync customers (upsert up to 1000)
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customers' => 'required|array|max:1000',
            'customers.*.phone' => 'nullable|string|max:20',
            'customers.*.email' => 'nullable|email|max:255',
            'customers.*.external_id' => 'nullable|string|max:255',
            'customers.*.name' => 'nullable|string|max:255',
            'customers.*.data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $clientId = $this->getClientId($request);
        $customers = $request->input('customers');
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        DB::beginTransaction();
        try {
            foreach ($customers as $index => $customerData) {
                // At least one identifier required
                if (empty($customerData['phone']) && empty($customerData['email']) && empty($customerData['external_id'])) {
                    $results['errors'][] = [
                        'index' => $index,
                        'message' => 'At least one of phone, email, or external_id is required',
                    ];
                    continue;
                }

                $result = $this->upsertCustomer($clientId, $customerData);
                $results[$result]++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Sync failed: ' . $e->getMessage(), 500);
        }

        return $this->success($results);
    }

    /**
     * Create or update a single customer
     */
    public function upsert(Request $request, string $identifier): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'external_id' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $clientId = $this->getClientId($request);
        $customerData = array_merge($request->all(), $this->identifierToData($identifier));

        $result = $this->upsertCustomer($clientId, $customerData);

        $customer = Customer::forClient($clientId)
            ->findByIdentifier($clientId, $identifier)
            ->first();

        return $result === 'created'
            ? $this->created($this->formatCustomer($customer))
            : $this->success($this->formatCustomer($customer), 'Customer updated');
    }

    /**
     * Get a single customer
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $customer = Customer::forClient($this->getClientId($request))
            ->findByIdentifier($this->getClientId($request), $identifier)
            ->first();

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $data = $this->formatCustomer($customer);
        $data['services_count'] = $customer->services()->count();

        return $this->success($data);
    }

    /**
     * Delete a customer
     */
    public function destroy(Request $request, string $identifier): JsonResponse
    {
        $customer = Customer::forClient($this->getClientId($request))
            ->findByIdentifier($this->getClientId($request), $identifier)
            ->first();

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $customer->delete();

        return $this->success(null, 'Customer deleted');
    }

    /**
     * Bulk delete customers
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifiers' => 'required|array|max:1000',
            'identifiers.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $clientId = $this->getClientId($request);
        $deleted = 0;

        foreach ($request->input('identifiers') as $identifier) {
            $customer = Customer::forClient($clientId)
                ->findByIdentifier($clientId, $identifier)
                ->first();

            if ($customer) {
                $customer->delete();
                $deleted++;
            }
        }

        return $this->success(['deleted' => $deleted]);
    }

    /**
     * Upsert a single customer (internal)
     */
    private function upsertCustomer(int $clientId, array $data): string
    {
        $customer = null;

        // Try to find existing customer
        if (!empty($data['external_id'])) {
            $customer = Customer::forClient($clientId)->where('external_id', $data['external_id'])->first();
        }
        if (!$customer && !empty($data['phone'])) {
            $customer = Customer::forClient($clientId)->where('phone', $data['phone'])->first();
        }
        if (!$customer && !empty($data['email'])) {
            $customer = Customer::forClient($clientId)->where('email', $data['email'])->first();
        }

        if ($customer) {
            // Update existing
            $customer->update([
                'phone' => $data['phone'] ?? $customer->phone,
                'email' => $data['email'] ?? $customer->email,
                'external_id' => $data['external_id'] ?? $customer->external_id,
                'name' => $data['name'] ?? $customer->name,
                'data' => array_merge($customer->data ?? [], $data['data'] ?? []),
            ]);
            return 'updated';
        }

        // Create new
        Customer::create([
            'client_id' => $clientId,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'name' => $data['name'] ?? null,
            'data' => $data['data'] ?? null,
        ]);

        return 'created';
    }

    /**
     * Convert identifier to data array
     */
    private function identifierToData(string $identifier): array
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $identifier];
        }
        if (preg_match('/^[0-9+]+$/', $identifier)) {
            return ['phone' => $identifier];
        }
        return ['external_id' => $identifier];
    }

    /**
     * Format customer for response
     */
    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'external_id' => $customer->external_id,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'name' => $customer->name,
            'data' => $customer->data,
            'created_at' => $customer->created_at->toIso8601String(),
            'updated_at' => $customer->updated_at->toIso8601String(),
        ];
    }
}
