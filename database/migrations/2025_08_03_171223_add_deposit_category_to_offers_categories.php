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
        // Add deposit categories
        DB::table('offers_categories')->insert([
            [
                'title' => json_encode([
                    'az' => 'Depozit',
                    'en' => 'Deposit',
                    'ru' => 'Депозит'
                ]),
                'slug' => 'deposit',
                'status' => true,
                'order' => 10,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => json_encode([
                    'az' => 'Müddətli depozit',
                    'en' => 'Term Deposit',
                    'ru' => 'Срочный депозит'
                ]),
                'slug' => 'term-deposit',
                'status' => true,
                'order' => 11,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => json_encode([
                    'az' => 'Yığım depoziti',
                    'en' => 'Savings Deposit',
                    'ru' => 'Накопительный депозит'
                ]),
                'slug' => 'savings-deposit',
                'status' => true,
                'order' => 12,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('offers_categories')
            ->whereIn('slug', ['deposit', 'term-deposit', 'savings-deposit'])
            ->delete();
    }
};