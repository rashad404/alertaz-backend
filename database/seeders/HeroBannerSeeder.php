<?php

namespace Database\Seeders;

use App\Models\HeroBanner;
use Illuminate\Database\Seeder;

class HeroBannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banners = [
            [
                'title' => [
                    'az' => "Peşəkar veb sayt\nSayt.az ilə",
                    'en' => "Professional website\nwith Sayt.az",
                    'ru' => "Профессиональный сайт\nс Sayt.az"
                ],
                'description' => [
                    'az' => 'Müasir dizayn, sürətli performans və SEO optimallaşdırılmış veb saytlar hazırlayırıq',
                    'en' => 'We create modern design, fast performance and SEO optimized websites',
                    'ru' => 'Создаем сайты с современным дизайном, быстрой производительностью и SEO оптимизацией'
                ],
                'image' => '/sayt-az-banner.svg',
                'link' => 'https://sayt.az',
                'link_text' => [
                    'az' => 'Sifariş et',
                    'en' => 'Order now',
                    'ru' => 'Заказать'
                ],
                'order' => 1,
                'is_active' => false,
            ],
            [
                'title' => [
                    'az' => "0% komissiya\nPulsuz kredit kartı",
                    'en' => "0% commission\nFree credit card",
                    'ru' => "0% комиссия\nБесплатная кредитная карта"
                ],
                'description' => [
                    'az' => 'İlk 3 ay faizsiz, 50 günədək güzəşt müddəti ilə kredit kartı əldə edin',
                    'en' => 'Get a credit card with 0% interest for the first 3 months and up to 50 days grace period',
                    'ru' => 'Получите кредитную карту с 0% на первые 3 месяца и льготным периодом до 50 дней'
                ],
                'image' => '/credit-card-banner.svg',
                'link' => '/az/credits/cards',
                'link_text' => [
                    'az' => 'Müraciət et',
                    'en' => 'Apply now',
                    'ru' => 'Подать заявку'
                ],
                'order' => 2,
                'is_active' => false,
            ],
            [
                'title' => [
                    'az' => "15 dəqiqəyə kredit\nOnlayn müraciət",
                    'en' => "Credit in 15 minutes\nOnline application",
                    'ru' => "Кредит за 15 минут\nОнлайн заявка"
                ],
                'description' => [
                    'az' => 'Evdən çıxmadan onlayn kredit müraciəti göndərin və sürətli cavab alın',
                    'en' => 'Apply for a loan online without leaving home and get a quick response',
                    'ru' => 'Подайте заявку на кредит онлайн не выходя из дома и получите быстрый ответ'
                ],
                'image' => '/quick-credit-banner.svg',
                'link' => '/az/credits',
                'link_text' => [
                    'az' => 'Kreditləri gör',
                    'en' => 'View credits',
                    'ru' => 'Посмотреть кредиты'
                ],
                'order' => 3,
                'is_active' => false,
            ],
        ];

        foreach ($banners as $banner) {
            HeroBanner::create($banner);
        }
    }
}