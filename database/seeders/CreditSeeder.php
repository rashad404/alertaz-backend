<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Credit;
use App\Models\CreditType;

class CreditSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing credits in development
        if (app()->environment(['local', 'staging'])) {
            Credit::truncate();
        }

        // Get credit types
        $cashType = CreditType::where('slug', 'cash')->first();
        $autoType = CreditType::where('slug', 'auto')->first();
        $mortgageType = CreditType::where('slug', 'mortgage')->first();
        $businessType = CreditType::where('slug', 'business')->first();
        $educationType = CreditType::where('slug', 'education')->first();

        $credits = [
            [
                'credit_type_id' => $cashType->id ?? 1,
                'bank_name' => 'Kapital Bank',
                'credit_name' => [
                    'az' => 'Nağd pul krediti',
                    'en' => 'Cash loan',
                    'ru' => 'Наличный кредит'
                ],
                'about' => [
                    'az' => 'Güzəştli şərtlərlə nağd pul krediti. Sürətli rəsmiləşdirmə, minimum sənəd paketi.',
                    'en' => 'Cash loan with favorable terms. Fast processing, minimum document package.',
                    'ru' => 'Наличный кредит на выгодных условиях. Быстрое оформление, минимальный пакет документов.'
                ],
                'credit_amount' => 30000,
                'min_amount' => 1000,
                'max_amount' => 30000,
                'credit_term' => 36,
                'min_term_months' => 6,
                'max_term_months' => 36,
                'interest_rate' => 11.99,
                'commission_rate' => 1,
                'guarantor' => [
                    'az' => 'Tələb olunmur',
                    'en' => 'Not required',
                    'ru' => 'Не требуется'
                ],
                'collateral' => [
                    'az' => 'Tələb olunmur',
                    'en' => 'Not required',
                    'ru' => 'Не требуется'
                ],
                'method_of_purchase' => [
                    'az' => 'Filial, online müraciət',
                    'en' => 'Branch, online application',
                    'ru' => 'Филиал, онлайн заявка'
                ],
                'bank_phone' => '+994 12 310 0310',
                'bank_address' => 'Baku, Sabail district, Fuzuli str. 71',
                'views' => 1523,
                'seo_title' => [
                    'az' => 'Kapital Bank Nağd Pul Krediti - 11.99% faiz dərəcəsi',
                    'en' => 'Kapital Bank Cash Loan - 11.99% interest rate',
                    'ru' => 'Kapital Bank Наличный кредит - 11.99% процентная ставка'
                ],
                'seo_keywords' => [
                    'az' => 'nağd kredit, kapital bank, kredit faizi, pul krediti',
                    'en' => 'cash loan, kapital bank, loan interest, money loan',
                    'ru' => 'наличный кредит, kapital bank, процент по кредиту, денежный кредит'
                ],
                'seo_description' => [
                    'az' => 'Kapital Bank-dan 30,000 AZN-dək nağd pul krediti, 11.99% illik faiz dərəcəsi ilə. Zamin və girov tələb olunmur.',
                    'en' => 'Cash loan up to 30,000 AZN from Kapital Bank with 11.99% annual interest rate. No guarantor or collateral required.',
                    'ru' => 'Наличный кредит до 30 000 AZN от Kapital Bank под 11,99% годовых. Поручитель и залог не требуются.'
                ],
                'status' => true,
                'order' => 1
            ],
            [
                'credit_type_id' => $autoType->id ?? 3,
                'bank_name' => 'Paşa Bank',
                'credit_name' => [
                    'az' => 'Avtomobil krediti',
                    'en' => 'Auto loan',
                    'ru' => 'Автокредит'
                ],
                'about' => [
                    'az' => 'Yeni və işlənmiş avtomobillərin alınması üçün kredit. İlkin ödəniş 20%-dən başlayır.',
                    'en' => 'Loan for purchasing new and used cars. Down payment starts from 20%.',
                    'ru' => 'Кредит на покупку новых и подержанных автомобилей. Первоначальный взнос от 20%.'
                ],
                'credit_amount' => 50000,
                'min_amount' => 5000,
                'max_amount' => 50000,
                'credit_term' => 60,
                'min_term_months' => 12,
                'max_term_months' => 60,
                'interest_rate' => 10.5,
                'commission_rate' => 1.5,
                'guarantor' => [
                    'az' => 'Tələb olunmur',
                    'en' => 'Not required',
                    'ru' => 'Не требуется'
                ],
                'collateral' => [
                    'az' => 'Alınan avtomobil',
                    'en' => 'Purchased vehicle',
                    'ru' => 'Приобретаемый автомобиль'
                ],
                'method_of_purchase' => [
                    'az' => 'Filial, dealer mərkəzi',
                    'en' => 'Branch, dealer center',
                    'ru' => 'Филиал, дилерский центр'
                ],
                'bank_phone' => '+994 12 496 5050',
                'bank_address' => 'Baku, Nizami district, Hasan Aliyev str. 15',
                'views' => 892,
                'seo_title' => [
                    'az' => 'Paşa Bank Avtomobil Krediti - 10.5% faiz',
                    'en' => 'Pasha Bank Auto Loan - 10.5% interest',
                    'ru' => 'Pasha Bank Автокредит - 10.5% процент'
                ],
                'seo_keywords' => [
                    'az' => 'avto kredit, avtomobil krediti, paşa bank, maşın krediti',
                    'en' => 'auto loan, car loan, pasha bank, vehicle loan',
                    'ru' => 'автокредит, кредит на авто, pasha bank, кредит на машину'
                ],
                'seo_description' => [
                    'az' => 'Paşa Bank-dan 50,000 AZN-dək avtomobil krediti. 10.5% illik faiz, 60 aya qədər müddət.',
                    'en' => 'Auto loan up to 50,000 AZN from Pasha Bank. 10.5% annual interest, up to 60 months term.',
                    'ru' => 'Автокредит до 50 000 AZN от Pasha Bank. 10,5% годовых, срок до 60 месяцев.'
                ],
                'status' => true,
                'order' => 2
            ],
            [
                'credit_type_id' => $mortgageType->id ?? 2,
                'bank_name' => 'ABB',
                'credit_name' => [
                    'az' => 'İpoteka krediti',
                    'en' => 'Mortgage loan',
                    'ru' => 'Ипотечный кредит'
                ],
                'about' => [
                    'az' => 'Mənzil alışı üçün ipoteka krediti. İlkin ödəniş 15%-dən, müddət 25 ilədək.',
                    'en' => 'Mortgage loan for home purchase. Down payment from 15%, term up to 25 years.',
                    'ru' => 'Ипотечный кредит на покупку жилья. Первоначальный взнос от 15%, срок до 25 лет.'
                ],
                'credit_amount' => 150000,
                'min_amount' => 20000,
                'max_amount' => 150000,
                'credit_term' => 300,
                'min_term_months' => 60,
                'max_term_months' => 300,
                'interest_rate' => 8.5,
                'commission_rate' => 0.5,
                'guarantor' => [
                    'az' => 'Tələb olunmur',
                    'en' => 'Not required',
                    'ru' => 'Не требуется'
                ],
                'collateral' => [
                    'az' => 'Alınan mənzil',
                    'en' => 'Purchased property',
                    'ru' => 'Приобретаемая недвижимость'
                ],
                'method_of_purchase' => [
                    'az' => 'Filial',
                    'en' => 'Branch',
                    'ru' => 'Филиал'
                ],
                'bank_phone' => '+994 12 493 0091',
                'bank_address' => 'Baku, Khatai district, Khojaly ave. 67',
                'views' => 2341,
                'seo_title' => [
                    'az' => 'ABB İpoteka Krediti - 8.5% illik faiz',
                    'en' => 'ABB Mortgage Loan - 8.5% annual interest',
                    'ru' => 'ABB Ипотечный кредит - 8.5% годовых'
                ],
                'seo_keywords' => [
                    'az' => 'ipoteka, ev krediti, mənzil krediti, ABB',
                    'en' => 'mortgage, home loan, property loan, ABB',
                    'ru' => 'ипотека, жилищный кредит, кредит на квартиру, ABB'
                ],
                'seo_description' => [
                    'az' => 'ABB-dən 150,000 AZN-dək ipoteka krediti. 8.5% illik faiz, 25 ilədək müddət.',
                    'en' => 'Mortgage loan up to 150,000 AZN from ABB. 8.5% annual interest, up to 25 years term.',
                    'ru' => 'Ипотечный кредит до 150 000 AZN от ABB. 8,5% годовых, срок до 25 лет.'
                ],
                'status' => true,
                'order' => 3
            ],
            [
                'credit_type_id' => $businessType->id ?? 4,
                'bank_name' => 'Bank Respublika',
                'credit_name' => [
                    'az' => 'Biznes krediti',
                    'en' => 'Business loan',
                    'ru' => 'Бизнес кредит'
                ],
                'about' => [
                    'az' => 'Kiçik və orta biznesin inkişafı üçün kredit. Dövriyyə vəsaitlərinin artırılması və avadanlıq alışı.',
                    'en' => 'Loan for small and medium business development. Working capital and equipment purchase.',
                    'ru' => 'Кредит для развития малого и среднего бизнеса. Пополнение оборотных средств и покупка оборудования.'
                ],
                'credit_amount' => 100000,
                'min_amount' => 10000,
                'max_amount' => 100000,
                'credit_term' => 48,
                'min_term_months' => 12,
                'max_term_months' => 48,
                'interest_rate' => 12.0,
                'commission_rate' => 2,
                'guarantor' => [
                    'az' => 'Şirkətin təsisçisi',
                    'en' => 'Company founder',
                    'ru' => 'Учредитель компании'
                ],
                'collateral' => [
                    'az' => 'Daşınmaz əmlak və ya avadanlıq',
                    'en' => 'Real estate or equipment',
                    'ru' => 'Недвижимость или оборудование'
                ],
                'method_of_purchase' => [
                    'az' => 'Filial, biznes mərkəzi',
                    'en' => 'Branch, business center',
                    'ru' => 'Филиал, бизнес центр'
                ],
                'bank_phone' => '+994 12 598 2424',
                'bank_address' => 'Baku, Nasimi district, Lermontov str. 68',
                'views' => 567,
                'seo_title' => [
                    'az' => 'Bank Respublika Biznes Krediti - KOB üçün',
                    'en' => 'Bank Respublika Business Loan - for SME',
                    'ru' => 'Bank Respublika Бизнес кредит - для МСБ'
                ],
                'seo_keywords' => [
                    'az' => 'biznes kredit, KOB krediti, bank respublika',
                    'en' => 'business loan, SME loan, bank respublika',
                    'ru' => 'бизнес кредит, кредит МСБ, bank respublika'
                ],
                'seo_description' => [
                    'az' => 'Bank Respublika-dan 100,000 AZN-dək biznes krediti. 12% illik faiz, 48 aya qədər.',
                    'en' => 'Business loan up to 100,000 AZN from Bank Respublika. 12% annual interest, up to 48 months.',
                    'ru' => 'Бизнес кредит до 100 000 AZN от Bank Respublika. 12% годовых, до 48 месяцев.'
                ],
                'status' => true,
                'order' => 4
            ],
            [
                'credit_type_id' => $educationType->id ?? 5,
                'bank_name' => 'Xalq Bank',
                'credit_name' => [
                    'az' => 'Təhsil krediti',
                    'en' => 'Education loan',
                    'ru' => 'Образовательный кредит'
                ],
                'about' => [
                    'az' => 'Ali və orta ixtisas təhsili üçün kredit. Güzəştli şərtlər, təhsil müddətində ödənişə möhlət.',
                    'en' => 'Loan for higher and vocational education. Preferential terms, payment deferment during studies.',
                    'ru' => 'Кредит на высшее и среднее специальное образование. Льготные условия, отсрочка платежа на период обучения.'
                ],
                'credit_amount' => 20000,
                'min_amount' => 500,
                'max_amount' => 20000,
                'credit_term' => 84,
                'min_term_months' => 12,
                'max_term_months' => 84,
                'interest_rate' => 7.0,
                'commission_rate' => 0,
                'guarantor' => [
                    'az' => 'Valideynlər və ya qəyyum',
                    'en' => 'Parents or guardian',
                    'ru' => 'Родители или опекун'
                ],
                'collateral' => [
                    'az' => 'Tələb olunmur',
                    'en' => 'Not required',
                    'ru' => 'Не требуется'
                ],
                'method_of_purchase' => [
                    'az' => 'Filial, universitet filialı',
                    'en' => 'Branch, university branch',
                    'ru' => 'Филиал, университетский филиал'
                ],
                'bank_phone' => '+994 12 497 7777',
                'bank_address' => 'Baku, Yasamal district, J.Jabbarly str. 32',
                'views' => 432,
                'seo_title' => [
                    'az' => 'Xalq Bank Təhsil Krediti - 7% faizlə',
                    'en' => 'Xalq Bank Education Loan - 7% interest',
                    'ru' => 'Xalq Bank Образовательный кредит - 7% ставка'
                ],
                'seo_keywords' => [
                    'az' => 'təhsil krediti, universitet krediti, xalq bank',
                    'en' => 'education loan, university loan, xalq bank',
                    'ru' => 'образовательный кредит, кредит на учебу, xalq bank'
                ],
                'seo_description' => [
                    'az' => 'Xalq Bank-dan 20,000 AZN-dək təhsil krediti. 7% illik faiz, 84 aya qədər müddət.',
                    'en' => 'Education loan up to 20,000 AZN from Xalq Bank. 7% annual interest, up to 84 months.',
                    'ru' => 'Образовательный кредит до 20 000 AZN от Xalq Bank. 7% годовых, до 84 месяцев.'
                ],
                'status' => true,
                'order' => 5
            ]
        ];

        foreach ($credits as $credit) {
            Credit::create($credit);
        }

        $this->command->info('Credits seeded successfully!');
    }
}