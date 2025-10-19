<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FaqPageContent;

class FaqPageContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FaqPageContent::updateOrCreate(
            ['id' => 1],
            [
                'title' => [
                    'az' => 'Tez-tez verilən suallar',
                    'en' => 'Frequently Asked Questions',
                    'ru' => 'Часто задаваемые вопросы'
                ],
                'description' => [
                    'az' => 'Kredit.az xidməti ilə bağlı ən çox verilən sualların cavablarını burada tapa bilərsiniz. Əlavə sualınız varsa, bizimlə əlaqə saxlayın.',
                    'en' => 'You can find answers to the most frequently asked questions about Kredit.az service here. If you have additional questions, please contact us.',
                    'ru' => 'Здесь вы можете найти ответы на наиболее часто задаваемые вопросы о сервисе Kredit.az. Если у вас есть дополнительные вопросы, пожалуйста, свяжитесь с нами.'
                ],
                'image' => null, // Can be updated with actual image path
                'image_alt_text' => [
                    'az' => 'Tez-tez verilən suallar',
                    'en' => 'Frequently Asked Questions',
                    'ru' => 'Часто задаваемые вопросы'
                ]
            ]
        );

        $this->command->info('FAQ page content seeded successfully!');
    }
}