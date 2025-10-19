<?php

namespace Database\Seeders;

use App\Models\OffersCategory;
use Illuminate\Database\Seeder;

class OffersCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'title' => ['az' => 'Nağd kredit', 'en' => 'Cash Credit', 'ru' => 'Наличный кредит'],
                'slug' => 'nagd-kredit',
                'order' => 1,
                'status' => true,
            ],
            [
                'title' => ['az' => 'İpoteka krediti', 'en' => 'Mortgage Loan', 'ru' => 'Ипотечный кредит'],
                'slug' => 'ipoteka-krediti',
                'order' => 2,
                'status' => true,
            ],
            [
                'title' => ['az' => 'Avtomobil krediti', 'en' => 'Auto Loan', 'ru' => 'Автокредит'],
                'slug' => 'avtomobil-krediti',
                'order' => 3,
                'status' => true,
            ],
            [
                'title' => ['az' => 'İstehlak krediti', 'en' => 'Consumer Credit', 'ru' => 'Потребительский кредит'],
                'slug' => 'istehlak-krediti',
                'order' => 4,
                'status' => true,
            ],
            [
                'title' => ['az' => 'Biznes krediti', 'en' => 'Business Loan', 'ru' => 'Бизнес кредит'],
                'slug' => 'biznes-krediti',
                'order' => 5,
                'status' => true,
            ],
            [
                'title' => ['az' => 'Təhsil krediti', 'en' => 'Education Loan', 'ru' => 'Образовательный кредит'],
                'slug' => 'tehsil-krediti',
                'order' => 6,
                'status' => true,
            ],
            [
                'title' => ['az' => 'Lombard krediti', 'en' => 'Pawnshop Loan', 'ru' => 'Ломбардный кредит'],
                'slug' => 'lombard-krediti',
                'order' => 7,
                'status' => true,
            ],
            [
                'title' => ['az' => 'Kredit xətti', 'en' => 'Credit Line', 'ru' => 'Кредитная линия'],
                'slug' => 'kredit-xetti',
                'order' => 8,
                'status' => true,
            ],
        ];

        foreach ($categories as $category) {
            OffersCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}