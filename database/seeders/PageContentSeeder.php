<?php

namespace Database\Seeders;

use App\Models\AboutPageData;
use App\Models\FaqPageContent;
use App\Models\SubscriptionSection;
use App\Models\AppDownloadSection;
use Illuminate\Database\Seeder;

class PageContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // About Page Data
        AboutPageData::create([
            'title' => json_encode(['az' => 'Kredit.az haqqında', 'en' => 'About Kredit.az', 'ru' => 'О Kredit.az']),
            'description' => json_encode(['az' => 'Kredit.az Azərbaycanda kredit və maliyyə məhsulları haqqında ən dolğun məlumat bazasıdır.']),
            'image' => 'about-us.jpg',
            'image_alt_text' => json_encode(['az' => 'Kredit.az haqqında', 'en' => 'About Kredit.az', 'ru' => 'О Kredit.az']),
            'mission_section_title' => json_encode(['az' => 'Bizim missiyamız', 'en' => 'Our mission', 'ru' => 'Наша миссия']),
            'video_image' => 'mission-video.jpg',
            'video_link' => 'https://www.youtube.com/watch?v=example',
            'our_mission_title' => json_encode(['az' => 'Missiyamız', 'en' => 'Our Mission', 'ru' => 'Наша миссия']),
            'our_mission_text' => json_encode(['az' => 'İnsanlara ən uyğun kredit məhsullarını tapmaqda kömək etmək.']),
        ]);

        // FAQ Page Content
        FaqPageContent::create([
            'title' => json_encode(['az' => 'Tez-tez verilən suallar', 'en' => 'FAQ', 'ru' => 'FAQ']),
            'description' => json_encode(['az' => 'Kredit və maliyyə məhsulları haqqında suallar']),
            'image' => 'faq-banner.jpg',
            'image_alt_text' => json_encode(['az' => 'FAQ', 'en' => 'FAQ', 'ru' => 'FAQ']),
        ]);

        // Subscription Section
        SubscriptionSection::create([
            'image' => 'subscription-bg.jpg',
            'image_alt_text' => json_encode(['az' => 'Abunə ol', 'en' => 'Subscribe', 'ru' => 'Подписаться']),
            'title' => json_encode(['az' => 'Xəbərlərdən xəbərdar olun', 'en' => 'Stay updated', 'ru' => 'Будьте в курсе']),
            'description' => json_encode(['az' => 'E-poçt ünvanınızı qeyd edərək abunə olun']),
        ]);

        // App Download Section
        AppDownloadSection::create([
            'title' => json_encode([
                'az' => 'Maliyyə yeniliklərdən xəbərdar olmaq üçün Appı yüklə',
                'en' => 'Download the app to stay updated on financial news',
                'ru' => 'Скачайте приложение, чтобы быть в курсе финансовых новостей'
            ]),
            'description' => json_encode([
                'az' => 'Kredit.az mobil tətbiqi ilə istənilən vaxt və yerdən maliyyə xidmətlərinə çıxış əldə edin. Kreditləri müqayisə edin, ərizə verin və maliyyə vəziyyətinizi idarə edin.',
                'en' => 'Access financial services anytime, anywhere with the Kredit.az mobile app. Compare loans, apply, and manage your finances on the go.',
                'ru' => 'Получите доступ к финансовым услугам в любое время и в любом месте с мобильным приложением Kredit.az. Сравнивайте кредиты, подавайте заявки и управляйте своими финансами.'
            ]),
            'image' => '/mobile-app-mockup.svg',
            'image_alt_text' => json_encode(['az' => 'Kredit.az mobil tətbiq', 'en' => 'Kredit.az mobile app', 'ru' => 'Мобильное приложение Kredit.az']),
            'app_store_url' => 'https://apps.apple.com/az/app/kredit-az',
            'play_store_url' => 'https://play.google.com/store/apps/details?id=az.kredit.app',
        ]);
    }
}