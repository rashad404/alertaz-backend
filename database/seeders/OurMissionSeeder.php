<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OurMission;

class OurMissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data in development/staging environments
        if (app()->environment(['local', 'staging'])) {
            OurMission::truncate();
        }

        $missions = [
            [
                'title' => [
                    'az' => 'Müştəri Məmnuniyyəti',
                    'en' => 'Customer Satisfaction',
                    'ru' => 'Удовлетворенность клиентов'
                ],
                'description' => [
                    'az' => 'Müştərilərimizin maliyyə ehtiyaclarını ən yüksək səviyyədə qarşılayaraq, onların güvənini qazanmaq və uzunmüddətli əməkdaşlıq qurmaq.',
                    'en' => 'Meeting our customers\' financial needs at the highest level, earning their trust and building long-term partnerships.',
                    'ru' => 'Удовлетворение финансовых потребностей наших клиентов на самом высоком уровне, завоевание их доверия и построение долгосрочного партнерства.'
                ],
                'order' => 1,
                'status' => true,
            ],
            [
                'title' => [
                    'az' => 'İnnovasiya və Texnologiya',
                    'en' => 'Innovation and Technology',
                    'ru' => 'Инновации и технологии'
                ],
                'description' => [
                    'az' => 'Ən müasir texnologiyaları tətbiq edərək, kredit və maliyyə xidmətlərini daha sürətli, təhlükəsiz və əlçatan etmək.',
                    'en' => 'Implementing cutting-edge technologies to make credit and financial services faster, safer, and more accessible.',
                    'ru' => 'Внедрение передовых технологий для ускорения, повышения безопасности и доступности кредитных и финансовых услуг.'
                ],
                'order' => 2,
                'status' => true,
            ],
            [
                'title' => [
                    'az' => 'Şəffaflıq və Etibarlılıq',
                    'en' => 'Transparency and Reliability',
                    'ru' => 'Прозрачность и надежность'
                ],
                'description' => [
                    'az' => 'Bütün maliyyə əməliyyatlarında tam şəffaflıq təmin edərək, müştərilərimizə etibarlı və dürüst xidmət göstərmək.',
                    'en' => 'Providing reliable and honest service to our customers by ensuring complete transparency in all financial transactions.',
                    'ru' => 'Предоставление надежных и честных услуг нашим клиентам путем обеспечения полной прозрачности во всех финансовых операциях.'
                ],
                'order' => 3,
                'status' => true,
            ],
            [
                'title' => [
                    'az' => 'Sosial Məsuliyyət',
                    'en' => 'Social Responsibility',
                    'ru' => 'Социальная ответственность'
                ],
                'description' => [
                    'az' => 'Cəmiyyətin inkişafına töhfə verərək, maliyyə savadlılığını artırmaq və iqtisadi rifaha nail olmağa kömək etmək.',
                    'en' => 'Contributing to society\'s development by increasing financial literacy and helping achieve economic prosperity.',
                    'ru' => 'Вклад в развитие общества путем повышения финансовой грамотности и содействия достижению экономического процветания.'
                ],
                'order' => 4,
                'status' => true,
            ],
        ];

        foreach ($missions as $mission) {
            OurMission::create($mission);
        }

        $this->command->info('Our missions seeded successfully!');
    }
}