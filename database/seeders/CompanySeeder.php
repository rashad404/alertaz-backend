<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyType;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bankType = CompanyType::where('slug', 'banks')->first();
        $creditType = CompanyType::where('slug', 'credit-organizations')->first();
        $insuranceType = CompanyType::where('slug', 'insurance')->first();

        $companies = [
            // Banks
            [
                'name' => json_encode(['az' => 'Kapital Bank', 'en' => 'Kapital Bank', 'ru' => 'Капитал Банк']),
                'short_name' => 'Kapital',
                'slug' => 'kapital-bank',
                'about' => 'Kapital Bank Azərbaycanın aparıcı banklarından biridir. Bank 1874-cü ildən fəaliyyət göstərir və müasir bank xidmətlərinin geniş spektrini təklif edir.',
                'company_type_id' => $bankType->id,
                'site' => 'https://kapitalbank.az',
                'phones' => json_encode(['+994 12 196', '196']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Fizuli küçəsi 71', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1300007691',
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
                'establishment_date' => '1874-01-01',
                'order' => 1,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'PAŞA Bank', 'en' => 'PASHA Bank', 'ru' => 'ПАША Банк']),
                'short_name' => 'PAŞA',
                'slug' => 'pasha-bank',
                'about' => 'PAŞA Bank korporativ və investisiya bankçılığı sahəsində ixtisaslaşmış Azərbaycanın aparıcı bankıdır.',
                'company_type_id' => $bankType->id,
                'site' => 'https://pashabank.az',
                'phones' => json_encode(['+994 12 496 50 00', '1540']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, M.Müşfiq küçəsi 15', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1401555071',
                    'license' => 'Mərkəzi Bank lisenziya №250',
                    'swift' => 'PASIAZ22'
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
                'establishment_date' => '2007-01-01',
                'order' => 2,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'ABB - Azərbaycan Beynəlxalq Bankı', 'en' => 'ABB - Azerbaijan International Bank', 'ru' => 'МБА - Международный Банк Азербайджана']),
                'short_name' => 'ABB',
                'slug' => 'abb',
                'about' => 'ABB Azərbaycanın ən böyük universal bankıdır. Bank 1992-ci ildə yaradılıb və müasir maliyyə xidmətlərinin tam spektrini təklif edir.',
                'company_type_id' => $bankType->id,
                'site' => 'https://abb-bank.az',
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
                'email' => 'info@abb.az',
                'establishment_date' => '1992-01-01',
                'order' => 3,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'Unibank', 'en' => 'Unibank', 'ru' => 'Юнибанк']),
                'short_name' => 'Unibank',
                'slug' => 'unibank',
                'about' => 'Unibank Azərbaycanın aparıcı universal banklarından biridir.',
                'company_type_id' => $bankType->id,
                'site' => 'https://unibank.az',
                'phones' => json_encode(['+994 12 505 55 55', '117']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, R.Behbudov küçəsi 55', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '9900003611',
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
                'establishment_date' => '1992-01-01',
                'order' => 4,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'Bank Respublika', 'en' => 'Bank Respublika', 'ru' => 'Банк Республика']),
                'short_name' => 'Bank Respublika',
                'slug' => 'bank-respublika',
                'about' => 'Bank Respublika müasir bank xidmətləri təklif edən dinamik bankdır.',
                'company_type_id' => $bankType->id,
                'site' => 'https://bankrespublika.az',
                'phones' => json_encode(['+994 12 598 00 91', '919']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Xətai rayonu, 8 Noyabr prospekti 15', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '9900002001',
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
                'email' => 'info@bankrespublika.az',
                'establishment_date' => '1991-01-01',
                'order' => 5,
                'status' => true,
            ],

            // Credit Organizations
            [
                'name' => json_encode(['az' => 'TBC Kredit', 'en' => 'TBC Credit', 'ru' => 'TBC Кредит']),
                'short_name' => 'TBC',
                'slug' => 'tbc-kredit',
                'about' => 'TBC Kredit Azərbaycanda fəaliyyət göstərən qabaqcıl bank olmayan kredit təşkilatıdır.',
                'company_type_id' => $creditType->id,
                'site' => 'https://tbckredit.az',
                'phones' => json_encode(['+994 12 377 00 00', '*0202']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Nəsimi rayonu, Zərifə Əliyeva küçəsi 85', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1405634311',
                    'license' => 'Mərkəzi Bank BOKT lisenziyası'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-16:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@tbckredit.az',
                'establishment_date' => '2016-01-01',
                'order' => 6,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'FinEx', 'en' => 'FinEx', 'ru' => 'ФинЭкс']),
                'short_name' => 'FinEx',
                'slug' => 'finex',
                'about' => 'FinEx müasir kredit təşkilatıdır. Biz müştərilərimizə sürətli və rahat kredit həlləri təklif edirik.',
                'company_type_id' => $creditType->id,
                'site' => 'https://finex.az',
                'phones' => json_encode(['+994 12 310 09 09', '143']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Yasamal rayonu, İnşaatçılar prospekti 33', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1502426271',
                    'license' => 'Mərkəzi Bank BOKT lisenziyası'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-15:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@finex.az',
                'establishment_date' => '2018-01-01',
                'order' => 7,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'MCB Credit', 'en' => 'MCB Credit', 'ru' => 'MCB Кредит']),
                'short_name' => 'MCB',
                'slug' => 'mcb-credit',
                'about' => 'MCB Credit - müştəri məmnuniyyətini önə çıxaran kredit təşkilatıdır.',
                'company_type_id' => $creditType->id,
                'site' => 'https://mcbcredit.az',
                'phones' => json_encode(['+994 12 444 01 01', '*7744']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Səbail rayonu, Neftçilər pr. 153', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1602356781',
                    'license' => 'Mərkəzi Bank BOKT lisenziyası'
                ]),
                'business_hours' => json_encode([
                    'monday' => '09:00-18:00',
                    'tuesday' => '09:00-18:00',
                    'wednesday' => '09:00-18:00',
                    'thursday' => '09:00-18:00',
                    'friday' => '09:00-18:00',
                    'saturday' => '10:00-16:00',
                    'sunday' => 'Bağlı'
                ]),
                'email' => 'info@mcbcredit.az',
                'establishment_date' => '2019-01-01',
                'order' => 8,
                'status' => true,
            ],

            // Insurance Companies
            [
                'name' => json_encode(['az' => 'Atəşgah Sığorta', 'en' => 'Ateshgah Insurance', 'ru' => 'Атешгах Страхование']),
                'short_name' => 'Atəşgah',
                'slug' => 'ateshgah-sigorta',
                'about' => 'Atəşgah Sığorta Azərbaycanın aparıcı sığorta şirkətlərindən biridir.',
                'company_type_id' => $insuranceType->id,
                'site' => 'https://ateshgah.az',
                'phones' => json_encode(['+994 12 404 44 44', '1559']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Nizami rayonu, Qara Qarayev pr. 68', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1300330571',
                    'license' => 'Maliyyə Nazirliyi lisenziya №571'
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
                'email' => 'info@ateshgah.az',
                'establishment_date' => '2001-01-01',
                'order' => 1,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'Paşa Sığorta', 'en' => 'Pasha Insurance', 'ru' => 'Паша Страхование']),
                'short_name' => 'Paşa Sığorta',
                'slug' => 'pasha-sigorta',
                'about' => 'Paşa Sığorta Azərbaycanın ən böyük və qabaqcıl sığorta şirkətidir.',
                'company_type_id' => $insuranceType->id,
                'site' => 'https://pasha-insurance.az',
                'phones' => json_encode(['+994 12 598 18 98', '1540']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, M.Müşfiq küçəsi 15', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1401018961',
                    'license' => 'Maliyyə Nazirliyi lisenziya №382'
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
                'email' => 'info@pasha-insurance.az',
                'establishment_date' => '2006-01-01',
                'order' => 2,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'AXA MBask', 'en' => 'AXA MBask', 'ru' => 'AXA MBask']),
                'short_name' => 'AXA',
                'slug' => 'axa-mbask',
                'about' => 'AXA MBask beynəlxalq təcrübəyə malik sığorta şirkətidir.',
                'company_type_id' => $insuranceType->id,
                'site' => 'https://axa-mbask.az',
                'phones' => json_encode(['+994 12 377 27 27', '2727']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Xətai pr. 48', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1300176981',
                    'license' => 'Maliyyə Nazirliyi lisenziya №269'
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
                'email' => 'info@axa-mbask.az',
                'establishment_date' => '1995-01-01',
                'order' => 3,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'Qala Sığorta', 'en' => 'Qala Insurance', 'ru' => 'Гала Страхование']),
                'short_name' => 'Qala',
                'slug' => 'qala-sigorta',
                'about' => 'Qala Sığorta müasir sığorta xidmətləri təklif edən dinamik şirkətdir.',
                'company_type_id' => $insuranceType->id,
                'site' => 'https://qala.az',
                'phones' => json_encode(['+994 12 936', '936']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Yasamal rayonu, M.Əsədov küçəsi 5', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1501046561',
                    'license' => 'Maliyyə Nazirliyi lisenziya №658'
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
                'email' => 'info@qala.az',
                'establishment_date' => '2010-01-01',
                'order' => 4,
                'status' => true,
            ],
            [
                'name' => json_encode(['az' => 'Xalq Sığorta', 'en' => 'Xalq Insurance', 'ru' => 'Халг Страхование']),
                'short_name' => 'Xalq',
                'slug' => 'xalq-sigorta',
                'about' => 'Xalq Sığorta Azərbaycanın ilk milli sığorta şirkətlərindən biridir.',
                'company_type_id' => $insuranceType->id,
                'site' => 'https://xalqsigorta.az',
                'phones' => json_encode(['+994 12 497 77 01', '909']),
                'addresses' => json_encode([
                    ['address' => 'Bakı şəhəri, Bül-bül pr. 26', 'type' => 'head_office']
                ]),
                'requisites' => json_encode([
                    'voen' => '1500046311',
                    'license' => 'Maliyyə Nazirliyi lisenziya №481'
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
                'email' => 'info@xalqsigorta.az',
                'establishment_date' => '1993-01-01',
                'order' => 5,
                'status' => true,
            ],
        ];

        foreach ($companies as $company) {
            // Check if company exists
            $existingCompany = Company::where('slug', $company['slug'])->first();
            
            if ($existingCompany) {
                // If name is already JSON encoded (double encoded), decode it first
                $currentName = $existingCompany->name;
                if (is_string($currentName) && str_starts_with($currentName, '{"') && !str_starts_with($currentName, '{"az"')) {
                    // This means it's double encoded, skip update for now
                    $existingCompany->update(array_merge($company, [
                        'name' => $company['name'] // Force overwrite with correct JSON
                    ]));
                } else {
                    $existingCompany->update($company);
                }
            } else {
                Company::create($company);
            }
        }
    }
}