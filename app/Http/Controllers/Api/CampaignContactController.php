<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignContactController extends Controller
{
    /**
     * Sync single contact
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^994[0-9]{9}$/'],
            'attributes' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        $attributes = $request->input('attributes');

        // Find or create contact
        $contact = Contact::updateOrCreate(
            [
                'client_id' => $client->id,
                'phone' => $phone,
            ],
            [
                'attributes' => $attributes,
            ]
        );

        $wasRecentlyCreated = $contact->wasRecentlyCreated;

        return response()->json([
            'status' => 'success',
            'message' => 'Contact synced successfully',
            'data' => [
                'contact_id' => $contact->id,
                'phone' => $contact->phone,
                'created' => $wasRecentlyCreated,
                'updated' => !$wasRecentlyCreated,
            ],
        ], 200);
    }

    /**
     * Bulk sync contacts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkSync(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'contacts' => ['required', 'array', 'min:1', 'max:1000'],
            'contacts.*.phone' => ['required', 'string', 'regex:/^994[0-9]{9}$/'],
            'contacts.*.attributes' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($request->input('contacts') as $contactData) {
            try {
                $contact = Contact::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'phone' => $contactData['phone'],
                    ],
                    [
                        'attributes' => $contactData['attributes'],
                    ]
                );

                if ($contact->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bulk sync completed',
            'data' => [
                'total' => count($request->input('contacts')),
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ],
        ], 200);
    }

    /**
     * List contacts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $perPage = $request->input('per_page', 50);

        $contacts = Contact::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'contacts' => $contacts->items(),
                'pagination' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ],
            ],
        ], 200);
    }

    /**
     * Delete contact
     *
     * @param Request $request
     * @param string $phone
     * @return JsonResponse
     */
    public function destroy(Request $request, string $phone): JsonResponse
    {
        $client = $request->attributes->get('client');

        $contact = Contact::where('client_id', $client->id)
            ->where('phone', $phone)
            ->first();

        if (!$contact) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contact not found',
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Contact deleted successfully',
        ], 200);
    }
}
