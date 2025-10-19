<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyTypeHierarchySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First ensure base types exist (they may not exist on fresh migration)
        $this->ensureBaseTypesExist();
        
        // Update existing parent categories with translations and slugs
        $this->updateExistingTypes();
        
        // Add subcategories for credits and insurance
        $this->addSubcategories();
    }
    
    private function ensureBaseTypesExist(): void
    {
        // Check if base types exist, if not create them with specific IDs
        $existingTypes = DB::table('company_types')->whereIn('id', [1, 2, 3, 4, 5, 6])->pluck('id')->toArray();
        
        if (!in_array(1, $existingTypes)) {
            DB::table('company_types')->insert([
                'id' => 1,
                'type_name' => 'Banks',
                'slug' => 'banklar',
                'description' => 'Banking institutions',
                'is_active' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        if (!in_array(2, $existingTypes)) {
            DB::table('company_types')->insert([
                'id' => 2,
                'type_name' => 'Insurance',
                'slug' => 'sigorta',
                'description' => 'Insurance companies',
                'is_active' => true,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        if (!in_array(3, $existingTypes)) {
            DB::table('company_types')->insert([
                'id' => 3,
                'type_name' => 'Credit Organizations',
                'slug' => 'kredit-teskilatlari',
                'description' => 'Non-bank credit organizations',
                'is_active' => true,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        if (!in_array(4, $existingTypes)) {
            DB::table('company_types')->insert([
                'id' => 4,
                'type_name' => 'Investment',
                'slug' => 'investisiya',
                'description' => 'Investment companies',
                'is_active' => false,
                'display_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        if (!in_array(5, $existingTypes)) {
            DB::table('company_types')->insert([
                'id' => 5,
                'type_name' => 'Leasing',
                'slug' => 'lizinq',
                'description' => 'Leasing companies',
                'is_active' => false,
                'display_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        if (!in_array(6, $existingTypes)) {
            DB::table('company_types')->insert([
                'id' => 6,
                'type_name' => 'Payment System',
                'slug' => 'odenis-sistemi',
                'description' => 'Payment system providers',
                'is_active' => false,
                'display_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    private function updateExistingTypes(): void
    {
        // Update Banks (ID: 1)
        DB::table('company_types')->where('id', 1)->update([
            'type_name' => json_encode([
                'az' => 'Banklar',
                'en' => 'Banks',
                'ru' => 'Банки'
            ]),
            'slug' => 'banklar',
            'description' => json_encode([
                'az' => 'Azərbaycanın aparıcı bankları',
                'en' => 'Leading banks of Azerbaijan',
                'ru' => 'Ведущие банки Азербайджана'
            ]),
            'display_order' => 1,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        
        // Update Insurance (ID: 2)
        DB::table('company_types')->where('id', 2)->update([
            'type_name' => json_encode([
                'az' => 'Sığorta Şirkətləri',
                'en' => 'Insurance Companies',
                'ru' => 'Страховые компании'
            ]),
            'slug' => 'sigorta',
            'description' => json_encode([
                'az' => 'Sığorta xidmətləri təklif edən şirkətlər',
                'en' => 'Companies offering insurance services',
                'ru' => 'Компании, предлагающие страховые услуги'
            ]),
            'display_order' => 2,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        
        // Update Credit Organizations (ID: 3)
        DB::table('company_types')->where('id', 3)->update([
            'type_name' => json_encode([
                'az' => 'Kredit Təşkilatları',
                'en' => 'Credit Organizations',
                'ru' => 'Кредитные организации'
            ]),
            'slug' => 'kredit-teskilatlari',
            'description' => json_encode([
                'az' => 'Bank olmayan kredit təşkilatları',
                'en' => 'Non-bank credit organizations',
                'ru' => 'Небанковские кредитные организации'
            ]),
            'display_order' => 3,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        
        // Update other types to be inactive or with lower priority
        DB::table('company_types')->whereIn('id', [4, 5, 6])->update([
            'is_active' => false,
            'display_order' => 99,
        ]);
    }
    
    private function addSubcategories(): void
    {
        // Delete existing subcategories if any
        DB::table('company_types')->where('id', '>', 6)->delete();
        
        // Credit subcategories (parent_id: 3)
        $creditSubcategories = [
            [
                'id' => 10,
                'type_name' => json_encode([
                    'az' => 'Nağd Kreditlər',
                    'en' => 'Cash Loans',
                    'ru' => 'Наличные кредиты'
                ]),
                'slug' => 'nagd-kreditler',
                'description' => json_encode([
                    'az' => 'Sürətli nağd kredit təklifləri',
                    'en' => 'Fast cash loan offers',
                    'ru' => 'Быстрые предложения наличных кредитов'
                ]),
                'parent_id' => 3, // Credit Organizations
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'type_name' => json_encode([
                    'az' => 'İpoteka Kreditləri',
                    'en' => 'Mortgage Loans',
                    'ru' => 'Ипотечные кредиты'
                ]),
                'slug' => 'ipoteka-kreditler',
                'description' => json_encode([
                    'az' => 'Ev və mənzil alışı üçün ipoteka kreditləri',
                    'en' => 'Mortgage loans for home and apartment purchases',
                    'ru' => 'Ипотечные кредиты для покупки домов и квартир'
                ]),
                'parent_id' => 3,
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'type_name' => json_encode([
                    'az' => 'Avtomobil Kreditləri',
                    'en' => 'Auto Loans',
                    'ru' => 'Автокредиты'
                ]),
                'slug' => 'avtomobil-kreditler',
                'description' => json_encode([
                    'az' => 'Yeni və işlənmiş avtomobil alışı üçün kreditlər',
                    'en' => 'Loans for new and used car purchases',
                    'ru' => 'Кредиты на покупку новых и подержанных автомобилей'
                ]),
                'parent_id' => 3,
                'display_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 13,
                'type_name' => json_encode([
                    'az' => 'Biznes Kreditləri',
                    'en' => 'Business Loans',
                    'ru' => 'Бизнес-кредиты'
                ]),
                'slug' => 'biznes-kreditler',
                'description' => json_encode([
                    'az' => 'Sahibkarlar və şirkətlər üçün biznes kreditləri',
                    'en' => 'Business loans for entrepreneurs and companies',
                    'ru' => 'Бизнес-кредиты для предпринимателей и компаний'
                ]),
                'parent_id' => 3,
                'display_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 14,
                'type_name' => json_encode([
                    'az' => 'Təhsil Kreditləri',
                    'en' => 'Education Loans',
                    'ru' => 'Образовательные кредиты'
                ]),
                'slug' => 'tehsil-kreditler',
                'description' => json_encode([
                    'az' => 'Təhsil məqsədli kreditlər',
                    'en' => 'Loans for educational purposes',
                    'ru' => 'Кредиты на образовательные цели'
                ]),
                'parent_id' => 3,
                'display_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 15,
                'type_name' => json_encode([
                    'az' => 'Kredit Xətləri',
                    'en' => 'Credit Lines',
                    'ru' => 'Кредитные линии'
                ]),
                'slug' => 'kredit-xetleri',
                'description' => json_encode([
                    'az' => 'Dövriyyə vəsaitləri üçün kredit xətləri',
                    'en' => 'Credit lines for working capital',
                    'ru' => 'Кредитные линии для оборотных средств'
                ]),
                'parent_id' => 3,
                'display_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 16,
                'type_name' => json_encode([
                    'az' => 'Lombard Kreditləri',
                    'en' => 'Pawnshop Loans',
                    'ru' => 'Ломбардные кредиты'
                ]),
                'slug' => 'lombard-kreditler',
                'description' => json_encode([
                    'az' => 'Qızıl və digər girov əsasında kreditlər',
                    'en' => 'Loans based on gold and other collateral',
                    'ru' => 'Кредиты под залог золота и другого имущества'
                ]),
                'parent_id' => 3,
                'display_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 17,
                'type_name' => json_encode([
                    'az' => 'Mikrokreditlər',
                    'en' => 'Microloans',
                    'ru' => 'Микрокредиты'
                ]),
                'slug' => 'mikrokreditler',
                'description' => json_encode([
                    'az' => 'Kiçik məbləğli sürətli kreditlər',
                    'en' => 'Small amount fast loans',
                    'ru' => 'Быстрые кредиты на небольшие суммы'
                ]),
                'parent_id' => 3,
                'display_order' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('company_types')->insert($creditSubcategories);

        // Insurance subcategories (parent_id: 2)
        $insuranceSubcategories = [
            [
                'id' => 20,
                'type_name' => json_encode([
                    'az' => 'Həyat Sığortası',
                    'en' => 'Life Insurance',
                    'ru' => 'Страхование жизни'
                ]),
                'slug' => 'heyat-sigortasi',
                'description' => json_encode([
                    'az' => 'Həyat və ölüm halları üçün sığorta',
                    'en' => 'Insurance for life and death cases',
                    'ru' => 'Страхование на случай жизни и смерти'
                ]),
                'parent_id' => 2, // Insurance Companies
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 21,
                'type_name' => json_encode([
                    'az' => 'Tibbi Sığorta',
                    'en' => 'Health Insurance',
                    'ru' => 'Медицинское страхование'
                ]),
                'slug' => 'tibbi-sigorta',
                'description' => json_encode([
                    'az' => 'Sağlamlıq və tibbi xərclər üçün sığorta',
                    'en' => 'Insurance for health and medical expenses',
                    'ru' => 'Страхование здоровья и медицинских расходов'
                ]),
                'parent_id' => 2,
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 22,
                'type_name' => json_encode([
                    'az' => 'Avtomobil Sığortası',
                    'en' => 'Auto Insurance',
                    'ru' => 'Автострахование'
                ]),
                'slug' => 'avtomobil-sigortasi',
                'description' => json_encode([
                    'az' => 'KASKO və icbari sığorta',
                    'en' => 'KASKO and compulsory insurance',
                    'ru' => 'КАСКО и обязательное страхование'
                ]),
                'parent_id' => 2,
                'display_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 23,
                'type_name' => json_encode([
                    'az' => 'Əmlak Sığortası',
                    'en' => 'Property Insurance',
                    'ru' => 'Страхование имущества'
                ]),
                'slug' => 'emlak-sigortasi',
                'description' => json_encode([
                    'az' => 'Ev, mənzil və digər əmlak sığortası',
                    'en' => 'Home, apartment and other property insurance',
                    'ru' => 'Страхование дома, квартиры и другого имущества'
                ]),
                'parent_id' => 2,
                'display_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 24,
                'type_name' => json_encode([
                    'az' => 'Səyahət Sığortası',
                    'en' => 'Travel Insurance',
                    'ru' => 'Туристическое страхование'
                ]),
                'slug' => 'seyahet-sigortasi',
                'description' => json_encode([
                    'az' => 'Xaricdə səyahət zamanı sığorta',
                    'en' => 'Insurance during travel abroad',
                    'ru' => 'Страхование во время путешествий за границу'
                ]),
                'parent_id' => 2,
                'display_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 25,
                'type_name' => json_encode([
                    'az' => 'Biznes Sığortası',
                    'en' => 'Business Insurance',
                    'ru' => 'Бизнес-страхование'
                ]),
                'slug' => 'biznes-sigortasi',
                'description' => json_encode([
                    'az' => 'Biznes riskləri və əmlak sığortası',
                    'en' => 'Business risks and property insurance',
                    'ru' => 'Страхование бизнес-рисков и имущества'
                ]),
                'parent_id' => 2,
                'display_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 26,
                'type_name' => json_encode([
                    'az' => 'Məsuliyyət Sığortası',
                    'en' => 'Liability Insurance',
                    'ru' => 'Страхование ответственности'
                ]),
                'slug' => 'mesuliyyet-sigortasi',
                'description' => json_encode([
                    'az' => 'Mülki məsuliyyət sığortası',
                    'en' => 'Civil liability insurance',
                    'ru' => 'Страхование гражданской ответственности'
                ]),
                'parent_id' => 2,
                'display_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 27,
                'type_name' => json_encode([
                    'az' => 'Bədbəxt Hadisələr Sığortası',
                    'en' => 'Accident Insurance',
                    'ru' => 'Страхование от несчастных случаев'
                ]),
                'slug' => 'bedbaxt-hadiseler-sigortasi',
                'description' => json_encode([
                    'az' => 'Fərdi bədbəxt hadisələrdən sığorta',
                    'en' => 'Personal accident insurance',
                    'ru' => 'Страхование от личных несчастных случаев'
                ]),
                'parent_id' => 2,
                'display_order' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('company_types')->insert($insuranceSubcategories);
    }
}