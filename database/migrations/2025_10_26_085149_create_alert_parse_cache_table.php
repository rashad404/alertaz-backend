<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alert_parse_cache', function (Blueprint $table) {
            $table->id();

            // Original user input
            $table->text('input_text');

            // Normalized pattern for matching (e.g., "crypto_price_above_{value}")
            $table->string('normalized_pattern', 255)->index();

            // Extracted variables as JSON (e.g., {"crypto": "BTC", "value": 100000})
            $table->json('extracted_variables')->nullable();

            // Parsed result as JSON (full alert configuration)
            $table->json('parsed_result');

            // Confidence score from LLM (0.0 - 1.0)
            $table->decimal('confidence', 3, 2)->default(1.0);

            // How many times this pattern was used (for analytics)
            $table->unsignedInteger('usage_count')->default(1);

            // Track which AI provider was used
            $table->string('ai_provider', 50)->nullable();

            $table->timestamps();

            // Index for fast pattern lookup
            $table->index(['normalized_pattern', 'usage_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_parse_cache');
    }
};
