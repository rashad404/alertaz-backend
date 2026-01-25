<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceTypeController extends BaseController
{
    /**
     * List all service types for the client
     */
    public function index(Request $request): JsonResponse
    {
        $serviceTypes = ServiceType::forClient($this->getClientId($request))
            ->orderBy('display_order')
            ->get()
            ->map(fn($type) => $this->formatServiceType($type));

        return $this->success($serviceTypes);
    }

    /**
     * Create a new service type
     */
    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateServiceType($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $clientId = $this->getClientId($request);

        // Check if key already exists
        $exists = ServiceType::forClient($clientId)
            ->where('key', $request->input('key'))
            ->exists();

        if ($exists) {
            return $this->error("Service type with key '{$request->input('key')}' already exists", 409);
        }

        $serviceType = ServiceType::create([
            'client_id' => $clientId,
            'key' => $request->input('key'),
            'label' => $request->input('label'),
            'icon' => $request->input('icon'),
            'user_link_field' => $request->input('user_link_field', 'phone'),
            'fields' => $request->input('fields', []),
            'display_order' => $request->input('display_order', 0),
        ]);

        return $this->created($this->formatServiceType($serviceType));
    }

    /**
     * Get a specific service type
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $serviceType = ServiceType::forClient($this->getClientId($request))
            ->where('key', $key)
            ->first();

        if (!$serviceType) {
            return $this->notFound("Service type '{$key}' not found");
        }

        // Include service count
        $data = $this->formatServiceType($serviceType);
        $data['services_count'] = $serviceType->services()->count();

        return $this->success($data);
    }

    /**
     * Update a service type
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $serviceType = ServiceType::forClient($this->getClientId($request))
            ->where('key', $key)
            ->first();

        if (!$serviceType) {
            return $this->notFound("Service type '{$key}' not found");
        }

        $updateData = [];

        if ($request->has('label')) {
            $updateData['label'] = $request->input('label');
        }
        if ($request->has('icon')) {
            $updateData['icon'] = $request->input('icon');
        }
        if ($request->has('user_link_field')) {
            $updateData['user_link_field'] = $request->input('user_link_field');
        }
        if ($request->has('fields')) {
            $updateData['fields'] = $request->input('fields');
        }
        if ($request->has('display_order')) {
            $updateData['display_order'] = $request->input('display_order');
        }

        $serviceType->update($updateData);

        return $this->success($this->formatServiceType($serviceType->fresh()));
    }

    /**
     * Delete a service type
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $serviceType = ServiceType::forClient($this->getClientId($request))
            ->where('key', $key)
            ->first();

        if (!$serviceType) {
            return $this->notFound("Service type '{$key}' not found");
        }

        // Check if there are services using this type
        $servicesCount = $serviceType->services()->count();
        if ($servicesCount > 0) {
            return $this->error("Cannot delete service type with {$servicesCount} existing services", 409);
        }

        $serviceType->delete();

        return $this->success(null, 'Service type deleted');
    }

    /**
     * Validate service type request
     */
    private function validateServiceType(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'key' => 'required|string|max:50|regex:/^[a-z][a-z0-9_]*$/',
            'label' => 'required|array',
            'label.en' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'user_link_field' => 'nullable|string|in:phone,email,external_id',
            'fields' => 'nullable|array',
            'fields.*.type' => 'required_with:fields|string|in:string,number,date,enum,boolean',
            'fields.*.label' => 'nullable|string|max:255',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.options' => 'nullable|array',
            'display_order' => 'nullable|integer|min:0',
        ], [
            'key.regex' => 'Key must start with a letter and contain only lowercase letters, numbers, and underscores',
        ]);
    }

    /**
     * Format service type for response
     */
    private function formatServiceType(ServiceType $type): array
    {
        return [
            'id' => $type->id,
            'key' => $type->key,
            'label' => $type->label,
            'icon' => $type->icon,
            'user_link_field' => $type->user_link_field,
            'fields' => $type->fields,
            'display_order' => $type->display_order,
            'created_at' => $type->created_at->toIso8601String(),
            'updated_at' => $type->updated_at->toIso8601String(),
        ];
    }
}
