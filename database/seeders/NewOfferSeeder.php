<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\OfferAdvantage;
use App\Models\OffersCategory;
use App\Models\OffersDuration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get bank companies from the new companies table
        $bankTypeId = 1; // Banks (from CompanyTypeHierarchySeeder)
        
        $banks = DB::table('companies')
            ->where('company_type_id', $bankTypeId)
            ->get()
            ->keyBy('slug');

        // Get categories
        $cashCredit = OffersCategory::where('slug', 'nagd-kredit')->first();
        $mortgageCredit = OffersCategory::where('slug', 'ipoteka-krediti')->first();
        $consumerCredit = OffersCategory::where('slug', 'istehlak-krediti')->first();
        $autoCredit = OffersCategory::where('slug', 'avtomobil-krediti')->first();
        $creditCard = OffersCategory::where('slug', 'kredit-karti')->first();

        // Get durations
        $durations = OffersDuration::all();
        $duration60 = $durations->where('title', '60 ay')->first();
        $duration36 = $durations->where('title', '36 ay')->first();
        $duration120 = $durations->where('title', '120 ay')->first();

        $offers = [
            // Kapital Bank offers
            [
                'bank_id' => $banks['kapital-bank']->id ?? null,
                'title' => json_encode(['az' => 'Nağd kredit', 'en' => 'Cash credit', 'ru' => 'Наличный кредит']),
                'category_id' => $cashCredit->id,
                'duration_id' => $duration60->id ?? null,
                'min_amount' => 300,
                'max_amount' => 50000,
                'min_interest_rate' => 10,
                'max_interest_rate' => 15,
                'annual_interest_rate' => 12.5,
                'site_link' => 'https://kapitalbank.az',
                'order' => 1,
                'views' => 5000,
                'monthly_payment' => 510,
                'status' => true,
                'loan_type' => 'cash',
                'max_duration' => 60,
                'employment_reference_required' => false,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => 'Girov tələb olunmur', 'en' => 'No collateral required', 'ru' => 'Залог не требуется']],
                    ['title' => ['az' => 'Sürətli təsdiq', 'en' => 'Fast approval', 'ru' => 'Быстрое одобрение']],
                    ['title' => ['az' => 'Çevik ödəniş', 'en' => 'Flexible payment', 'ru' => 'Гибкая оплата']],
                ],
            ],
            [
                'bank_id' => $banks['kapital-bank']->id ?? null,
                'title' => json_encode(['az' => 'İpoteka krediti', 'en' => 'Mortgage loan', 'ru' => 'Ипотечный кредит']),
                'category_id' => $mortgageCredit->id,
                'duration_id' => $duration120->id ?? null,
                'min_amount' => 15000,
                'max_amount' => 300000,
                'min_interest_rate' => 8,
                'max_interest_rate' => 12,
                'annual_interest_rate' => 10,
                'site_link' => 'https://kapitalbank.az',
                'order' => 4,
                'views' => 3800,
                'monthly_payment' => 1320,
                'status' => true,
                'loan_type' => 'mortgage',
                'max_duration' => 360,
                'employment_reference_required' => true,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => '30 ilə qədər müddət', 'en' => 'Up to 30 years', 'ru' => 'До 30 лет']],
                    ['title' => ['az' => 'Aşağı faiz dərəcəsi', 'en' => 'Low interest rate', 'ru' => 'Низкая процентная ставка']],
                    ['title' => ['az' => 'İlkin ödəniş 15%', 'en' => 'Down payment 15%', 'ru' => 'Первоначальный взнос 15%']],
                ],
            ],
            [
                'bank_id' => $banks['kapital-bank']->id ?? null,
                'title' => json_encode(['az' => 'Kredit kartı', 'en' => 'Credit card', 'ru' => 'Кредитная карта']),
                'category_id' => $creditCard->id ?? $cashCredit->id,
                'duration_id' => null,
                'min_amount' => 300,
                'max_amount' => 10000,
                'min_interest_rate' => 24,
                'max_interest_rate' => 36,
                'annual_interest_rate' => 30,
                'site_link' => 'https://kapitalbank.az',
                'order' => 7,
                'views' => 2900,
                'monthly_payment' => null,
                'status' => true,
                'loan_type' => 'credit_card',
                'max_duration' => null,
                'employment_reference_required' => false,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => '45 günə qədər güzəşt müddəti', 'en' => 'Up to 45 days grace period', 'ru' => 'До 45 дней льготный период']],
                    ['title' => ['az' => 'Cashback imkanı', 'en' => 'Cashback opportunity', 'ru' => 'Возможность кэшбэка']],
                    ['title' => ['az' => 'Dünya üzrə istifadə', 'en' => 'Worldwide usage', 'ru' => 'Использование по всему миру']],
                ],
            ],
            
            // Pasha Bank offers
            [
                'bank_id' => $banks['pasha-bank']->id ?? null,
                'title' => json_encode(['az' => 'Nağd kredit', 'en' => 'Cash credit', 'ru' => 'Наличный кредит']),
                'category_id' => $cashCredit->id,
                'duration_id' => $duration60->id ?? null,
                'min_amount' => 500,
                'max_amount' => 50000,
                'min_interest_rate' => 12,
                'max_interest_rate' => 18,
                'annual_interest_rate' => 15,
                'site_link' => 'https://pashabank.az',
                'order' => 2,
                'views' => 4500,
                'monthly_payment' => 560,
                'status' => true,
                'loan_type' => 'cash',
                'max_duration' => 60,
                'employment_reference_required' => false,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => 'Girov tələb olunmur', 'en' => 'No collateral required', 'ru' => 'Залог не требуется']],
                    ['title' => ['az' => 'Sürətli təsdiq', 'en' => 'Fast approval', 'ru' => 'Быстрое одобрение']],
                    ['title' => ['az' => 'Çevik ödəniş', 'en' => 'Flexible payment', 'ru' => 'Гибкая оплата']],
                ],
            ],
            [
                'bank_id' => $banks['pasha-bank']->id ?? null,
                'title' => json_encode(['az' => 'İstehlak krediti', 'en' => 'Consumer loan', 'ru' => 'Потребительский кредит']),
                'category_id' => $consumerCredit->id,
                'duration_id' => $duration36->id ?? null,
                'min_amount' => 200,
                'max_amount' => 30000,
                'min_interest_rate' => 13,
                'max_interest_rate' => 20,
                'annual_interest_rate' => 16.5,
                'site_link' => 'https://pashabank.az',
                'order' => 5,
                'views' => 3500,
                'monthly_payment' => 914,
                'status' => true,
                'loan_type' => 'consumer',
                'max_duration' => 36,
                'employment_reference_required' => false,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => 'Məqsədli kredit', 'en' => 'Purpose loan', 'ru' => 'Целевой кредит']],
                    ['title' => ['az' => '1 gün ərzində cavab', 'en' => 'Response within 1 day', 'ru' => 'Ответ в течение 1 дня']],
                    ['title' => ['az' => 'Komissiya yoxdur', 'en' => 'No commission', 'ru' => 'Без комиссии']],
                ],
            ],
            
            // ABB offers
            [
                'bank_id' => $banks['abb']->id ?? null,
                'title' => json_encode(['az' => 'Nağd kredit', 'en' => 'Cash credit', 'ru' => 'Наличный кредит']),
                'category_id' => $cashCredit->id,
                'duration_id' => $duration60->id ?? null,
                'min_amount' => 300,
                'max_amount' => 50000,
                'min_interest_rate' => 11,
                'max_interest_rate' => 16,
                'annual_interest_rate' => 13.5,
                'site_link' => 'https://abb-bank.az',
                'order' => 3,
                'views' => 4200,
                'monthly_payment' => 535,
                'status' => true,
                'loan_type' => 'cash',
                'max_duration' => 60,
                'employment_reference_required' => false,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => 'Girov tələb olunmur', 'en' => 'No collateral required', 'ru' => 'Залог не требуется']],
                    ['title' => ['az' => 'Sürətli təsdiq', 'en' => 'Fast approval', 'ru' => 'Быстрое одобрение']],
                    ['title' => ['az' => 'Çevik ödəniş', 'en' => 'Flexible payment', 'ru' => 'Гибкая оплата']],
                ],
            ],
            [
                'bank_id' => $banks['abb']->id ?? null,
                'title' => json_encode(['az' => 'Avtomobil krediti', 'en' => 'Auto loan', 'ru' => 'Автокредит']),
                'category_id' => $autoCredit->id ?? $consumerCredit->id,
                'duration_id' => $duration60->id ?? null,
                'min_amount' => 5000,
                'max_amount' => 100000,
                'min_interest_rate' => 9,
                'max_interest_rate' => 14,
                'annual_interest_rate' => 11.5,
                'site_link' => 'https://abb-bank.az',
                'order' => 6,
                'views' => 3200,
                'monthly_payment' => 2173,
                'status' => true,
                'loan_type' => 'auto',
                'max_duration' => 84,
                'employment_reference_required' => true,
                'guarantor_required' => false,
                'advantages' => [
                    ['title' => ['az' => 'İlkin ödəniş 20%', 'en' => 'Down payment 20%', 'ru' => 'Первоначальный взнос 20%']],
                    ['title' => ['az' => '7 ilə qədər müddət', 'en' => 'Up to 7 years', 'ru' => 'До 7 лет']],
                    ['title' => ['az' => 'Sığorta daxildir', 'en' => 'Insurance included', 'ru' => 'Страховка включена']],
                ],
            ],
        ];

        foreach ($offers as $offerData) {
            $advantages = $offerData['advantages'] ?? [];
            unset($offerData['advantages']);

            // Skip if no bank found
            if (!$offerData['bank_id']) {
                continue;
            }

            // Create the offer
            $offer = Offer::create($offerData);

            // Create advantages
            foreach ($advantages as $advantage) {
                OfferAdvantage::create([
                    'offer_id' => $offer->id,
                    'title' => json_encode($advantage['title']),
                ]);
            }
        }
    }
}