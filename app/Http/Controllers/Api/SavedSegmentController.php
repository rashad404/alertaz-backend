<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedSegment;
use App\Services\SegmentQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SavedSegmentController extends Controller
{
    protected SegmentQueryBuilder $queryBuilder;

    public function __construct(SegmentQueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * List saved segments
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $perPage = $request->input('per_page', 20);

        $segments = SavedSegment::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'segments' => $segments->items(),
                'pagination' => [
                    'current_page' => $segments->currentPage(),
                    'last_page' => $segments->lastPage(),
                    'per_page' => $segments->perPage(),
                    'total' => $segments->total(),
                ],
            ],
        ], 200);
    }

    /**
     * Get single saved segment
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $segment = SavedSegment::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$segment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Segment not found',
            ], 404);
        }

        // Calculate current match count
        $matchCount = $this->queryBuilder->countMatches($client->id, $segment->filter_config);

        return response()->json([
            'status' => 'success',
            'data' => [
                'segment' => $segment,
                'current_match_count' => $matchCount,
            ],
        ], 200);
    }

    /**
     * Create saved segment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'filter_config' => ['required', 'array'],
            'filter_config.logic' => ['nullable', 'in:AND,OR'],
            'filter_config.conditions' => ['required', 'array', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get authenticated user from client's user_id
        $userId = $client->user_id;

        $segment = SavedSegment::create([
            'client_id' => $client->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'filter_config' => $request->input('filter_config'),
            'created_by' => $userId,
        ]);

        // Calculate match count
        $matchCount = $this->queryBuilder->countMatches($client->id, $segment->filter_config);

        return response()->json([
            'status' => 'success',
            'message' => 'Segment saved successfully',
            'data' => [
                'segment' => $segment,
                'match_count' => $matchCount,
            ],
        ], 201);
    }

    /**
     * Update saved segment
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $segment = SavedSegment::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$segment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Segment not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'filter_config' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $segment->name = $request->input('name');
        }

        if ($request->has('description')) {
            $segment->description = $request->input('description');
        }

        if ($request->has('filter_config')) {
            $segment->filter_config = $request->input('filter_config');
        }

        $segment->save();

        // Calculate new match count
        $matchCount = $this->queryBuilder->countMatches($client->id, $segment->filter_config);

        return response()->json([
            'status' => 'success',
            'message' => 'Segment updated successfully',
            'data' => [
                'segment' => $segment,
                'match_count' => $matchCount,
            ],
        ], 200);
    }

    /**
     * Delete saved segment
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $request->attributes->get('client');

        $segment = SavedSegment::where('client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$segment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Segment not found',
            ], 404);
        }

        $segment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Segment deleted successfully',
        ], 200);
    }
}
