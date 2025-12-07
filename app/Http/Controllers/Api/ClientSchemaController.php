<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientAttributeSchema;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientSchemaController extends Controller
{
    /**
     * Register or update client attribute schema
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'attributes' => ['required', 'array', 'min:1'],
            'attributes.*.key' => ['required', 'string', 'max:100'],
            'attributes.*.type' => ['required', 'in:string,number,integer,date,boolean,enum,array'],
            'attributes.*.label' => ['required', 'string', 'max:255'],
            'attributes.*.options' => ['nullable', 'array'],
            'attributes.*.item_type' => ['nullable', 'string', 'in:object,string,number,integer,boolean'],
            'attributes.*.properties' => ['nullable', 'array'],
            'attributes.*.required' => ['nullable', 'boolean'],
            'attributes.*.metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Delete existing schemas for this client
        ClientAttributeSchema::where('client_id', $client->id)->delete();

        // Clear attributes for all contacts of this client (force re-sync)
        $contactsCleared = Contact::where('client_id', $client->id)->count();
        Contact::where('client_id', $client->id)->update(['attributes' => []]);

        // Create new schemas
        $attributesCount = 0;
        foreach ($request->input('attributes') as $attribute) {
            ClientAttributeSchema::create([
                'client_id' => $client->id,
                'attribute_key' => $attribute['key'],
                'attribute_type' => $attribute['type'],
                'label' => $attribute['label'],
                'options' => $attribute['options'] ?? null,
                'item_type' => $attribute['item_type'] ?? null,
                'properties' => $attribute['properties'] ?? null,
                'required' => $attribute['required'] ?? false,
                'metadata' => $attribute['metadata'] ?? null,
            ]);
            $attributesCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Schema registered successfully. Contact attributes have been cleared.',
            'data' => [
                'client_id' => $client->id,
                'attributes_count' => $attributesCount,
                'contacts_cleared' => $contactsCleared,
            ],
        ], 200);
    }

    /**
     * Get client schema
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function get(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $schemas = ClientAttributeSchema::where('client_id', $client->id)
            ->get()
            ->map(function ($schema) {
                return [
                    'key' => $schema->attribute_key,
                    'type' => $schema->attribute_type,
                    'label' => $schema->label,
                    'options' => $schema->options,
                    'item_type' => $schema->item_type,
                    'properties' => $schema->properties,
                    'required' => $schema->required,
                    'metadata' => $schema->metadata,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'attributes' => $schemas,
            ],
        ], 200);
    }
}
