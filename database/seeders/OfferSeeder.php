<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\OfferAdvantage;
use App\Models\Company;
use App\Models\OffersCategory;
use App\Models\OffersDuration;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kapitalBank = Company::where('slug', 'kapital-bank')->first();
        $pashaBank = Company::where('slug', 'pasha-bank')->first();
        $abb = Company::where('slug', 'abb')->first();
        $unibank = Company::where('slug', 'unibank')->first();

        $cashCredit = OffersCategory::where('slug', 'nagd-kredit')->first();
        $mortgageCredit = OffersCategory::where('slug', 'ipoteka-krediti')->first();
        $consumerCredit = OffersCategory::where('slug', 'istehlak-krediti')->first();

        $durations = OffersDuration::all();

        $offers = [
            // Popular cash credit offers for homepage
            [
                'company_id' => $kapitalBank->id,
                'title' => ['az' => 'Nağd kredit', 'en' => 'Cash credit', 'ru' => 'Наличный кредит'],
                'category_id' => $cashCredit->id,
                'duration_id' => $durations->where('title', '60 ay')->first()->id,
                'min_amount' => 300,
                'max_amount' => 50000,
                'min_interest_rate' => 10,
                'max_interest_rate' => 15,
                'site_link' => 'https://kapitalbank.az',
                'order' => 1,
                'views' => 5000,
                'monthly_payment' => 510,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Girov tələb olunmur', 'en' => 'No collateral required', 'ru' => 'Залог не требуется']],
                    ['title' => ['az' => 'Sürətli təsdiq', 'en' => 'Fast approval', 'ru' => 'Быстрое одобрение']],
                    ['title' => ['az' => 'Çevik ödəniş', 'en' => 'Flexible payment', 'ru' => 'Гибкая оплата']],
                ],
            ],
            [
                'company_id' => $pashaBank->id,
                'title' => ['az' => 'Nağd kredit', 'en' => 'Cash credit', 'ru' => 'Наличный кредит'],
                'category_id' => $cashCredit->id,
                'duration_id' => $durations->where('title', '60 ay')->first()->id,
                'min_amount' => 500,
                'max_amount' => 50000,
                'min_interest_rate' => 12,
                'max_interest_rate' => 18,
                'site_link' => 'https://pashabank.az',
                'order' => 2,
                'views' => 4500,
                'monthly_payment' => 560,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Girov tələb olunmur', 'en' => 'No collateral required', 'ru' => 'Залог не требуется']],
                    ['title' => ['az' => 'Sürətli təsdiq', 'en' => 'Fast approval', 'ru' => 'Быстрое одобрение']],
                    ['title' => ['az' => 'Çevik ödəniş', 'en' => 'Flexible payment', 'ru' => 'Гибкая оплата']],
                ],
            ],
            [
                'company_id' => $unibank ? $unibank->id : $abb->id,
                'title' => ['az' => 'Nağd kredit', 'en' => 'Cash credit', 'ru' => 'Наличный кредит'],
                'category_id' => $cashCredit->id,
                'duration_id' => $durations->where('title', '60 ay')->first()->id,
                'min_amount' => 300,
                'max_amount' => 50000,
                'min_interest_rate' => 11,
                'max_interest_rate' => 16,
                'site_link' => 'https://unibank.az',
                'order' => 3,
                'views' => 4200,
                'monthly_payment' => 535,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Girov tələb olunmur', 'en' => 'No collateral required', 'ru' => 'Залог не требуется']],
                    ['title' => ['az' => 'Sürətli təsdiq', 'en' => 'Fast approval', 'ru' => 'Быстрое одобрение']],
                    ['title' => ['az' => 'Çevik ödəniş', 'en' => 'Flexible payment', 'ru' => 'Гибкая оплата']],
                ],
            ],
            // Other offers
            [
                'company_id' => $kapitalBank->id,
                'title' => ['az' => 'İpoteka krediti', 'en' => 'Mortgage loan', 'ru' => 'Ипотечный кредит'],
                'category_id' => $mortgageCredit->id,
                'duration_id' => $durations->where('title', '120 ay')->first()->id,
                'min_amount' => 15000,
                'max_amount' => 300000,
                'min_interest_rate' => 8,
                'max_interest_rate' => 12,
                'site_link' => 'https://kb.az/ipoteka',
                'order' => 4,
                'views' => 890,
                'monthly_payment' => 1200,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Aşağı faiz dərəcəsi', 'en' => 'Low interest rate', 'ru' => 'Низкая процентная ставка']],
                    ['title' => ['az' => 'Uzun müddət', 'en' => 'Long term', 'ru' => 'Долгий срок']],
                    ['title' => ['az' => 'İlkin ödəniş 20%', 'en' => 'Down payment 20%', 'ru' => 'Первоначальный взнос 20%']],
                ],
            ],
            [
                'company_id' => $pashaBank->id,
                'title' => ['az' => 'Biznes krediti', 'en' => 'Business loan', 'ru' => 'Бизнес кредит'],
                'category_id' => OffersCategory::where('slug', 'biznes-krediti')->first()->id,
                'duration_id' => $durations->where('title', '60 ay')->first()->id,
                'min_amount' => 5000,
                'max_amount' => 500000,
                'min_interest_rate' => 12,
                'max_interest_rate' => 18,
                'site_link' => 'https://pashabank.az/biznes-kredit',
                'order' => 5,
                'views' => 567,
                'monthly_payment' => 2500,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Güzəştli şərtlər', 'en' => 'Favorable conditions', 'ru' => 'Выгодные условия']],
                    ['title' => ['az' => 'Fərdi yanaşma', 'en' => 'Individual approach', 'ru' => 'Индивидуальный подход']],
                    ['title' => ['az' => 'Sürətli qərar', 'en' => 'Quick decision', 'ru' => 'Быстрое решение']],
                ],
            ],
            [
                'company_id' => $abb->id,
                'title' => ['az' => 'Taksit kartı', 'en' => 'Installment card', 'ru' => 'Карта рассрочки'],
                'category_id' => $consumerCredit->id,
                'duration_id' => $durations->where('title', '12 ay')->first()->id,
                'min_amount' => 100,
                'max_amount' => 10000,
                'min_interest_rate' => 0,
                'max_interest_rate' => 24,
                'site_link' => 'https://abb.az/taksit-karti',
                'order' => 6,
                'views' => 2340,
                'monthly_payment' => 150,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => '0% komissiya', 'en' => '0% commission', 'ru' => '0% комиссия']],
                    ['title' => ['az' => 'Partnyor mağazalarda endirimlər', 'en' => 'Discounts at partner stores', 'ru' => 'Скидки в партнерских магазинах']],
                    ['title' => ['az' => 'SMS xəbərdarlıq', 'en' => 'SMS notifications', 'ru' => 'SMS уведомления']],
                ],
            ],
        ];

        foreach ($offers as $offerData) {
            $advantages = $offerData['advantages'] ?? [];
            unset($offerData['advantages']);
            
            $offer = Offer::create($offerData);
            
            // Create advantages
            foreach ($advantages as $index => $advantage) {
                OfferAdvantage::create([
                    'offer_id' => $offer->id,
                    'title' => $advantage['title'],
                    'order' => $index + 1,
                    'status' => true,
                ]);
            }
        }
    }
}