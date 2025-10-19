<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AboutPageData;

class AboutPageDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update or create the about page data with properly formatted JSON
        $aboutData = AboutPageData::updateOrCreate(
            ['id' => 1],
            [
                'title' => [
                    'az' => 'Kredit.az haqqında',
                    'en' => 'About Kredit.az',
                    'ru' => 'О Kredit.az'
                ],
                'description' => [
                    'az' => "Kredit.az – Azərbaycanda maliyyə və iqtisadiyyata dair ən etibarlı mənbələrdən biridir. Platforma iqtisadi xəbərləri, müsahibələri, analitik məqalələri və aktual renkinqləri oxuculara peşəkar şəkildə təqdim edir.\n\nSaytda xəbərlərlə yanaşı, həm də geniş məlumat bazası mövcuddur: valyuta məzənnələri, banklar, kredit təşkilatları və digər şirkətlər haqqında ətraflı məlumatlara bir platformada çıxış əldə edə bilərsiniz.\n\nBundan əlavə, Kredit.az istifadəçilərə kredit, depozit, endirim kartları və digər maliyyə məhsullarını müqayisə etmək imkanı yaradır.\n\nMəqsədimiz maliyyə bazarında şəffaflığı artırmaq, insanlar və şirkətlər üçün düzgün seçim etməkdə köməkçi olmaqdır.\n\nKredit.az eyni zamanda \"Facebook\", \"X\", \"Instagram\", \"LinkedIn\" kimi sosial media platformalarında təmsil olunur.\n\nTəsisçi və baş redaktor: Ramil Mirzəyev",
                    'en' => "Kredit.az is one of the most reliable sources for finance and economics in Azerbaijan. The platform professionally presents economic news, interviews, analytical articles and current rankings to readers.\n\nAlongside news, the site also has an extensive database: you can access detailed information about exchange rates, banks, credit organizations and other companies on one platform.\n\nAdditionally, Kredit.az enables users to compare loans, deposits, discount cards and other financial products.\n\nOur goal is to increase transparency in the financial market and help individuals and companies make the right choices.\n\nKredit.az is also represented on social media platforms such as \"Facebook\", \"X\", \"Instagram\", and \"LinkedIn\".\n\nFounder and Chief Editor: Ramil Mirzayev",
                    'ru' => "Kredit.az – один из самых надежных источников по финансам и экономике в Азербайджане. Платформа профессионально представляет читателям экономические новости, интервью, аналитические статьи и актуальные рейтинги.\n\nНаряду с новостями, на сайте также имеется обширная база данных: вы можете получить доступ к подробной информации о валютных курсах, банках, кредитных организациях и других компаниях на одной платформе.\n\nКроме того, Kredit.az позволяет пользователям сравнивать кредиты, депозиты, дисконтные карты и другие финансовые продукты.\n\nНаша цель – повысить прозрачность финансового рынка и помочь людям и компаниям сделать правильный выбор.\n\nKredit.az также представлен в таких социальных сетях, как \"Facebook\", \"X\", \"Instagram\" и \"LinkedIn\".\n\nОснователь и главный редактор: Рамиль Мирзаев"
                ],
                'image_alt_text' => [
                    'az' => 'Kredit.az komandası',
                    'en' => 'Kredit.az team',
                    'ru' => 'Команда Kredit.az'
                ],
                'mission_section_title' => [
                    'az' => 'Bizim missiyamız',
                    'en' => 'Our mission',
                    'ru' => 'Наша миссия'
                ],
                'our_mission_title' => [
                    'az' => 'Məqsədimiz',
                    'en' => 'Our goal',
                    'ru' => 'Наша цель'
                ],
                'our_mission_text' => [
                    'az' => 'Maliyyə bazarında şəffaflığı artırmaq, insanlar və şirkətlər üçün düzgün seçim etməkdə köməkçi olmaqdır. Biz inanırıq ki, doğru məlumat və müqayisə imkanları ilə hər kəs öz maliyyə gələcəyini daha yaxşı idarə edə bilər.',
                    'en' => 'To increase transparency in the financial market and help individuals and companies make the right choices. We believe that with the right information and comparison opportunities, everyone can better manage their financial future.',
                    'ru' => 'Повысить прозрачность финансового рынка и помочь людям и компаниям сделать правильный выбор. Мы верим, что с правильной информацией и возможностями сравнения каждый может лучше управлять своим финансовым будущим.'
                ],
                'carer_section_title' => [
                    'az' => 'Komandamıza qoşulun',
                    'en' => 'Join our team',
                    'ru' => 'Присоединяйтесь к нашей команде'
                ],
                'carer_section_desc' => [
                    'az' => 'Kredit.az-da biz yenilikçi, dinamik və peşəkar komanda axtarırıq. Əgər siz maliyyə texnologiyaları sahəsində karyera qurmaq və Azərbaycanın maliyyə bazarının inkişafına töhfə vermək istəyirsinizsə, bizimlə əlaqə saxlayın.',
                    'en' => 'At Kredit.az, we are looking for innovative, dynamic and professional team members. If you want to build a career in financial technology and contribute to the development of Azerbaijan\'s financial market, contact us.',
                    'ru' => 'В Kredit.az мы ищем инновационных, динамичных и профессиональных членов команды. Если вы хотите построить карьеру в области финансовых технологий и внести вклад в развитие финансового рынка Азербайджана, свяжитесь с нами.'
                ],
                'carer_section_image_alt_text' => [
                    'az' => 'Karyera imkanları',
                    'en' => 'Career opportunities',
                    'ru' => 'Карьерные возможности'
                ],
                // Non-translatable fields
                'image' => 'about/team.jpg',
                'video_image' => 'about/video-thumbnail.jpg',
                'video_link' => 'https://www.youtube.com/watch?v=example',
                'carer_section_image' => 'about/career.jpg',
            ]
        );

        $this->command->info('About page data seeded successfully!');
    }
}