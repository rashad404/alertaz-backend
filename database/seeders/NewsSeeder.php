<?php

namespace Database\Seeders;

use App\Models\News;
use App\Models\Category;
use App\Models\User;
use App\Helpers\UnsplashHelper;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Download images from Unsplash first
        echo "Downloading images from Unsplash...\n";
        $images = UnsplashHelper::downloadMultipleImages(15);
        
        if (empty($images)) {
            echo "Warning: Could not download images from Unsplash. Using fallback.\n";
            // Create fallback image list
            $images = [];
            for ($i = 1; $i <= 15; $i++) {
                $images[] = 'news-default.jpg';
            }
        } else {
            echo "Successfully downloaded " . count($images) . " images.\n";
        }
        
        $financeCategory = Category::where('slug', 'maliyye')->first();
        $creditCategory = Category::where('slug', 'kredit')->first();
        $bankNewsCategory = Category::where('slug', 'bank-xeberleri')->first();
        $economyCategory = Category::where('slug', 'iqtisadiyyat')->first();
        
        // Get users for author assignment
        $editor = User::where('role', 'editor')->first();
        $correspondent = User::where('role', 'correspondent')->first();
        $admin = User::where('role', 'admin')->first();

        // Create news in different languages
        $newsData = [
            // Azerbaijani news
            [
                'language' => 'az',
                'title' => 'Mərkəzi Bank uçot dərəcəsini 7.25%-ə endirib',
                'slug' => 'merkezi-bank-ucot-derecesini-725-e-endirib-az',
                'body' => '<p>Azərbaycan Mərkəzi Bankının İdarə Heyəti 2025-ci il 27 yanvar tarixində keçirilən növbəti iclasında uçot dərəcəsi barədə qərar qəbul edib.</p><p>İnflyasiya gözləntilərinin azalması və makroiqtisadi sabitliyin qorunması fonunda Mərkəzi Bank uçot dərəcəsini 0.25 faiz bəndi azaldaraq 7.25%-ə endirib.</p>',
                'category_id' => $financeCategory->id,
                'views' => 1523,
                'author' => 'Redaksiya',
                'author_id' => $editor ? $editor->id : null,
                'hashtags' => ['mərkəzibank', 'faizlər', 'maliyyə', 'iqtisadiyyat'],
                'thumbnail_image' => $images[0] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(2),
                'seo_title' => 'Mərkəzi Bank uçot dərəcəsini 7.25%-ə endirib',
                'seo_description' => 'Azərbaycan Mərkəzi Bankı uçot dərəcəsini 0.25 faiz bəndi azaldaraq 7.25%-ə endirib',
                'seo_keywords' => 'mərkəzi bank, uçot dərəcəsi, faiz dərəcəsi',
            ],
            // English version
            [
                'language' => 'en',
                'title' => 'Central Bank cuts discount rate to 7.25%',
                'slug' => 'central-bank-cuts-discount-rate-to-725-en',
                'body' => '<p>The Board of the Central Bank of Azerbaijan made a decision on the discount rate at its regular meeting held on January 27, 2025.</p><p>Against the background of declining inflation expectations and maintaining macroeconomic stability, the Central Bank reduced the discount rate by 0.25 percentage points to 7.25%.</p>',
                'category_id' => $financeCategory->id,
                'views' => 892,
                'author' => 'Editorial',
                'author_id' => $editor ? $editor->id : null,
                'hashtags' => ['centralbank', 'interestrates', 'finance', 'economy'],
                'thumbnail_image' => $images[0] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(2),
                'seo_title' => 'Central Bank cuts discount rate to 7.25%',
                'seo_description' => 'The Central Bank of Azerbaijan has reduced the discount rate by 0.25 percentage points to 7.25%',
                'seo_keywords' => 'central bank, discount rate, interest rate',
            ],
            // Russian version
            [
                'language' => 'ru',
                'title' => 'Центральный банк снизил учетную ставку до 7,25%',
                'slug' => 'tsentralniy-bank-snizil-uchetnuyu-stavku-do-725-ru',
                'body' => '<p>Правление Центрального банка Азербайджана приняло решение об учетной ставке на очередном заседании, состоявшемся 27 января 2025 года.</p><p>На фоне снижения инфляционных ожиданий и сохранения макроэкономической стабильности Центральный банк снизил учетную ставку на 0,25 процентных пункта до 7,25%.</p>',
                'category_id' => $financeCategory->id,
                'views' => 654,
                'author' => 'Редакция',
                'author_id' => $editor ? $editor->id : null,
                'hashtags' => ['центробанк', 'ставки', 'финансы'],
                'thumbnail_image' => $images[0] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(2),
                'seo_title' => 'Центральный банк снизил учетную ставку до 7,25%',
                'seo_description' => 'Центральный банк Азербайджана снизил учетную ставку на 0,25 процентных пункта до 7,25%',
                'seo_keywords' => 'центральный банк, учетная ставка, процентная ставка',
            ],
            // More Azerbaijani news with common hashtags
            [
                'language' => 'az',
                'title' => 'Kapital Bank yeni kredit kampaniyası elan edib',
                'slug' => 'kapital-bank-yeni-kredit-kampaniyasi-elan-edib',
                'body' => '<p>Kapital Bank müştəriləri üçün xüsusi kampaniya elan edib. Kampaniya çərçivəsində nağd kreditlər üzrə faiz dərəcələri əhəmiyyətli dərəcədə azaldılıb.</p><p>Yeni şərtlərə əsasən, 30,000 AZN-dək kreditlər üçün illik faiz dərəcəsi 11.99%-dən başlayır.</p>',
                'category_id' => $bankNewsCategory->id,
                'views' => 892,
                'author' => 'Elçin Məmmədov',
                'author_id' => $correspondent ? $correspondent->id : null,
                'hashtags' => ['kredit', 'faizlər', 'bankxəbərləri', 'maliyyə'],
                'thumbnail_image' => $images[1] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(5),
                'seo_title' => 'Kapital Bank kredit faizlərini endirib',
                'seo_description' => 'Kapital Bank nağd kredit faizlərini endirərək yeni kampaniyaya başlayıb',
                'seo_keywords' => 'kapital bank, kredit, faiz dərəcəsi, kampaniya',
            ],
            [
                'language' => 'az',
                'title' => 'İpoteka kreditləri üzrə yeni qaydalar qüvvəyə minib',
                'slug' => 'ipoteka-kreditleri-uzre-yeni-qaydalar-quvveye-minib',
                'body' => '<p>Azərbaycan Mərkəzi Bankı ipoteka kreditləri bazarında yeni tənzimləmə qaydalarını qüvvəyə mindirib. Yeni qaydalara əsasən, ipoteka kreditləri üzrə ilkin ödəniş minimum 15% təşkil edəcək.</p>',
                'category_id' => $creditCategory->id,
                'views' => 1205,
                'author' => 'Nigar Əliyeva',
                'author_id' => $correspondent ? $correspondent->id : null,
                'hashtags' => ['ipoteka', 'kredit', 'mərkəzibank', 'daşınmazemlak'],
                'thumbnail_image' => $images[2] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(7),
                'seo_title' => 'İpoteka kreditləri üzrə yeni qaydalar',
                'seo_description' => 'Mərkəzi Bank ipoteka kreditləri üzrə yeni tənzimləmə qaydalarını təsdiqləyib',
                'seo_keywords' => 'ipoteka, kredit, mərkəzi bank, qaydalar',
            ],
            [
                'language' => 'az',
                'title' => '2024-cü ildə bank sektorunun mənfəəti 1 milyard manatı keçib',
                'slug' => '2024-cu-ilde-bank-sektorunun-menfeeti-1-milyard-manati-kecib',
                'body' => '<p>Mərkəzi Bankın məlumatına görə, 2024-cü ildə Azərbaycan bank sektorunun xalis mənfəəti 1.05 milyard manat təşkil edib. Bu, 2023-cü illə müqayisədə 15% artım deməkdir.</p>',
                'category_id' => $economyCategory->id,
                'views' => 756,
                'author' => 'Rəşad Hüseynov',
                'author_id' => $correspondent ? $correspondent->id : null,
                'hashtags' => ['bankxəbərləri', 'maliyyə', 'iqtisadiyyat', 'illikhesabat'],
                'thumbnail_image' => $images[3] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(10),
                'seo_title' => 'Bank sektorunun mənfəəti rekord həddə çatıb',
                'seo_description' => 'Azərbaycan bank sektorunun 2024-cü il üzrə xalis mənfəəti 1 milyard manatı ötüb',
                'seo_keywords' => 'bank sektoru, mənfəət, iqtisadiyyat',
            ],
            [
                'language' => 'az',
                'title' => 'Onlayn kredit müraciətlərinin sayı 40% artıb',
                'slug' => 'onlayn-kredit-muracietlerinin-sayi-40-artib',
                'body' => '<p>Azərbaycan Banklar Assosiasiyasının məlumatına görə, 2024-cü ildə onlayn kredit müraciətlərinin sayı 40% artıb. Bu artım xüsusilə mobil tətbiqlər vasitəsilə edilən müraciətlərdə müşahidə olunub.</p>',
                'category_id' => $creditCategory->id,
                'views' => 623,
                'author' => 'Kənan Məmmədov',
                'author_id' => $correspondent ? $correspondent->id : null,
                'hashtags' => ['kredit', 'rəqəmsalbank', 'onlaynxidmətlər', 'maliyyə'],
                'thumbnail_image' => $images[4] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(14),
                'seo_title' => 'Onlayn kredit müraciətləri kəskin artıb',
                'seo_description' => 'Son bir ildə onlayn kredit müraciətlərinin sayında 40% artım qeydə alınıb',
                'seo_keywords' => 'onlayn kredit, rəqəmsal bankçılıq, kredit müraciəti',
            ],
            // English news
            [
                'language' => 'en',
                'title' => 'Kapital Bank announces new credit campaign',
                'slug' => 'kapital-bank-announces-new-credit-campaign',
                'body' => '<p>Kapital Bank has announced a special campaign for its customers. As part of the campaign, interest rates on cash loans have been significantly reduced.</p><p>According to the new terms, the annual interest rate for loans up to 30,000 AZN starts from 11.99%.</p>',
                'category_id' => $bankNewsCategory->id,
                'views' => 456,
                'author' => 'Elchin Mammadov',
                'hashtags' => ['credit', 'interestrates', 'banknews', 'finance'],
                'thumbnail_image' => $images[1] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(5),
                'seo_title' => 'Kapital Bank reduces credit interest rates',
                'seo_description' => 'Kapital Bank has launched a new campaign with reduced cash credit interest rates',
                'seo_keywords' => 'kapital bank, credit, interest rate, campaign',
            ],
            [
                'language' => 'en',
                'title' => 'New mortgage loan regulations come into force',
                'slug' => 'new-mortgage-loan-regulations-come-into-force',
                'body' => '<p>The Central Bank of Azerbaijan has enforced new regulatory rules in the mortgage lending market. According to the new rules, the minimum down payment for mortgage loans will be 15%.</p>',
                'category_id' => $creditCategory->id,
                'views' => 678,
                'author' => 'Nigar Aliyeva',
                'hashtags' => ['mortgage', 'credit', 'centralbank', 'realestate'],
                'thumbnail_image' => $images[2] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(7),
                'seo_title' => 'New regulations for mortgage loans',
                'seo_description' => 'Central Bank has approved new regulatory rules for mortgage loans',
                'seo_keywords' => 'mortgage, credit, central bank, regulations',
            ],
            // Russian news
            [
                'language' => 'ru',
                'title' => 'Kapital Bank объявил новую кредитную кампанию',
                'slug' => 'kapital-bank-obyavil-novuyu-kreditnuyu-kampaniyu',
                'body' => '<p>Kapital Bank объявил специальную кампанию для своих клиентов. В рамках кампании процентные ставки по наличным кредитам были значительно снижены.</p><p>Согласно новым условиям, годовая процентная ставка по кредитам до 30 000 манатов начинается с 11,99%.</p>',
                'category_id' => $bankNewsCategory->id,
                'views' => 321,
                'author' => 'Эльчин Мамедов',
                'hashtags' => ['кредит', 'ставки', 'банки', 'финансы'],
                'thumbnail_image' => $images[1] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(5),
                'seo_title' => 'Kapital Bank снизил процентные ставки по кредитам',
                'seo_description' => 'Kapital Bank начал новую кампанию со сниженными процентными ставками по наличным кредитам',
                'seo_keywords' => 'kapital bank, кредит, процентная ставка, кампания',
            ],
            [
                'language' => 'ru',
                'title' => 'Вступили в силу новые правила ипотечного кредитования',
                'slug' => 'vstupili-v-silu-novye-pravila-ipotechnogo-kreditovaniya',
                'body' => '<p>Центральный банк Азербайджана ввел в действие новые регулятивные правила на рынке ипотечного кредитования. Согласно новым правилам, минимальный первоначальный взнос по ипотечным кредитам составит 15%.</p>',
                'category_id' => $creditCategory->id,
                'views' => 432,
                'author' => 'Нигяр Алиева',
                'hashtags' => ['ипотека', 'кредит', 'цб', 'недвижимость'],
                'thumbnail_image' => $images[2] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(7),
                'seo_title' => 'Новые правила по ипотечным кредитам',
                'seo_description' => 'Центральный банк утвердил новые регулятивные правила по ипотечным кредитам',
                'seo_keywords' => 'ипотека, кредит, центральный банк, правила',
            ],
            // Additional Azerbaijani news with overlapping hashtags
            [
                'language' => 'az',
                'title' => 'Mərkəzi Bank kredit portfelinin artımını açıqlayıb',
                'slug' => 'merkezi-bank-kredit-portfelinin-artimini-aciqlayib',
                'body' => '<p>Mərkəzi Bankın hesabatına görə, 2024-cü ildə ümumi kredit portfeli 20% artaraq 18 milyard manata çatıb. Artım əsasən biznes kreditləri və ipoteka kreditləri seqmentində müşahidə edilib.</p>',
                'category_id' => $creditCategory->id,
                'views' => 945,
                'author' => 'Səbinə Əliyeva',
                'hashtags' => ['kredit', 'mərkəzibank', 'maliyyə', 'kreditportfeli'],
                'thumbnail_image' => $images[5] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(3),
                'seo_title' => 'Kredit portfeli 18 milyard manata çatıb',
                'seo_description' => 'Mərkəzi Bank 2024-cü ildə kredit portfelinin 20% artdığını açıqlayıb',
                'seo_keywords' => 'kredit portfeli, mərkəzi bank, maliyyə',
            ],
            [
                'language' => 'az',
                'title' => 'İpoteka kreditləri üzrə faizlər aşağı düşüb',
                'slug' => 'ipoteka-kreditleri-uzre-faizler-asagi-dusub',
                'body' => '<p>Bank sektorunda ipoteka kreditləri üzrə orta faiz dərəcəsi 8.5%-ə enib. Ekspertlər bu trendin davam edəcəyini proqnozlaşdırırlar.</p>',
                'category_id' => $creditCategory->id,
                'views' => 1102,
                'author' => 'Rəşad Hüseynov',
                'hashtags' => ['ipoteka', 'faizlər', 'kredit', 'daşınmazemlak'],
                'thumbnail_image' => $images[6] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(4),
                'seo_title' => 'İpoteka faizləri tarixi minimuma yaxınlaşır',
                'seo_description' => 'İpoteka kreditləri üzrə orta faiz dərəcəsi 8.5%-ə enib',
                'seo_keywords' => 'ipoteka, faiz dərəcəsi, kredit',
            ],
            [
                'language' => 'az',
                'title' => 'Banklararası faiz dərəcələri sabitləşib',
                'slug' => 'banklararasi-faiz-dereceleri-sabitlesib',
                'body' => '<p>Azərbaycan banklararası pul bazarında faiz dərəcələri son həftələrdə sabitləşib. AZIBOR göstəricisi 7.8% səviyyəsində qərarlaşıb.</p>',
                'category_id' => $financeCategory->id,
                'views' => 567,
                'author' => 'Kamran Əzizov',
                'hashtags' => ['faizlər', 'mərkəzibank', 'maliyyə', 'AZIBOR'],
                'thumbnail_image' => $images[7] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(6),
                'seo_title' => 'AZIBOR göstəricisi 7.8% səviyyəsində',
                'seo_description' => 'Banklararası pul bazarında faiz dərəcələri sabitləşib',
                'seo_keywords' => 'AZIBOR, faiz dərəcəsi, banklararası bazar',
            ],
            [
                'language' => 'az',
                'title' => 'Rəqəmsal bankçılıq xidmətləri genişlənir',
                'slug' => 'reqemsal-bankcilik-xidmetleri-genislenir',
                'body' => '<p>Azərbaycanda rəqəmsal bankçılıq xidmətlərindən istifadə edənlərin sayı 2.5 milyon nəfəri ötüb. Onlayn kredit müraciətləri ümumi müraciətlərin 60%-ni təşkil edir.</p>',
                'category_id' => $bankNewsCategory->id,
                'views' => 789,
                'author' => 'Aysel Məmmədova',
                'hashtags' => ['rəqəmsalbank', 'onlaynxidmətlər', 'kredit', 'bankxəbərləri'],
                'thumbnail_image' => $images[8] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(8),
                'seo_title' => 'Rəqəmsal bankçılıqda böyük artım',
                'seo_description' => 'Rəqəmsal bankçılıq xidmətlərindən istifadə edənlərin sayı 2.5 milyonu keçib',
                'seo_keywords' => 'rəqəmsal bankçılıq, onlayn xidmətlər, kredit',
            ],
            [
                'language' => 'az',
                'title' => 'Yeni il kampaniyaları: Banklar faizləri endirir',
                'slug' => 'yeni-il-kampaniyalari-banklar-faizleri-endirir',
                'body' => '<p>Yeni il münasibətilə əksər banklar kredit faizlərində endirimlər edib. Kampaniyalar yanvar ayının sonunadək davam edəcək.</p>',
                'category_id' => $bankNewsCategory->id,
                'views' => 1456,
                'author' => 'Elçin Məmmədov',
                'hashtags' => ['faizlər', 'kredit', 'bankxəbərləri', 'kampaniya'],
                'thumbnail_image' => $images[9] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(1),
                'seo_title' => 'Banklar yeni il kampaniyaları elan edib',
                'seo_description' => 'Yeni il münasibətilə banklarda kredit faizləri endirilib',
                'seo_keywords' => 'yeni il kampaniyası, kredit, faiz endirimi',
            ],
            // Additional English news with overlapping hashtags
            [
                'language' => 'en',
                'title' => 'Central Bank reports credit portfolio growth',
                'slug' => 'central-bank-reports-credit-portfolio-growth',
                'body' => '<p>According to the Central Bank report, the total credit portfolio grew by 20% in 2024, reaching 18 billion manats. Growth was mainly observed in business loans and mortgage segments.</p>',
                'category_id' => $creditCategory->id,
                'views' => 534,
                'author' => 'Sabina Aliyeva',
                'hashtags' => ['credit', 'centralbank', 'finance', 'creditportfolio'],
                'thumbnail_image' => $images[5] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(3),
                'seo_title' => 'Credit portfolio reaches 18 billion manats',
                'seo_description' => 'Central Bank announces 20% growth in credit portfolio for 2024',
                'seo_keywords' => 'credit portfolio, central bank, finance',
            ],
            [
                'language' => 'en',
                'title' => 'Mortgage interest rates decline',
                'slug' => 'mortgage-interest-rates-decline',
                'body' => '<p>The average interest rate for mortgage loans in the banking sector has decreased to 8.5%. Experts predict this trend will continue.</p>',
                'category_id' => $creditCategory->id,
                'views' => 678,
                'author' => 'Rashad Huseynov',
                'hashtags' => ['mortgage', 'interestrates', 'credit', 'realestate'],
                'thumbnail_image' => $images[6] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(4),
                'seo_title' => 'Mortgage rates approaching historic lows',
                'seo_description' => 'Average mortgage interest rate drops to 8.5%',
                'seo_keywords' => 'mortgage, interest rate, credit',
            ],
            [
                'language' => 'en',
                'title' => 'Digital banking services expand rapidly',
                'slug' => 'digital-banking-services-expand-rapidly',
                'body' => '<p>The number of digital banking users in Azerbaijan has exceeded 2.5 million. Online credit applications now account for 60% of all applications.</p>',
                'category_id' => $bankNewsCategory->id,
                'views' => 445,
                'author' => 'Aysel Mammadova',
                'hashtags' => ['digitalbanking', 'onlineservices', 'credit', 'banknews'],
                'thumbnail_image' => $images[8] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(8),
                'seo_title' => 'Major growth in digital banking',
                'seo_description' => 'Digital banking users exceed 2.5 million in Azerbaijan',
                'seo_keywords' => 'digital banking, online services, credit',
            ],
            // Additional Russian news with overlapping hashtags
            [
                'language' => 'ru',
                'title' => 'Центробанк сообщил о росте кредитного портфеля',
                'slug' => 'tsentrobank-soobshil-o-roste-kreditnogo-portfelya',
                'body' => '<p>Согласно отчету Центрального банка, общий кредитный портфель вырос на 20% в 2024 году, достигнув 18 миллиардов манатов. Рост в основном наблюдался в сегментах бизнес-кредитов и ипотеки.</p>',
                'category_id' => $creditCategory->id,
                'views' => 389,
                'author' => 'Сабина Алиева',
                'hashtags' => ['кредит', 'цб', 'финансы', 'портфель'],
                'thumbnail_image' => $images[5] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(3),
                'seo_title' => 'Кредитный портфель достиг 18 миллиардов манатов',
                'seo_description' => 'Центробанк объявил о росте кредитного портфеля на 20% в 2024 году',
                'seo_keywords' => 'кредитный портфель, центральный банк, финансы',
            ],
            [
                'language' => 'ru',
                'title' => 'Ставки по ипотечным кредитам снижаются',
                'slug' => 'stavki-po-ipotechnym-kreditam-snizhayutsya',
                'body' => '<p>Средняя процентная ставка по ипотечным кредитам в банковском секторе снизилась до 8,5%. Эксперты прогнозируют продолжение этой тенденции.</p>',
                'category_id' => $creditCategory->id,
                'views' => 412,
                'author' => 'Рашад Гусейнов',
                'hashtags' => ['ипотека', 'ставки', 'кредит'],
                'thumbnail_image' => $images[6] ?? 'news-default.jpg',
                'status' => true,
                'publish_date' => now()->subDays(4),
                'seo_title' => 'Ипотечные ставки приближаются к историческим минимумам',
                'seo_description' => 'Средняя процентная ставка по ипотеке снизилась до 8,5%',
                'seo_keywords' => 'ипотека, процентная ставка, кредит',
            ],
        ];

        foreach ($newsData as $item) {
            News::create($item);
        }
        
        echo "News seeding completed with images.\n";
    }
}