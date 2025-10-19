<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, convert existing string entity names to JSON format
        $entities = DB::table('company_entities')->get();
        
        foreach ($entities as $entity) {
            if ($entity->entity_name && !$this->isJson($entity->entity_name)) {
                // Convert string to JSON with default language (az)
                $jsonName = json_encode([
                    'az' => $entity->entity_name,
                    'en' => $entity->entity_name,
                    'ru' => $entity->entity_name
                ]);
                
                DB::table('company_entities')
                    ->where('id', $entity->id)
                    ->update(['entity_name' => $jsonName]);
            }
        }

        Schema::table('company_entities', function (Blueprint $table) {
            // Change entity_name from string to JSON
            $table->json('entity_name')->change();
        });
    }

    /**
     * Check if a string is valid JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_entities', function (Blueprint $table) {
            // Revert entity_name back to string
            $table->string('entity_name')->change();
        });
    }
};
