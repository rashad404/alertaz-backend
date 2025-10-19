<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'title' => json_encode(['az' => 'Maliyyə', 'en' => 'Finance', 'ru' => 'Финансы']),
                'slug' => 'maliyye',
                'order' => 1,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Kredit', 'en' => 'Credit', 'ru' => 'Кредит']),
                'slug' => 'kredit',
                'order' => 2,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Bank xəbərləri', 'en' => 'Bank News', 'ru' => 'Банковские новости']),
                'slug' => 'bank-xeberleri',
                'order' => 3,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'İqtisadiyyat', 'en' => 'Economy', 'ru' => 'Экономика']),
                'slug' => 'iqtisadiyyat',
                'order' => 4,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Texnologiya', 'en' => 'Technology', 'ru' => 'Технологии']),
                'slug' => 'texnologiya',
                'order' => 5,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Məsləhətlər', 'en' => 'Advice', 'ru' => 'Советы']),
                'slug' => 'meslehetler',
                'order' => 6,
                'status' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}