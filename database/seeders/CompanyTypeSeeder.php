<?php

namespace Database\Seeders;

use App\Models\CompanyType;
use Illuminate\Database\Seeder;

class CompanyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'title' => json_encode(['az' => 'Bank', 'en' => 'Banks', 'ru' => 'Банки']),
                'slug' => 'banks',
                'icon' => 'fa-university',
                'icon_alt_text' => json_encode(['az' => 'Bank ikonu', 'en' => 'Bank icon', 'ru' => 'Иконка банка']),
                'order' => 1,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Kredit təşkilatı', 'en' => 'Credit Organizations', 'ru' => 'Кредитные организации']),
                'slug' => 'credit-organizations',
                'icon' => 'fa-building',
                'icon_alt_text' => json_encode(['az' => 'Kredit təşkilatı ikonu', 'en' => 'Credit organization icon', 'ru' => 'Иконка кредитной организации']),
                'order' => 2,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Sığorta şirkəti', 'en' => 'Insurance Companies', 'ru' => 'Страховые компании']),
                'slug' => 'insurance',
                'icon' => 'fa-shield',
                'icon_alt_text' => json_encode(['az' => 'Sığorta ikonu', 'en' => 'Insurance icon', 'ru' => 'Иконка страхования']),
                'order' => 3,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'İnvestisiya şirkəti', 'en' => 'Investment Companies', 'ru' => 'Инвестиционные компании']),
                'slug' => 'investment',
                'icon' => 'fa-chart-line',
                'icon_alt_text' => json_encode(['az' => 'İnvestisiya ikonu', 'en' => 'Investment icon', 'ru' => 'Иконка инвестиций']),
                'order' => 4,
                'status' => true,
            ],
            [
                'title' => json_encode(['az' => 'Lizinq şirkəti', 'en' => 'Leasing Companies', 'ru' => 'Лизинговые компании']),
                'slug' => 'leasing',
                'icon' => 'fa-car',
                'icon_alt_text' => json_encode(['az' => 'Lizinq ikonu', 'en' => 'Leasing icon', 'ru' => 'Иконка лизинга']),
                'order' => 5,
                'status' => true,
            ],
        ];

        foreach ($types as $type) {
            CompanyType::create($type);
        }
    }
}