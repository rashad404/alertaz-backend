<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $search = $request->input('search');

        $query = Contact::where('client_id', $client->id);

        // Search by phone or attributes
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhereRaw("JSON_SEARCH(LOWER(attributes), 'all', LOWER(?)) IS NOT NULL", ["%{$search}%"]);
            });
        }

        $contacts = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Get last sync date (most recent updated_at)
        $lastSyncAt = Contact::where('client_id', $client->id)
            ->max('updated_at');

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
                'last_sync_at' => $lastSyncAt,
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

    /**
     * Create single contact (for UI)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^994[0-9]{9}$/'],
            'attributes' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');

        // Check if contact already exists
        $existing = Contact::where('client_id', $client->id)
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contact with this phone number already exists',
            ], 409);
        }

        $contact = Contact::create([
            'client_id' => $client->id,
            'phone' => $phone,
            'attributes' => $request->input('attributes', []),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Contact created successfully',
            'data' => [
                'contact' => $contact,
            ],
        ], 201);
    }

    /**
     * Update single contact
     *
     * @param Request $request
     * @param string $phone
     * @return JsonResponse
     */
    public function update(Request $request, string $phone): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'phone' => ['nullable', 'string', 'regex:/^994[0-9]{9}$/'],
            'attributes' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newPhone = $request->input('phone');

        // If phone is being changed, check for duplicates
        if ($newPhone && $newPhone !== $phone) {
            $existing = Contact::where('client_id', $client->id)
                ->where('phone', $newPhone)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact with this phone number already exists',
                ], 409);
            }

            $contact->phone = $newPhone;
        }

        if ($request->has('attributes')) {
            $contact->attributes = $request->input('attributes');
        }

        $contact->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Contact updated successfully',
            'data' => [
                'contact' => $contact,
            ],
        ], 200);
    }

    /**
     * Bulk delete contacts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'phones' => ['required', 'array', 'min:1'],
            'phones.*' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phones = $request->input('phones');

        $deleted = Contact::where('client_id', $client->id)
            ->whereIn('phone', $phones)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Contacts deleted successfully',
            'data' => [
                'deleted' => $deleted,
            ],
        ], 200);
    }

    /**
     * Export contacts to CSV
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function export(Request $request): StreamedResponse
    {
        $client = $request->attributes->get('client');

        $contacts = Contact::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all unique attribute keys
        $attributeKeys = [];
        foreach ($contacts as $contact) {
            if (is_array($contact->attributes)) {
                $attributeKeys = array_merge($attributeKeys, array_keys($contact->attributes));
            }
        }
        $attributeKeys = array_unique($attributeKeys);

        $response = new StreamedResponse(function () use ($contacts, $attributeKeys) {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            $headers = ['phone', ...$attributeKeys, 'created_at'];
            fputcsv($handle, $headers);

            // Data rows
            foreach ($contacts as $contact) {
                $row = [$contact->phone];

                foreach ($attributeKeys as $key) {
                    $value = $contact->attributes[$key] ?? '';
                    // Handle arrays/objects
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    }
                    $row[] = $value;
                }

                $row[] = $contact->created_at->format('Y-m-d H:i:s');
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $filename = 'contacts_' . date('Y-m-d_His') . '.csv';

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
