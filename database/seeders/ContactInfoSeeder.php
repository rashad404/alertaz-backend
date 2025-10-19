<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContactInfo;

class ContactInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ContactInfo::updateOrCreate(
            ['id' => 1],
            [
                'company_name' => 'KREDIT.AZ',
                'legal_name' => '"RMEDİA" MMC',
                'voen' => '2601675631',
                'chief_editor' => [
                    'az' => 'Baş redaktor: Ramil Alim oğlu Mirzəyev',
                    'en' => 'Chief Editor: Ramil Alim oglu Mirzayev',
                    'ru' => 'Главный редактор: Рамиль Алим оглы Мирзаев'
                ],
                'domain_owner' => 'İdrak Mustafazadə',
                'address' => [
                    'az' => 'Bakı şəhəri, Nərimanov rayonu, Xan Şuşinski küçəsi 49. Green Plaza. 5-ci mərtəbə, 502.',
                    'en' => 'Baku city, Narimanov district, Khan Shushinski street 49. Green Plaza. 5th floor, 502.',
                    'ru' => 'город Баку, Наримановский район, улица Хан Шушинский 49. Green Plaza. 5-й этаж, 502.'
                ],
                'phone' => '0502365333',
                'phone_2' => null,
                'email' => 'info@kredit.az',
                'email_2' => null,
                'working_hours' => [
                    'az' => 'Bazar ertəsi - Cümə: 09:00 - 18:00',
                    'en' => 'Monday - Friday: 09:00 - 18:00',
                    'ru' => 'Понедельник - Пятница: 09:00 - 18:00'
                ],
                'facebook_url' => 'https://www.facebook.com/kredit.az',
                'instagram_url' => 'https://www.instagram.com/kredit.az',
                'linkedin_url' => 'https://www.linkedin.com/company/kredit-az',
                'twitter_url' => 'https://twitter.com/kreditaz',
                'youtube_url' => null,
                'latitude' => 40.4093,
                'longitude' => 49.8671,
                // Google Maps embed URL for Green Plaza location
                'map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3039.2693291290647!2d49.864919315744!3d40.40930397936544!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40307d6bd6211cf9%3A0x343f6b5e7ae56c6b!2sBaku%2C%20Azerbaijan!5e0!3m2!1sen!2s!4v1625000000000!5m2!1sen!2s'
            ]
        );

        $this->command->info('Contact info seeded successfully!');
    }
}