<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\OfferAdvantage;
use App\Models\Company;
use App\Models\OffersCategory;
use App\Models\OffersDuration;
use Illuminate\Database\Seeder;

class DepositOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get banks - only get banks that actually exist
        $bankSlugs = ['kapital-bank', 'pasa-bank', 'abb'];
        $banks = [];
        foreach ($bankSlugs as $slug) {
            $bank = Company::where('slug', $slug)->first();
            if ($bank) {
                $banks[$slug] = $bank;
            }
        }

        // Get deposit categories
        $depositCategory = OffersCategory::where('slug', 'deposit')->first();
        $termDepositCategory = OffersCategory::where('slug', 'term-deposit')->first();
        $savingsDepositCategory = OffersCategory::where('slug', 'savings-deposit')->first();

        // Get or create deposit durations
        $durations = [
            '3 ay' => OffersDuration::firstOrCreate(
                ['title' => json_encode(['az' => '3 ay', 'en' => '3 months', 'ru' => '3 месяца'])],
                ['order' => 1, 'status' => true]
            ),
            '6 ay' => OffersDuration::firstOrCreate(
                ['title' => json_encode(['az' => '6 ay', 'en' => '6 months', 'ru' => '6 месяцев'])],
                ['order' => 2, 'status' => true]
            ),
            '12 ay' => OffersDuration::firstOrCreate(
                ['title' => json_encode(['az' => '12 ay', 'en' => '12 months', 'ru' => '12 месяцев'])],
                ['order' => 3, 'status' => true]
            ),
            '18 ay' => OffersDuration::firstOrCreate(
                ['title' => json_encode(['az' => '18 ay', 'en' => '18 months', 'ru' => '18 месяцев'])],
                ['order' => 4, 'status' => true]
            ),
            '24 ay' => OffersDuration::firstOrCreate(
                ['title' => json_encode(['az' => '24 ay', 'en' => '24 months', 'ru' => '24 месяца'])],
                ['order' => 5, 'status' => true]
            ),
            '36 ay' => OffersDuration::firstOrCreate(
                ['title' => json_encode(['az' => '36 ay', 'en' => '36 months', 'ru' => '36 месяцев'])],
                ['order' => 6, 'status' => true]
            ),
        ];

        $deposits = [
            // Kapital Bank Deposits
            [
                'company_id' => $banks['kapital-bank']?->id,
                'title' => ['az' => 'Universal depozit', 'en' => 'Universal deposit', 'ru' => 'Универсальный депозит'],
                'category_id' => $depositCategory->id,
                'duration_id' => $durations['12 ay']->id,
                'min_amount' => 100,
                'max_amount' => 250000,
                'min_interest_rate' => 8.5,
                'max_interest_rate' => 11.5,
                'site_link' => 'https://kapitalbank.az/deposits',
                'order' => 1,
                'views' => 1500,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Yüksək faiz dərəcəsi', 'en' => 'High interest rate', 'ru' => 'Высокая процентная ставка']],
                    ['title' => ['az' => 'Təminatlı', 'en' => 'Guaranteed', 'ru' => 'Гарантированный']],
                    ['title' => ['az' => 'Çevik şərtlər', 'en' => 'Flexible terms', 'ru' => 'Гибкие условия']],
                ],
            ],
            [
                'company_id' => $banks['kapital-bank']?->id,
                'title' => ['az' => 'Yığım depoziti', 'en' => 'Savings deposit', 'ru' => 'Накопительный депозит'],
                'category_id' => $savingsDepositCategory->id,
                'duration_id' => $durations['24 ay']->id,
                'min_amount' => 50,
                'max_amount' => 100000,
                'min_interest_rate' => 9,
                'max_interest_rate' => 12,
                'site_link' => 'https://kapitalbank.az/savings',
                'order' => 2,
                'views' => 1200,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Əlavə yatırım imkanı', 'en' => 'Additional deposit option', 'ru' => 'Возможность дополнительных взносов']],
                    ['title' => ['az' => 'Bonus faizlər', 'en' => 'Bonus interest', 'ru' => 'Бонусные проценты']],
                ],
            ],

            // Pasha Bank Deposits
            [
                'company_id' => isset($banks['pasa-bank']) ? $banks['pasa-bank']->id : null,
                'title' => ['az' => 'Stabil gəlir depoziti', 'en' => 'Stable income deposit', 'ru' => 'Депозит стабильный доход'],
                'category_id' => $termDepositCategory->id,
                'duration_id' => $durations['18 ay']->id,
                'min_amount' => 500,
                'max_amount' => 500000,
                'min_interest_rate' => 10,
                'max_interest_rate' => 13,
                'site_link' => 'https://pashabank.az/deposits',
                'order' => 3,
                'views' => 980,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Premium bank xidməti', 'en' => 'Premium banking service', 'ru' => 'Премиум банковское обслуживание']],
                    ['title' => ['az' => 'Yüksək məbləğlər üçün xüsusi şərtlər', 'en' => 'Special terms for large amounts', 'ru' => 'Особые условия для крупных сумм']],
                ],
            ],
            [
                'company_id' => isset($banks['pasa-bank']) ? $banks['pasa-bank']->id : null,
                'title' => ['az' => 'VIP depozit', 'en' => 'VIP deposit', 'ru' => 'VIP депозит'],
                'category_id' => $depositCategory->id,
                'duration_id' => $durations['36 ay']->id,
                'min_amount' => 10000,
                'max_amount' => 1000000,
                'min_interest_rate' => 11,
                'max_interest_rate' => 14,
                'site_link' => 'https://pashabank.az/vip-deposit',
                'order' => 4,
                'views' => 450,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Fərdi menecerlə xidmət', 'en' => 'Personal manager service', 'ru' => 'Персональный менеджер']],
                    ['title' => ['az' => 'Maksimal faiz dərəcəsi', 'en' => 'Maximum interest rate', 'ru' => 'Максимальная процентная ставка']],
                ],
            ],

            // ABB Deposits
            [
                'company_id' => isset($banks['abb']) ? $banks['abb']->id : null,
                'title' => ['az' => 'Dinamik depozit', 'en' => 'Dynamic deposit', 'ru' => 'Динамичный депозит'],
                'category_id' => $depositCategory->id,
                'duration_id' => $durations['6 ay']->id,
                'min_amount' => 200,
                'max_amount' => 150000,
                'min_interest_rate' => 7.5,
                'max_interest_rate' => 10.5,
                'site_link' => 'https://abb.az/deposits',
                'order' => 5,
                'views' => 1100,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Hər ay artan faiz', 'en' => 'Monthly increasing interest', 'ru' => 'Ежемесячно растущие проценты']],
                    ['title' => ['az' => 'Mobil tətbiqdən idarəetmə', 'en' => 'Mobile app management', 'ru' => 'Управление через мобильное приложение']],
                    ['title' => ['az' => 'Cashback bonusları', 'en' => 'Cashback bonuses', 'ru' => 'Кэшбэк бонусы']],
                ],
            ],
            [
                'company_id' => isset($banks['abb']) ? $banks['abb']->id : null,
                'title' => ['az' => 'Uşaq yığım depoziti', 'en' => 'Children savings deposit', 'ru' => 'Детский накопительный депозит'],
                'category_id' => $savingsDepositCategory->id,
                'duration_id' => $durations['36 ay']->id,
                'min_amount' => 20,
                'max_amount' => 50000,
                'min_interest_rate' => 10,
                'max_interest_rate' => 13,
                'site_link' => 'https://abb.az/kids-deposit',
                'order' => 6,
                'views' => 670,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Uşaqların gələcəyi üçün', 'en' => 'For children\'s future', 'ru' => 'Для будущего детей']],
                    ['title' => ['az' => 'Yüksək faiz', 'en' => 'High interest', 'ru' => 'Высокие проценты']],
                ],
            ],

            // Additional Kapital Bank Products (using Kapital Bank for demo)
            [
                'company_id' => isset($banks['kapital-bank']) ? $banks['kapital-bank']->id : null,
                'title' => ['az' => 'Elit depozit', 'en' => 'Elite deposit', 'ru' => 'Элитный депозит'],
                'category_id' => $termDepositCategory->id,
                'duration_id' => $durations['12 ay']->id,
                'min_amount' => 100,
                'max_amount' => 200000,
                'min_interest_rate' => 8,
                'max_interest_rate' => 11,
                'site_link' => 'https://kapitalbank.az/elite-deposit',
                'order' => 7,
                'views' => 890,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => '100% təminatlı', 'en' => '100% guaranteed', 'ru' => '100% гарантия']],
                    ['title' => ['az' => 'Sərfəli şərtlər', 'en' => 'Favorable conditions', 'ru' => 'Выгодные условия']],
                ],
            ],

            // More Pasha Bank Products
            [
                'company_id' => isset($banks['pasa-bank']) ? $banks['pasa-bank']->id : null,
                'title' => ['az' => 'Biznes depozit', 'en' => 'Business deposit', 'ru' => 'Бизнес депозит'],
                'category_id' => $depositCategory->id,
                'duration_id' => $durations['24 ay']->id,
                'min_amount' => 300,
                'max_amount' => 300000,
                'min_interest_rate' => 9.5,
                'max_interest_rate' => 12.5,
                'site_link' => 'https://pashabank.az/business-deposit',
                'order' => 8,
                'views' => 560,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Etibarlı bank', 'en' => 'Reliable bank', 'ru' => 'Надежный банк']],
                    ['title' => ['az' => 'Şəffaf şərtlər', 'en' => 'Transparent conditions', 'ru' => 'Прозрачные условия']],
                ],
            ],

            // ABB Special Products
            [
                'company_id' => isset($banks['abb']) ? $banks['abb']->id : null,
                'title' => ['az' => 'Smart depozit', 'en' => 'Smart deposit', 'ru' => 'Смарт депозит'],
                'category_id' => $depositCategory->id,
                'duration_id' => $durations['12 ay']->id,
                'min_amount' => 50,
                'max_amount' => 100000,
                'min_interest_rate' => 8.5,
                'max_interest_rate' => 11,
                'site_link' => 'https://abb.az/smart-deposit',
                'order' => 9,
                'views' => 1250,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Smart bonus sistеmi', 'en' => 'Smart bonus system', 'ru' => 'Система смарт бонусов']],
                    ['title' => ['az' => 'Sürətli onlayn açılış', 'en' => 'Fast online opening', 'ru' => 'Быстрое онлайн открытие']],
                ],
            ],
            [
                'company_id' => isset($banks['abb']) ? $banks['abb']->id : null,
                'title' => ['az' => 'Ailə depoziti', 'en' => 'Family deposit', 'ru' => 'Семейный депозит'],
                'category_id' => $savingsDepositCategory->id,
                'duration_id' => $durations['18 ay']->id,
                'min_amount' => 30,
                'max_amount' => 75000,
                'min_interest_rate' => 9,
                'max_interest_rate' => 12,
                'site_link' => 'https://abb.az/family-deposit',
                'order' => 10,
                'views' => 780,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Kiçik məbləğlərdən başlayın', 'en' => 'Start with small amounts', 'ru' => 'Начните с малых сумм']],
                    ['title' => ['az' => 'Aylıq əlavə yatırım', 'en' => 'Monthly additional deposits', 'ru' => 'Ежемесячные дополнительные взносы']],
                ],
            ],

            // Kapital Bank Premium Products
            [
                'company_id' => isset($banks['kapital-bank']) ? $banks['kapital-bank']->id : null,
                'title' => ['az' => 'Premium depozit', 'en' => 'Premium deposit', 'ru' => 'Премиум депозит'],
                'category_id' => $termDepositCategory->id,
                'duration_id' => $durations['12 ay']->id,
                'min_amount' => 250,
                'max_amount' => 250000,
                'min_interest_rate' => 8,
                'max_interest_rate' => 10.5,
                'site_link' => 'https://kapitalbank.az/premium-deposit',
                'order' => 11,
                'views' => 430,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Mərkəzi bankın təminatı', 'en' => 'Central bank guarantee', 'ru' => 'Гарантия центрального банка']],
                    ['title' => ['az' => 'Rahat şərtlər', 'en' => 'Comfortable conditions', 'ru' => 'Комфортные условия']],
                ],
            ],

            // Pasha Bank Express Products
            [
                'company_id' => isset($banks['pasa-bank']) ? $banks['pasa-bank']->id : null,
                'title' => ['az' => 'Express depozit', 'en' => 'Express deposit', 'ru' => 'Экспресс депозит'],
                'category_id' => $depositCategory->id,
                'duration_id' => $durations['6 ay']->id,
                'min_amount' => 100,
                'max_amount' => 100000,
                'min_interest_rate' => 7,
                'max_interest_rate' => 9.5,
                'site_link' => 'https://pashabank.az/express-deposit',
                'order' => 12,
                'views' => 340,
                'status' => true,
                'advantages' => [
                    ['title' => ['az' => 'Sürətli rəsmiləşdirmə', 'en' => 'Quick registration', 'ru' => 'Быстрое оформление']],
                    ['title' => ['az' => 'Minimum sənəd', 'en' => 'Minimum documents', 'ru' => 'Минимум документов']],
                ],
            ],
        ];

        foreach ($deposits as $depositData) {
            // Skip if company doesn't exist
            if (!$depositData['company_id']) {
                continue;
            }

            $advantages = $depositData['advantages'] ?? [];
            unset($depositData['advantages']);
            
            // Check if similar offer already exists
            $titleAz = is_array($depositData['title']) ? $depositData['title']['az'] : json_decode($depositData['title'], true)['az'];
            $existingOffer = Offer::where('company_id', $depositData['company_id'])
                ->where('category_id', $depositData['category_id'])
                ->whereJsonContains('title->az', $titleAz)
                ->first();
            
            if (!$existingOffer) {
                // Encode title if not already encoded
                if (is_array($depositData['title'])) {
                    $depositData['title'] = json_encode($depositData['title']);
                }
                
                $offer = Offer::create($depositData);
                
                // Create advantages
                foreach ($advantages as $index => $advantage) {
                    OfferAdvantage::create([
                        'offer_id' => $offer->id,
                        'title' => is_array($advantage['title']) ? json_encode($advantage['title']) : $advantage['title'],
                        'order' => $index + 1,
                        'status' => true,
                    ]);
                }
            }
        }
    }
}