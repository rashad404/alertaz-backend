<?php

namespace Database\Seeders;

use App\Models\MetaTag;
use Illuminate\Database\Seeder;

class MetaTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metaTags = [
            [
                'seo_title' => 'Kredit.az - Azərbaycanda kredit müqayisə platforması',
                'seo_description' => 'Azərbaycanda bütün bankların kredit təkliflərini müqayisə edin. Ən sərfəli kredit şərtləri, onlayn müraciət, kredit kalkulyatoru.',
                'seo_keywords' => 'kredit, bank krediti, onlayn kredit, kredit müqayisəsi, kredit kalkulyatoru',
                'code' => 'home',
            ],
            [
                'seo_title' => 'Kredit təklifləri - Bütün bankların kreditləri',
                'seo_description' => 'Azərbaycan banklarının bütün kredit təklifləri bir yerdə. Nağd kredit, ipoteka, avtomobil krediti və digər kredit növləri.',
                'seo_keywords' => 'kredit təklifləri, bank kreditləri, nağd kredit, ipoteka, avtomobil krediti',
                'code' => 'offers',
            ],
            [
                'seo_title' => 'Kredit kalkulyatoru - Aylıq ödənişi hesablayın',
                'seo_description' => 'Onlayn kredit kalkulyatoru ilə aylıq ödənişinizi hesablayın. Faiz dərəcəsi, kredit müddəti və məbləğə görə dəqiq hesablama.',
                'seo_keywords' => 'kredit kalkulyatoru, aylıq ödəniş hesablama, faiz hesablama',
                'code' => 'calculator',
            ],
            [
                'seo_title' => 'Maliyyə xəbərləri - Bank və kredit xəbərləri',
                'seo_description' => 'Azərbaycanın maliyyə bazarından ən son xəbərlər. Bank xəbərləri, kredit kampaniyaları, faiz dərəcələri haqqında məlumatlar.',
                'seo_keywords' => 'maliyyə xəbərləri, bank xəbərləri, kredit xəbərləri, faiz dərəcələri',
                'code' => 'news',
            ],
            [
                'seo_title' => 'Banklar və kredit təşkilatları - Tam siyahı',
                'seo_description' => 'Azərbaycanda fəaliyyət göstərən bütün banklar və kredit təşkilatlarının siyahısı. Əlaqə məlumatları və filiallar.',
                'seo_keywords' => 'banklar, kredit təşkilatları, bank siyahısı, bank əlaqə',
                'code' => 'companies',
            ],
        ];

        foreach ($metaTags as $metaTag) {
            MetaTag::create($metaTag);
        }
    }
}