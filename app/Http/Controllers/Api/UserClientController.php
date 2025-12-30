<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
                        'contacts_count' => $client->contacts()->count(),
                        'campaigns_count' => $client->campaigns()->count(),
                        'active_campaigns' => $client->campaigns()->where('status', 'sending')->count(),
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
                        'contacts_count' => $client->contacts()->count(),
                        'campaigns_count' => $client->campaigns()->count(),
                        'schemas_count' => $client->attributeSchemas()->count(),
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
}
