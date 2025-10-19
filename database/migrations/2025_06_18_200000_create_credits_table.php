<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->json('credit_name');
            $table->string('credit_image')->nullable();
            $table->json('about')->nullable();
            $table->json('credit_type')->nullable();
            $table->decimal('credit_amount', 15, 2)->nullable();
            $table->string('credit_term')->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->json('guarantor')->nullable();
            $table->json('collateral')->nullable();
            $table->json('method_of_purchase')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->json('seo_title')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->json('seo_description')->nullable();
            $table->boolean('status')->default(1);
            $table->integer('order')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('credits');
    }
}; 