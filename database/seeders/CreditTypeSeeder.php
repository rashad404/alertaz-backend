<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CreditType;

class CreditTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $creditTypes = [
            [
                'slug' => 'cash',
                'name' => [
                    'az' => 'Nağd kredit',
                    'en' => 'Cash loan',
                    'ru' => 'Наличный кредит'
                ],
                'description' => [
                    'az' => 'Hər hansı məqsəd üçün nağd pul krediti',
                    'en' => 'Cash loan for any purpose',
                    'ru' => 'Наличный кредит на любые цели'
                ],
                'order' => 1
            ],
            [
                'slug' => 'mortgage',
                'name' => [
                    'az' => 'İpoteka',
                    'en' => 'Mortgage',
                    'ru' => 'Ипотека'
                ],
                'description' => [
                    'az' => 'Mənzil və ya ev almaq üçün ipoteka krediti',
                    'en' => 'Mortgage loan for buying property',
                    'ru' => 'Ипотечный кредит на покупку недвижимости'
                ],
                'order' => 2
            ],
            [
                'slug' => 'auto',
                'name' => [
                    'az' => 'Avto kredit',
                    'en' => 'Auto loan',
                    'ru' => 'Автокредит'
                ],
                'description' => [
                    'az' => 'Avtomobil almaq üçün kredit',
                    'en' => 'Loan for purchasing a vehicle',
                    'ru' => 'Кредит на покупку автомобиля'
                ],
                'order' => 3
            ],
            [
                'slug' => 'business',
                'name' => [
                    'az' => 'Biznes kredit',
                    'en' => 'Business loan',
                    'ru' => 'Бизнес кредит'
                ],
                'description' => [
                    'az' => 'Biznesin inkişafı üçün kredit',
                    'en' => 'Loan for business development',
                    'ru' => 'Кредит для развития бизнеса'
                ],
                'order' => 4
            ],
            [
                'slug' => 'education',
                'name' => [
                    'az' => 'Təhsil krediti',
                    'en' => 'Education loan',
                    'ru' => 'Образовательный кредит'
                ],
                'description' => [
                    'az' => 'Təhsil almaq üçün kredit',
                    'en' => 'Loan for education purposes',
                    'ru' => 'Кредит на образование'
                ],
                'order' => 5
            ],
            [
                'slug' => 'cards',
                'name' => [
                    'az' => 'Kredit kartları',
                    'en' => 'Credit Cards',
                    'ru' => 'Кредитные карты'
                ],
                'description' => [
                    'az' => 'Bank kredit kartları',
                    'en' => 'Bank credit cards',
                    'ru' => 'Банковские кредитные карты'
                ],
                'order' => 6
            ]
        ];

        foreach ($creditTypes as $type) {
            CreditType::create($type);
        }

        $this->command->info('Credit types seeded successfully!');
    }
}