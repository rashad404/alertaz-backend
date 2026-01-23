<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAttributeSchema;
use App\Services\SegmentQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SegmentController extends Controller
{
    protected SegmentQueryBuilder $queryBuilder;

    public function __construct(SegmentQueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Get available attributes for segment building
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAttributes(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $schemas = ClientAttributeSchema::where('client_id', $client->id)
            ->get()
            ->map(function ($schema) {
                return [
                    'key' => $schema->attribute_key,
                    'label' => $schema->label,
                    'type' => $schema->attribute_type,
                    'conditions' => $schema->getConditionsForType(),
                    'options' => $schema->options,
                    'item_type' => $schema->item_type,
                    'required' => $schema->required,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'attributes' => $schemas,
            ],
        ], 200);
    }

    /**
     * Preview segment (count and sample contacts)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function preview(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'filter' => ['required', 'array'],
            'filter.logic' => ['nullable', 'in:AND,OR'],
            'filter.conditions' => ['required', 'array', 'min:1'],
            'filter.conditions.*.key' => ['required', 'string'],
            'filter.conditions.*.operator' => ['required', 'string'],
            'filter.conditions.*.value' => ['nullable'],
            'preview_limit' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filter = $request->input('filter');
        $previewLimit = $request->input('preview_limit', 10);

        try {
            // Count total matches
            $totalCount = $this->queryBuilder->countMatches($client->id, $filter);

            // Get sample contacts (skip if preview_limit is 0)
            $sampleContacts = $previewLimit > 0
                ? $this->queryBuilder->getMatches($client->id, $filter, $previewLimit)
                : collect();

            $responseData = [
                'total_count' => $totalCount,
                'preview_count' => $sampleContacts->count(),
                'preview_contacts' => $sampleContacts->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'phone' => $contact->phone,
                        'attributes' => $contact->attributes,
                        'created_at' => $contact->created_at->toIso8601String(),
                    ];
                }),
            ];

            // Add debug SQL for client_id 1 only
            if ($client->id === 1) {
                $responseData['debug_sql'] = $this->queryBuilder->getDebugSql($client->id, $filter);
                $responseData['debug_filter'] = $filter;
            }

            return response()->json([
                'status' => 'success',
                'data' => $responseData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to preview segment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate segment filter
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'filter' => ['required', 'array'],
            'filter.logic' => ['nullable', 'in:AND,OR'],
            'filter.conditions' => ['required', 'array', 'min:1'],
            'filter.conditions.*.key' => ['required', 'string'],
            'filter.conditions.*.operator' => ['required', 'string'],
            'filter.conditions.*.value' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filter = $request->input('filter');

        // Validate that all condition keys exist in schema
        $schemas = ClientAttributeSchema::where('client_id', $client->id)
            ->pluck('attribute_key')
            ->toArray();

        $errors = [];
        foreach ($filter['conditions'] as $index => $condition) {
            if (!in_array($condition['key'], $schemas)) {
                $errors[] = "Condition {$index}: attribute '{$condition['key']}' does not exist in schema";
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid filter conditions',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Filter is valid',
        ], 200);
    }
}
