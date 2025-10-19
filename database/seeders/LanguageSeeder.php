<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'title' => 'Azərbaycan dili',
                'lang_code' => 'az',
                'is_main' => true,
                'status' => true,
                'order' => 1,
            ],
            [
                'title' => 'English',
                'lang_code' => 'en',
                'is_main' => false,
                'status' => true,
                'order' => 2,
            ],
            [
                'title' => 'Русский',
                'lang_code' => 'ru',
                'is_main' => false,
                'status' => true,
                'order' => 3,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['lang_code' => $language['lang_code']],
                $language
            );
        }
    }
}
