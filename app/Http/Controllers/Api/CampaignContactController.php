<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ClientAttributeSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignContactController extends Controller
{
    /**
     * Sanitize contact attributes - validate date fields and set invalid ones to null
     *
     * @param int $clientId
     * @param array $attributes
     * @return array
     */
    protected function sanitizeAttributes(int $clientId, array $attributes): array
    {
        // Get date attributes from schema
        $dateAttributes = ClientAttributeSchema::where('client_id', $clientId)
            ->where('attribute_type', 'date')
            ->pluck('attribute_key')
            ->toArray();

        // Validate each date attribute
        foreach ($dateAttributes as $key) {
            if (isset($attributes[$key])) {
                $value = $attributes[$key];

                // Check if it's a valid date
                if (!$this->isValidDate($value)) {
                    $attributes[$key] = null;
                }
            }
        }

        // Get array attributes with object items that have date properties
        $arrayAttributes = ClientAttributeSchema::where('client_id', $clientId)
            ->where('attribute_type', 'array')
            ->where('item_type', 'object')
            ->get();

        // Validate dates inside array of objects
        foreach ($arrayAttributes as $arrayAttr) {
            $key = $arrayAttr->attribute_key;
            $properties = $arrayAttr->properties ?? [];

            if (!isset($attributes[$key]) || !is_array($attributes[$key])) {
                continue;
            }

            // Find date properties in this array's item schema
            $dateProperties = [];
            foreach ($properties as $propKey => $propType) {
                if ($propType === 'date') {
                    $dateProperties[] = $propKey;
                }
            }

            // Validate each item in the array
            foreach ($attributes[$key] as $idx => $item) {
                if (!is_array($item)) {
                    continue;
                }

                foreach ($dateProperties as $dateProp) {
                    if (isset($item[$dateProp]) && !$this->isValidDate($item[$dateProp])) {
                        $attributes[$key][$idx][$dateProp] = null;
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * Check if a value is a valid, realistic date
     *
     * @param mixed $value
     * @return bool
     */
    protected function isValidDate($value): bool
    {
        // Must be a string
        if (!is_string($value) || empty($value)) {
            return false;
        }

        // Try to parse the date
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        // Check if parsing succeeded and the date matches the input
        if (!$date || $date->format('Y-m-d') !== $value) {
            // Try datetime format as well
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if (!$date) {
                return false;
            }
        }

        // Check if the year is realistic (between 1900 and 2100)
        $year = (int) $date->format('Y');
        if ($year < 1900 || $year > 2100) {
            return false;
        }

        return true;
    }

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
            'phone' => ['nullable', 'string', 'regex:/^994[0-9]{9}$/'],
            // Note: email is now expected in attributes.email, not at root level
            'attributes' => ['required', 'array'],
            'attributes.email' => ['nullable', 'email', 'max:255'],
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
        $attributesEmail = $attributes['email'] ?? null;

        // At least one of phone or attributes.email is required
        if (empty($phone) && empty($attributesEmail)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['contact' => ['Phone or email (in attributes) is required']],
            ], 422);
        }

        // Sanitize date attributes
        $attributes = $this->sanitizeAttributes($client->id, $attributes);

        // Build unique identifier - use phone only
        $identifier = [];
        $identifier['client_id'] = $client->id;
        if ($phone) {
            $identifier['phone'] = $phone;
        }
        // Note: email column is no longer used, email is stored in attributes['email']

        // Build update data - email is in attributes, not as separate column
        $updateData = ['attributes' => $attributes];
        if ($phone) {
            $updateData['phone'] = $phone;
        }

        // Find or create contact
        $contact = Contact::updateOrCreate($identifier, $updateData);

        $wasRecentlyCreated = $contact->wasRecentlyCreated;

        return response()->json([
            'status' => 'success',
            'message' => 'Contact synced successfully',
            'data' => [
                'contact_id' => $contact->id,
                'phone' => $contact->phone,
                'email' => $attributes['email'] ?? null,
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
            'contacts.*.phone' => ['nullable', 'string', 'regex:/^994[0-9]{9}$/'],
            // Note: email is now expected in attributes.email, not at root level
            'contacts.*.attributes' => ['required', 'array'],
            'contacts.*.attributes.email' => ['nullable', 'email', 'max:255'],
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
        $skipped = 0;

        foreach ($request->input('contacts') as $index => $contactData) {
            $phone = $contactData['phone'] ?? null;
            // Email should come from attributes['email'], not root level
            $attributesEmail = $contactData['attributes']['email'] ?? null;

            // Skip contacts without phone or email (check attributes.email)
            if (empty($phone) && empty($attributesEmail)) {
                $skipped++;
                continue;
            }

            try {
                // Sanitize date attributes
                $attributes = $this->sanitizeAttributes($client->id, $contactData['attributes']);

                // Build unique identifier - prefer phone, then attributes.email
                $identifier = [];
                $identifier['client_id'] = $client->id;
                if ($phone) {
                    $identifier['phone'] = $phone;
                }
                // Note: email column is no longer used, email is stored in attributes['email']

                // Build update data - email is in attributes, not as separate column
                $updateData = ['attributes' => $attributes];
                if ($phone) {
                    $updateData['phone'] = $phone;
                }

                $contact = Contact::updateOrCreate($identifier, $updateData);

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
                'skipped' => $skipped,
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

        // Search by phone, email or attributes
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereRaw("JSON_SEARCH(LOWER(attributes), 'all', LOWER(?)) IS NOT NULL", ["%{$search}%"]);
            });
        }

        $contacts = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Get last sync date (most recent updated_at)
        $lastSyncAt = Contact::where('client_id', $client->id)
            ->max('updated_at');

        // Convert to ISO 8601 format with UTC timezone indicator
        // This ensures JavaScript interprets it correctly as UTC
        $lastSyncAtFormatted = $lastSyncAt
            ? \Carbon\Carbon::parse($lastSyncAt)->toIso8601String()
            : null;

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
                'last_sync_at' => $lastSyncAtFormatted,
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
            'phone' => ['nullable', 'string', 'regex:/^994[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
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
        $email = $request->input('email');

        // At least one of phone or email is required
        if (empty($phone) && empty($email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['contact' => ['Phone or email is required']],
            ], 422);
        }

        // Check if contact already exists by phone or email
        if ($phone) {
            $existing = Contact::where('client_id', $client->id)
                ->where('phone', $phone)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact with this phone number already exists',
                ], 409);
            }
        }

        if ($email) {
            $existing = Contact::where('client_id', $client->id)
                ->where('email', $email)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact with this email address already exists',
                ], 409);
            }
        }

        // Sanitize date attributes
        $attributes = $this->sanitizeAttributes($client->id, $request->input('attributes', []));

        $contact = Contact::create([
            'client_id' => $client->id,
            'phone' => $phone,
            'email' => $email,
            'attributes' => $attributes,
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
     * @param string $identifier Phone or email to find the contact
     * @return JsonResponse
     */
    public function update(Request $request, string $identifier): JsonResponse
    {
        $client = $request->attributes->get('client');

        // Find contact by phone or email
        $contact = Contact::where('client_id', $client->id)
            ->where(function ($query) use ($identifier) {
                $query->where('phone', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();

        if (!$contact) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contact not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['nullable', 'string', 'regex:/^994[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
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
        $newEmail = $request->input('email');

        // If phone is being changed, check for duplicates
        if ($newPhone && $newPhone !== $contact->phone) {
            $existing = Contact::where('client_id', $client->id)
                ->where('phone', $newPhone)
                ->where('id', '!=', $contact->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact with this phone number already exists',
                ], 409);
            }

            $contact->phone = $newPhone;
        }

        // If email is being changed, check for duplicates
        if ($newEmail && $newEmail !== $contact->email) {
            $existing = Contact::where('client_id', $client->id)
                ->where('email', $newEmail)
                ->where('id', '!=', $contact->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact with this email address already exists',
                ], 409);
            }

            $contact->email = $newEmail;
        }

        // Ensure at least one of phone or email remains
        $finalPhone = $request->has('phone') ? $newPhone : $contact->phone;
        $finalEmail = $request->has('email') ? $newEmail : $contact->email;

        if (empty($finalPhone) && empty($finalEmail)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['contact' => ['Phone or email is required']],
            ], 422);
        }

        if ($request->has('attributes')) {
            // Sanitize date attributes
            $attributes = $this->sanitizeAttributes($client->id, $request->input('attributes'));
            $contact->attributes = $attributes;
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
            $headers = ['phone', 'email', ...$attributeKeys, 'created_at'];
            fputcsv($handle, $headers);

            // Data rows
            foreach ($contacts as $contact) {
                $row = [$contact->phone, $contact->email];

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
