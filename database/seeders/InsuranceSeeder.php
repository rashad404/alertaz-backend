<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InsuranceCategory;
use App\Models\InsuranceProvider;
use App\Models\Insurance;
use App\Models\InsuranceAdvantage;

class InsuranceSeeder extends Seeder
{
    public function run()
    {
        // Create insurance categories
        $categories = [
            [
                'title' => ['az' => 'Avto sığorta', 'en' => 'Auto Insurance', 'ru' => 'Автострахование'],
                'slug' => 'auto',
                'icon' => 'car',
                'order' => 1
            ],
            [
                'title' => ['az' => 'Tibbi sığorta', 'en' => 'Health Insurance', 'ru' => 'Медицинское страхование'],
                'slug' => 'health',
                'icon' => 'health',
                'order' => 2
            ],
            [
                'title' => ['az' => 'Əmlak sığortası', 'en' => 'Property Insurance', 'ru' => 'Страхование имущества'],
                'slug' => 'property',
                'icon' => 'home',
                'order' => 3
            ],
            [
                'title' => ['az' => 'Səyahət sığortası', 'en' => 'Travel Insurance', 'ru' => 'Туристическое страхование'],
                'slug' => 'travel',
                'icon' => 'plane',
                'order' => 4
            ],
            [
                'title' => ['az' => 'Həyat sığortası', 'en' => 'Life Insurance', 'ru' => 'Страхование жизни'],
                'slug' => 'life',
                'icon' => 'heart',
                'order' => 5
            ]
        ];

        foreach ($categories as $categoryData) {
            InsuranceCategory::create($categoryData);
        }

        // Create insurance providers
        $providers = [
            [
                'name' => ['az' => 'AXA MBASK', 'en' => 'AXA MBASK', 'ru' => 'AXA MBASK'],
                'slug' => 'axa-mbask',
                'logo' => 'axa-logo.png',
                'description' => [
                    'az' => 'Azərbaycanın aparıcı sığorta şirkətlərindən biri',
                    'en' => 'One of the leading insurance companies in Azerbaijan',
                    'ru' => 'Одна из ведущих страховых компаний Азербайджана'
                ],
                'website' => 'https://axa-mbask.az',
                'phones' => ['994124040404', '994502000000'],
                'email' => ['info@axa-mbask.az']
            ],
            [
                'name' => ['az' => 'Paşa Sığorta', 'en' => 'Pasha Insurance', 'ru' => 'Паша Страхование'],
                'slug' => 'pasha-insurance',
                'logo' => 'pasha-insurance.png',
                'description' => [
                    'az' => 'Paşa Holdinqin sığorta şirkəti',
                    'en' => 'Insurance company of Pasha Holding',
                    'ru' => 'Страховая компания Pasha Holding'
                ],
                'website' => 'https://pasha-insurance.az',
                'phones' => ['994125981898'],
                'email' => ['info@pasha-insurance.az']
            ],
            [
                'name' => ['az' => 'Atəşgah Sığorta', 'en' => 'Ateshgah Insurance', 'ru' => 'Атешгях Страхование'],
                'slug' => 'ateshgah',
                'logo' => 'ateshgah.png',
                'description' => [
                    'az' => 'Azərbaycanın ilk özəl sığorta şirkəti',
                    'en' => 'First private insurance company in Azerbaijan',
                    'ru' => 'Первая частная страховая компания в Азербайджане'
                ],
                'website' => 'https://ateshgah.az',
                'phones' => ['994124658600'],
                'email' => ['office@ateshgah.az']
            ]
        ];

        foreach ($providers as $providerData) {
            InsuranceProvider::create($providerData);
        }

        // Get created data for relationships
        $autoCategory = InsuranceCategory::where('slug', 'auto')->first();
        $healthCategory = InsuranceCategory::where('slug', 'health')->first();
        $propertyCategory = InsuranceCategory::where('slug', 'property')->first();
        $travelCategory = InsuranceCategory::where('slug', 'travel')->first();
        $lifeCategory = InsuranceCategory::where('slug', 'life')->first();

        $axaProvider = InsuranceProvider::where('slug', 'axa-mbask')->first();
        $pashaProvider = InsuranceProvider::where('slug', 'pasha-insurance')->first();
        $ateshgahProvider = InsuranceProvider::where('slug', 'ateshgah')->first();

        // Create insurance products
        $insurances = [
            // Auto Insurance
            [
                'category_id' => $autoCategory->id,
                'provider_id' => $axaProvider->id,
                'slug' => 'kasko-sigortasi-premium',
                'title' => [
                    'az' => 'Kasko Sığortası - Premium',
                    'en' => 'Comprehensive Auto Insurance - Premium',
                    'ru' => 'КАСКО Страхование - Премиум'
                ],
                'description' => [
                    'az' => 'Avtomobiliniz üçün tam qoruma paketi',
                    'en' => 'Complete protection package for your vehicle',
                    'ru' => 'Полный пакет защиты для вашего автомобиля'
                ],
                'coverage_amount' => [
                    'min' => 5000,
                    'max' => 100000,
                    'currency' => 'AZN'
                ],
                'premium' => [
                    'min' => 300,
                    'max' => 3000,
                    'currency' => 'AZN',
                    'period' => 'yearly'
                ],
                'duration' => [
                    'az' => '1 il',
                    'en' => '1 year',
                    'ru' => '1 год'
                ],
                'features' => [
                    'az' => ['Tam kasko təminatı', 'Evakuator xidməti', '24/7 dəstək'],
                    'en' => ['Full comprehensive coverage', 'Towing service', '24/7 support'],
                    'ru' => ['Полное КАСКО покрытие', 'Эвакуатор', 'Поддержка 24/7']
                ],
                'is_featured' => true,
                'order' => 1
            ],
            [
                'category_id' => $autoCategory->id,
                'provider_id' => $pashaProvider->id,
                'slug' => 'icbari-sigorta',
                'title' => [
                    'az' => 'İcbari Sığorta',
                    'en' => 'Mandatory Insurance',
                    'ru' => 'Обязательное страхование'
                ],
                'description' => [
                    'az' => 'Qanunla tələb olunan icbari avtomobil sığortası',
                    'en' => 'Legally required mandatory auto insurance',
                    'ru' => 'Обязательное автострахование по закону'
                ],
                'coverage_amount' => [
                    'min' => 10000,
                    'max' => 30000,
                    'currency' => 'AZN'
                ],
                'premium' => [
                    'min' => 50,
                    'max' => 150,
                    'currency' => 'AZN',
                    'period' => 'yearly'
                ],
                'duration' => [
                    'az' => '1 il',
                    'en' => '1 year',
                    'ru' => '1 год'
                ],
                'features' => [
                    'az' => ['Üçüncü şəxslərə dəyən ziyan', 'Qanuni tələblərə uyğun'],
                    'en' => ['Third party liability', 'Legal compliance'],
                    'ru' => ['Ответственность перед третьими лицами', 'Соответствие законодательству']
                ],
                'order' => 2
            ],
            // Health Insurance
            [
                'category_id' => $healthCategory->id,
                'provider_id' => $axaProvider->id,
                'slug' => 'aile-saglamliq-paketi',
                'title' => [
                    'az' => 'Ailə Sağlamlıq Paketi',
                    'en' => 'Family Health Package',
                    'ru' => 'Семейный пакет здоровья'
                ],
                'description' => [
                    'az' => 'Bütün ailə üçün kompleks tibbi sığorta',
                    'en' => 'Comprehensive health insurance for the whole family',
                    'ru' => 'Комплексное медицинское страхование для всей семьи'
                ],
                'coverage_amount' => [
                    'min' => 10000,
                    'max' => 50000,
                    'currency' => 'AZN'
                ],
                'premium' => [
                    'min' => 500,
                    'max' => 2000,
                    'currency' => 'AZN',
                    'period' => 'yearly'
                ],
                'duration' => [
                    'az' => '1 il',
                    'en' => '1 year',
                    'ru' => '1 год'
                ],
                'features' => [
                    'az' => ['Ambulator müalicə', 'Stasionar müalicə', 'Diaqnostika', 'Dərman təminatı'],
                    'en' => ['Outpatient treatment', 'Inpatient treatment', 'Diagnostics', 'Medicine coverage'],
                    'ru' => ['Амбулаторное лечение', 'Стационарное лечение', 'Диагностика', 'Лекарственное обеспечение']
                ],
                'is_featured' => true,
                'order' => 1
            ],
            // Property Insurance
            [
                'category_id' => $propertyCategory->id,
                'provider_id' => $ateshgahProvider->id,
                'slug' => 'ev-sigortasi',
                'title' => [
                    'az' => 'Ev Sığortası',
                    'en' => 'Home Insurance',
                    'ru' => 'Страхование жилья'
                ],
                'description' => [
                    'az' => 'Eviniz və əmlakınız üçün tam qoruma',
                    'en' => 'Complete protection for your home and property',
                    'ru' => 'Полная защита вашего дома и имущества'
                ],
                'coverage_amount' => [
                    'min' => 20000,
                    'max' => 500000,
                    'currency' => 'AZN'
                ],
                'premium' => [
                    'min' => 200,
                    'max' => 2000,
                    'currency' => 'AZN',
                    'period' => 'yearly'
                ],
                'duration' => [
                    'az' => '1 il',
                    'en' => '1 year',
                    'ru' => '1 год'
                ],
                'features' => [
                    'az' => ['Yanğından qoruma', 'Su basmasından qoruma', 'Oğurluqdan qoruma', 'Təbii fəlakətlərdən qoruma'],
                    'en' => ['Fire protection', 'Flood protection', 'Theft protection', 'Natural disaster protection'],
                    'ru' => ['Защита от пожара', 'Защита от наводнения', 'Защита от кражи', 'Защита от стихийных бедствий']
                ],
                'order' => 1
            ],
            // Travel Insurance
            [
                'category_id' => $travelCategory->id,
                'provider_id' => $pashaProvider->id,
                'slug' => 'beynelxalq-seyahet-sigortasi',
                'title' => [
                    'az' => 'Beynəlxalq Səyahət Sığortası',
                    'en' => 'International Travel Insurance',
                    'ru' => 'Международное туристическое страхование'
                ],
                'description' => [
                    'az' => 'Xaricdə səyahət zamanı tam təminat',
                    'en' => 'Complete coverage during international travel',
                    'ru' => 'Полное покрытие во время международных поездок'
                ],
                'coverage_amount' => [
                    'min' => 30000,
                    'max' => 100000,
                    'currency' => 'EUR'
                ],
                'premium' => [
                    'min' => 20,
                    'max' => 200,
                    'currency' => 'AZN',
                    'period' => 'per_trip'
                ],
                'duration' => [
                    'az' => '1-365 gün',
                    'en' => '1-365 days',
                    'ru' => '1-365 дней'
                ],
                'features' => [
                    'az' => ['Tibbi xərclər', 'Baqaj itkisi', 'Uçuş ləğvi', 'Təcili evakuasiya'],
                    'en' => ['Medical expenses', 'Luggage loss', 'Flight cancellation', 'Emergency evacuation'],
                    'ru' => ['Медицинские расходы', 'Потеря багажа', 'Отмена рейса', 'Экстренная эвакуация']
                ],
                'is_featured' => true,
                'order' => 1
            ]
        ];

        foreach ($insurances as $insuranceData) {
            $insurance = Insurance::create($insuranceData);
            
            // Add some advantages
            $advantages = [
                [
                    'title' => ['az' => 'Sürətli ödəniş', 'en' => 'Fast payment', 'ru' => 'Быстрая выплата'],
                    'description' => ['az' => '3 iş günü ərzində', 'en' => 'Within 3 business days', 'ru' => 'В течение 3 рабочих дней'],
                    'icon' => 'speed',
                    'order' => 1
                ],
                [
                    'title' => ['az' => 'Onlayn müraciət', 'en' => 'Online application', 'ru' => 'Онлайн заявка'],
                    'description' => ['az' => 'Ofisə gəlmədən', 'en' => 'Without visiting office', 'ru' => 'Без посещения офиса'],
                    'icon' => 'online',
                    'order' => 2
                ],
                [
                    'title' => ['az' => '24/7 Dəstək', 'en' => '24/7 Support', 'ru' => 'Поддержка 24/7'],
                    'description' => ['az' => 'İstənilən vaxt', 'en' => 'Anytime', 'ru' => 'В любое время'],
                    'icon' => 'support',
                    'order' => 3
                ]
            ];
            
            foreach ($advantages as $advantageData) {
                $insurance->advantages()->create($advantageData);
            }
        }
        
        $this->command->info('Insurance data seeded successfully!');
    }
}