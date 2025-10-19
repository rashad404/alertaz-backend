<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\CompanyType;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clean up any duplicates first
        $duplicateSlugs = ['pasa-bank']; // Known duplicate slugs
        Company::whereIn('slug', $duplicateSlugs)->delete();
        
        // Use existing bank type from CompanyTypeSeeder (slug: 'banks')
        $bankType = CompanyType::where('slug', 'banks')->first();
        
        if (!$bankType) {
            // Fallback: create if not exists
            $bankType = CompanyType::create([
                'title' => json_encode(['az' => 'Bank', 'en' => 'Banks', 'ru' => 'Банки']),
                'slug' => 'banks',
                'icon' => 'fa-university',
                'icon_alt_text' => json_encode(['az' => 'Bank ikonu', 'en' => 'Bank icon', 'ru' => 'Иконка банка']),
                'order' => 1,
                'status' => true
            ]);
        }

        $banks = [
            [
                'name' => json_encode(['az' => 'Azərbaycan Beynəlxalq Bankı', 'en' => 'International Bank of Azerbaijan', 'ru' => 'Международный Банк Азербайджана']),
                'short_name' => 'ABB',
                'slug' => 'abb',
                'logo' => 'banks/abb.svg',
                'about' => 'Azərbaycan Beynəlxalq Bankı ölkənin ən böyük və ən köhnə bankıdır. 1992-ci ildə təsis edilmişdir.',
                'company_type_id' => $bankType->id,
                'site' => 'https://www.ibar.az',
                'phones' => json_encode(['+994 12 493 00 91', '937']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Nizami küçəsi 67', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '9900001881',
                    'license' => 'Mərkəzi Bank lisenziya №245',
                    'swift' => 'IBAZAZ2X'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '09:00-15:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@ibar.az',
                'establishment_date' => '1992-01-05',
                'order' => 1,
                'status' => true
            ],
            [
                'name' => json_encode(['az' => 'Kapital Bank', 'en' => 'Kapital Bank', 'ru' => 'Капитал Банк']),
                'short_name' => 'Kapital',
                'slug' => 'kapital-bank',
                'logo' => 'banks/kapital.svg',
                'about' => 'Kapital Bank Azərbaycanın aparıcı universal bankıdır. Birbank mobil tətbiqi ilə tanınır.',
                'company_type_id' => $bankType->id,
                'site' => 'https://www.kapitalbank.az',
                'phones' => json_encode(['+994 12 310 99 99', '196']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Fizuli küçəsi 71', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1400037171',
                    'license' => 'Mərkəzi Bank lisenziya №244',
                    'swift' => 'AIIBAZ2X'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '09:00-15:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@kapitalbank.az',
                'establishment_date' => '2000-01-12',
                'order' => 2,
                'status' => true
            ],
            [
                'name' => json_encode(['az' => 'PAŞA Bank', 'en' => 'PASHA Bank', 'ru' => 'ПАША Банк']),
                'short_name' => 'PAŞA',
                'slug' => 'pasha-bank',
                'logo' => 'banks/pasha.svg',
                'about' => 'PAŞA Bank premium və korporativ bankinq xidmətləri göstərən aparıcı bankdır.',
                'company_type_id' => $bankType->id,
                'site' => 'https://www.pashabank.az',
                'phones' => json_encode(['+994 12 496 50 00', '9650']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Bül-bül prospekti 15', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1401555071',
                    'license' => 'Mərkəzi Bank lisenziya №250',
                    'swift' => 'PAHAAZ22'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => 'Bağlı',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@pashabank.az',
                'establishment_date' => '2007-06-15',
                'order' => 3,
                'status' => true
            ],
            [
                'name' => json_encode(['az' => 'Unibank', 'en' => 'Unibank', 'ru' => 'Юнибанк']),
                'short_name' => 'Unibank',
                'slug' => 'unibank',
                'logo' => 'banks/unibank.svg',
                'about' => 'Unibank Azərbaycanın ən böyük özəl banklarından biridir. Geniş filial şəbəkəsinə malikdir.',
                'company_type_id' => $bankType->id,
                'site' => 'https://www.unibank.az',
                'phones' => json_encode(['+994 12 618 88 88', '117']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Rəşid Behbudov küçəsi 55', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1300379611',
                    'license' => 'Mərkəzi Bank lisenziya №248',
                    'swift' => 'UBAZAZ22'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '09:00-15:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@unibank.az',
                'establishment_date' => '2002-02-15',
                'order' => 4,
                'status' => true
            ],
            [
                'name' => json_encode(['az' => 'AccessBank', 'en' => 'AccessBank', 'ru' => 'AccessBank']),
                'short_name' => 'Access',
                'slug' => 'accessbank',
                'logo' => 'banks/access.svg',
                'about' => 'AccessBank mikro və kiçik biznesə kredit verən ixtisaslaşmış bankdır.',
                'company_type_id' => $bankType->id,
                'site' => 'https://www.accessbank.az',
                'phones' => json_encode(['+994 12 565 85 85', '151']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Tbilisi prospekti 34', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1401907071',
                    'license' => 'Mərkəzi Bank lisenziya №241',
                    'swift' => 'AXABAZ22'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '09:00-15:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'office@accessbank.az',
                'establishment_date' => '2002-10-29',
                'order' => 5,
                'status' => true
            ],
            [
                'name' => json_encode(['az' => 'Bank Respublika', 'en' => 'Bank Respublika', 'ru' => 'Банк Республика']),
                'short_name' => 'Respublika',
                'slug' => 'bank-respublika',
                'logo' => 'banks/respublika.svg',
                'about' => 'Bank Respublika Azərbaycanın köhnə banklarından biridir və universal bank xidmətləri göstərir.',
                'company_type_id' => $bankType->id,
                'site' => 'https://www.bankrespublika.az',
                'phones' => json_encode(['+994 12 598 12 67', '147']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Xətai prospekti 41', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '9900002611',
                    'license' => 'Mərkəzi Bank lisenziya №246',
                    'swift' => 'BREPAZ22'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '09:00-15:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'office@bankrespublika.az',
                'establishment_date' => '1992-05-22',
                'order' => 6,
                'status' => true
            ],
        ];

        foreach ($banks as $bankData) {
            Company::updateOrCreate(
                ['slug' => $bankData['slug']],
                $bankData
            );
        }

        $this->command->info('Banks seeded successfully!');
    }
}