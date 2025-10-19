<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing menus
        Menu::truncate();
        
        // Main menu items
        $menus = [
            [
                'title' => [
                    'az' => 'Şirkətlər',
                    'en' => 'Companies',
                    'ru' => 'Компании'
                ],
                'slug' => 'sirketler',
                'url' => '/sirketler',
                'position' => 1,
                'has_dropdown' => false,
                'is_active' => true,
                'menu_location' => 'header',
            ],
            [
                'title' => [
                    'az' => 'Banklar',
                    'en' => 'Banks',
                    'ru' => 'Банки'
                ],
                'slug' => 'banklar',
                'url' => '/sirketler/banklar',
                'position' => 2,
                'has_dropdown' => false,
                'is_active' => true,
                'menu_location' => 'header',
            ],
            [
                'title' => [
                    'az' => 'Kreditlər',
                    'en' => 'Credits',
                    'ru' => 'Кредиты'
                ],
                'slug' => 'kreditler',
                'url' => '/sirketler/kredit-teskilatlari',
                'position' => 3,
                'has_dropdown' => true,
                'is_active' => true,
                'menu_location' => 'header',
                'children' => [
                    [
                        'title' => [
                            'az' => 'Nağd Kreditlər',
                            'en' => 'Cash Loans',
                            'ru' => 'Наличные кредиты'
                        ],
                        'slug' => 'nagd-kreditler',
                        'url' => '/sirketler/kredit-teskilatlari/nagd-kreditler',
                        'position' => 1,
                    ],
                    [
                        'title' => [
                            'az' => 'İpoteka Kreditləri',
                            'en' => 'Mortgage Loans',
                            'ru' => 'Ипотечные кредиты'
                        ],
                        'slug' => 'ipoteka-kreditler',
                        'url' => '/sirketler/kredit-teskilatlari/ipoteka-kreditler',
                        'position' => 2,
                    ],
                    [
                        'title' => [
                            'az' => 'Avtomobil Kreditləri',
                            'en' => 'Auto Loans',
                            'ru' => 'Автокредиты'
                        ],
                        'slug' => 'avtomobil-kreditler',
                        'url' => '/sirketler/kredit-teskilatlari/avtomobil-kreditler',
                        'position' => 3,
                    ],
                    [
                        'title' => [
                            'az' => 'Biznes Kreditləri',
                            'en' => 'Business Loans',
                            'ru' => 'Бизнес кредиты'
                        ],
                        'slug' => 'biznes-kreditler',
                        'url' => '/sirketler/kredit-teskilatlari/biznes-kreditler',
                        'position' => 4,
                    ],
                    [
                        'title' => [
                            'az' => 'Təhsil Kreditləri',
                            'en' => 'Education Loans',
                            'ru' => 'Образовательные кредиты'
                        ],
                        'slug' => 'tehsil-kreditler',
                        'url' => '/sirketler/kredit-teskilatlari/tehsil-kreditler',
                        'position' => 5,
                    ],
                    [
                        'title' => [
                            'az' => 'Kredit Xətləri',
                            'en' => 'Credit Lines',
                            'ru' => 'Кредитные линии'
                        ],
                        'slug' => 'kredit-xetleri',
                        'url' => '/sirketler/kredit-teskilatlari/kredit-xetleri',
                        'position' => 6,
                    ],
                    [
                        'title' => [
                            'az' => 'Lombard Kreditləri',
                            'en' => 'Pawnshop Loans',
                            'ru' => 'Ломбардные кредиты'
                        ],
                        'slug' => 'lombard-kreditler',
                        'url' => '/sirketler/kredit-teskilatlari/lombard-kreditler',
                        'position' => 7,
                    ],
                    [
                        'title' => [
                            'az' => 'Mikrokreditlər',
                            'en' => 'Microloans',
                            'ru' => 'Микрокредиты'
                        ],
                        'slug' => 'mikrokreditler',
                        'url' => '/sirketler/kredit-teskilatlari/mikrokreditler',
                        'position' => 8,
                    ],
                ],
            ],
            [
                'title' => [
                    'az' => 'Sığorta',
                    'en' => 'Insurance',
                    'ru' => 'Страхование'
                ],
                'slug' => 'sigorta',
                'url' => '/sirketler/sigorta',
                'position' => 4,
                'has_dropdown' => true,
                'is_active' => true,
                'menu_location' => 'header',
                'children' => [
                    [
                        'title' => [
                            'az' => 'Həyat Sığortası',
                            'en' => 'Life Insurance',
                            'ru' => 'Страхование жизни'
                        ],
                        'slug' => 'heyat-sigortasi',
                        'url' => '/sirketler/sigorta/heyat-sigortasi',
                        'position' => 1,
                    ],
                    [
                        'title' => [
                            'az' => 'Tibbi Sığorta',
                            'en' => 'Health Insurance',
                            'ru' => 'Медицинское страхование'
                        ],
                        'slug' => 'tibbi-sigorta',
                        'url' => '/sirketler/sigorta/tibbi-sigorta',
                        'position' => 2,
                    ],
                    [
                        'title' => [
                            'az' => 'Avtomobil Sığortası',
                            'en' => 'Auto Insurance',
                            'ru' => 'Автострахование'
                        ],
                        'slug' => 'avtomobil-sigortasi',
                        'url' => '/sirketler/sigorta/avtomobil-sigortasi',
                        'position' => 3,
                    ],
                    [
                        'title' => [
                            'az' => 'Əmlak Sığortası',
                            'en' => 'Property Insurance',
                            'ru' => 'Страхование имущества'
                        ],
                        'slug' => 'emlak-sigortasi',
                        'url' => '/sirketler/sigorta/emlak-sigortasi',
                        'position' => 4,
                    ],
                    [
                        'title' => [
                            'az' => 'Səyahət Sığortası',
                            'en' => 'Travel Insurance',
                            'ru' => 'Туристическое страхование'
                        ],
                        'slug' => 'seyahet-sigortasi',
                        'url' => '/sirketler/sigorta/seyahet-sigortasi',
                        'position' => 5,
                    ],
                    [
                        'title' => [
                            'az' => 'Biznes Sığortası',
                            'en' => 'Business Insurance',
                            'ru' => 'Страхование бизнеса'
                        ],
                        'slug' => 'biznes-sigortasi',
                        'url' => '/sirketler/sigorta/biznes-sigortasi',
                        'position' => 6,
                    ],
                    [
                        'title' => [
                            'az' => 'Məsuliyyət Sığortası',
                            'en' => 'Liability Insurance',
                            'ru' => 'Страхование ответственности'
                        ],
                        'slug' => 'mesuliyyet-sigortasi',
                        'url' => '/sirketler/sigorta/mesuliyyet-sigortasi',
                        'position' => 7,
                    ],
                    [
                        'title' => [
                            'az' => 'Bədbəxt Hadisələr Sığortası',
                            'en' => 'Accident Insurance',
                            'ru' => 'Страхование от несчастных случаев'
                        ],
                        'slug' => 'bedbext-hadiseler-sigortasi',
                        'url' => '/sirketler/sigorta/bedbext-hadiseler-sigortasi',
                        'position' => 8,
                    ],
                ],
            ],
            [
                'title' => [
                    'az' => 'Kripto',
                    'en' => 'Crypto',
                    'ru' => 'Крипто'
                ],
                'slug' => 'kripto',
                'url' => '/kripto',
                'position' => 5,
                'has_dropdown' => false,
                'is_active' => false,
                'menu_location' => 'header',
            ],
            [
                'title' => [
                    'az' => 'Xəbərlər',
                    'en' => 'News',
                    'ru' => 'Новости'
                ],
                'slug' => 'xeberler',
                'url' => '/xeberler',
                'position' => 6,
                'has_dropdown' => true,
                'is_active' => true,
                'menu_location' => 'header',
                'children' => [
                    [
                        'title' => [
                            'az' => 'Bütün xəbərlər',
                            'en' => 'All news',
                            'ru' => 'Все новости'
                        ],
                        'slug' => 'butun-xeberler',
                        'url' => '/xeberler',
                        'position' => 1,
                    ],
                    // News categories will be added dynamically
                ],
            ],
        ];

        // Footer menu items
        $footerMenus = [
            [
                'title' => [
                    'az' => 'Şirkətlər',
                    'en' => 'Companies',
                    'ru' => 'Компании'
                ],
                'slug' => 'sirketler-footer',
                'url' => '/sirketler',
                'position' => 1,
                'menu_location' => 'footer',
                'is_active' => true,
            ],
            [
                'title' => [
                    'az' => 'Haqqımızda',
                    'en' => 'About Us',
                    'ru' => 'О нас'
                ],
                'slug' => 'haqqimizda',
                'url' => '/haqqimizda',
                'position' => 2,
                'menu_location' => 'footer',
                'is_active' => true,
            ],
            [
                'title' => [
                    'az' => 'Əlaqə',
                    'en' => 'Contact',
                    'ru' => 'Контакты'
                ],
                'slug' => 'elaqe',
                'url' => '/elaqe',
                'position' => 3,
                'menu_location' => 'footer',
                'is_active' => true,
            ],
            [
                'title' => [
                    'az' => 'Gizlilik siyasəti',
                    'en' => 'Privacy Policy',
                    'ru' => 'Политика конфиденциальности'
                ],
                'slug' => 'gizlilik',
                'url' => '/gizlilik',
                'position' => 4,
                'menu_location' => 'footer',
                'is_active' => true,
            ],
            [
                'title' => [
                    'az' => 'İstifadə şərtləri',
                    'en' => 'Terms of Use',
                    'ru' => 'Условия использования'
                ],
                'slug' => 'sertler',
                'url' => '/sertler',
                'position' => 5,
                'menu_location' => 'footer',
                'is_active' => true,
            ],
        ];

        // Create main menus
        foreach ($menus as $menuData) {
            $children = $menuData['children'] ?? [];
            unset($menuData['children']);
            
            $menu = Menu::create($menuData);
            
            // Create children if exist
            foreach ($children as $childData) {
                $childData['parent_id'] = $menu->id;
                $childData['menu_location'] = 'header';
                $childData['is_active'] = true;
                Menu::create($childData);
            }
        }

        // Create footer menus
        foreach ($footerMenus as $footerMenu) {
            Menu::create($footerMenu);
        }
    }
}