<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Insurance categories table
        Schema::create('insurance_categories', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Insurance companies (can be banks or insurance companies)
        Schema::create('insurance_providers', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->json('description')->nullable();
            $table->string('website')->nullable();
            $table->json('phones')->nullable();
            $table->json('email')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Insurance products/offers
        Schema::create('insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('insurance_categories');
            $table->foreignId('provider_id')->constrained('insurance_providers');
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->json('coverage_amount')->nullable(); // Min-max coverage
            $table->json('premium')->nullable(); // Price/premium info
            $table->json('duration')->nullable(); // Coverage period
            $table->json('features')->nullable(); // Key features list
            $table->json('requirements')->nullable(); // Requirements list
            $table->json('documents')->nullable(); // Required documents
            $table->json('exclusions')->nullable(); // What's not covered
            $table->string('image')->nullable();
            $table->integer('order')->default(0);
            $table->integer('views')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('status')->default(true);
            $table->json('seo_title')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->json('seo_description')->nullable();
            $table->timestamps();
        });

        // Insurance advantages
        Schema::create('insurance_advantages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_id')->constrained('insurances')->onDelete('cascade');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('insurance_advantages');
        Schema::dropIfExists('insurances');
        Schema::dropIfExists('insurance_providers');
        Schema::dropIfExists('insurance_categories');
    }
};