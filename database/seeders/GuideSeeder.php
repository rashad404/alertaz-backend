<?php

namespace Database\Seeders;

use App\Models\Guide;
use Illuminate\Database\Seeder;

class GuideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guides = [
            // Loans Category
            [
                'slug' => 'personal-loan-application',
                'title' => [
                    'az' => 'Şəxsi kredit üçün necə müraciət etmək olar',
                    'en' => 'How to Apply for a Personal Loan',
                    'ru' => 'Как подать заявку на личный кредит'
                ],
                'description' => [
                    'az' => 'Azərbaycanda şəxsi kredit almaq üçün tam bələdçi',
                    'en' => 'Complete guide to applying for a personal loan in Azerbaijan',
                    'ru' => 'Полное руководство по подаче заявки на личный кредит в Азербайджане'
                ],
                'content' => [
                    'az' => '## Addım 1: Kredit ehtiyacınızı müəyyənləşdirin\n\nİlk öncə nə qədər pula ehtiyacınız olduğunu və onu nə üçün istifadə edəcəyinizi müəyyən edin...\n\n## Addım 2: Kredit reytinqinizi yoxlayın\n\nKredit müraciətindən əvvəl kredit tarixçənizi yoxlamaq vacibdir...\n\n## Addım 3: Bankları müqayisə edin\n\nMüxtəlif bankların təkliflərini müqayisə edərək sizə ən uyğun olanı seçin...',
                    'en' => '## Step 1: Determine Your Loan Need\n\nFirst, determine how much money you need and what you will use it for...\n\n## Step 2: Check Your Credit Score\n\nIt is important to check your credit history before applying for a loan...\n\n## Step 3: Compare Banks\n\nCompare offers from different banks and choose the most suitable one for you...',
                    'ru' => '## Шаг 1: Определите вашу потребность в кредите\n\nСначала определите, сколько денег вам нужно и для чего вы будете их использовать...\n\n## Шаг 2: Проверьте свой кредитный рейтинг\n\nВажно проверить свою кредитную историю перед подачей заявки на кредит...\n\n## Шаг 3: Сравните банки\n\nСравните предложения разных банков и выберите наиболее подходящее для вас...'
                ],
                'category' => 'loans',
                'read_time' => 10,
                'difficulty' => 'beginner',
                'views' => rand(1000, 15000),
                'is_featured' => false,
                'order' => 1,
            ],
            [
                'slug' => 'choosing-right-mortgage',
                'title' => [
                    'az' => 'Düzgün ipoteka seçimi',
                    'en' => 'Choosing the Right Mortgage',
                    'ru' => 'Выбор правильной ипотеки'
                ],
                'description' => [
                    'az' => 'İpoteka variantlarını müqayisə edin və ən yaxşı təklifi tapın',
                    'en' => 'Compare mortgage options and find the best deal',
                    'ru' => 'Сравните варианты ипотеки и найдите лучшее предложение'
                ],
                'content' => [
                    'az' => '## İpoteka növləri\n\nAzərbaycanda müxtəlif ipoteka növləri mövcuddur...\n\n## Faiz dərəcələri\n\nSabit və dəyişkən faiz dərəcələri arasındakı fərq...\n\n## İlkin ödəniş\n\nİpoteka üçün tələb olunan minimum ilkin ödəniş məbləği...',
                    'en' => '## Types of Mortgages\n\nThere are different types of mortgages available in Azerbaijan...\n\n## Interest Rates\n\nThe difference between fixed and variable interest rates...\n\n## Down Payment\n\nMinimum down payment amount required for a mortgage...',
                    'ru' => '## Виды ипотеки\n\nВ Азербайджане доступны различные виды ипотеки...\n\n## Процентные ставки\n\nРазница между фиксированными и переменными процентными ставками...\n\n## Первоначальный взнос\n\nМинимальная сумма первоначального взноса для ипотеки...'
                ],
                'category' => 'loans',
                'read_time' => 15,
                'difficulty' => 'intermediate',
                'views' => rand(1000, 15000),
                'is_featured' => true,
                'order' => 2,
            ],
            [
                'slug' => 'business-loan-guide',
                'title' => [
                    'az' => 'Biznes krediti almaq',
                    'en' => 'Getting a Business Loan',
                    'ru' => 'Получение бизнес-кредита'
                ],
                'description' => [
                    'az' => 'Biznes maliyyələşməsi üçün tələblər və proses',
                    'en' => 'Requirements and process for business financing',
                    'ru' => 'Требования и процесс для бизнес-финансирования'
                ],
                'content' => [
                    'az' => '## Biznes plan hazırlığı\n\nBanklar üçün peşəkar biznes plan necə hazırlanır...\n\n## Maliyyə sənədləri\n\nTələb olunan maliyyə hesabatları və sənədlər...\n\n## Təminat\n\nBiznes krediti üçün təminat növləri...',
                    'en' => '## Business Plan Preparation\n\nHow to prepare a professional business plan for banks...\n\n## Financial Documents\n\nRequired financial statements and documents...\n\n## Collateral\n\nTypes of collateral for business loans...',
                    'ru' => '## Подготовка бизнес-плана\n\nКак подготовить профессиональный бизнес-план для банков...\n\n## Финансовые документы\n\nНеобходимые финансовые отчеты и документы...\n\n## Залог\n\nВиды залога для бизнес-кредитов...'
                ],
                'category' => 'loans',
                'read_time' => 20,
                'difficulty' => 'advanced',
                'views' => rand(1000, 15000),
                'is_featured' => false,
                'order' => 3,
            ],

            // Insurance Category
            [
                'slug' => 'auto-insurance-guide',
                'title' => [
                    'az' => 'Avtomobil sığortası bələdçisi',
                    'en' => 'Auto Insurance Guide',
                    'ru' => 'Руководство по автострахованию'
                ],
                'description' => [
                    'az' => 'Azərbaycanda avtomobil sığortası haqqında hər şey',
                    'en' => 'Everything about car insurance in Azerbaijan',
                    'ru' => 'Все об автостраховании в Азербайджане'
                ],
                'content' => [
                    'az' => '## İcbari sığorta\n\nİcbari avtomobil sığortası haqqında bilməli olduqlarınız...\n\n## Könüllü sığorta (KASKO)\n\nKASKO sığortasının üstünlükləri və əhatə dairəsi...\n\n## Sığorta hadisəsi\n\nQəza baş verdikdə nə etməli...',
                    'en' => '## Mandatory Insurance\n\nWhat you need to know about mandatory car insurance...\n\n## Voluntary Insurance (KASKO)\n\nAdvantages and coverage of KASKO insurance...\n\n## Insurance Incident\n\nWhat to do when an accident occurs...',
                    'ru' => '## Обязательное страхование\n\nЧто нужно знать об обязательном автостраховании...\n\n## Добровольное страхование (КАСКО)\n\nПреимущества и покрытие страхования КАСКО...\n\n## Страховой случай\n\nЧто делать при наступлении ДТП...'
                ],
                'category' => 'insurance',
                'read_time' => 8,
                'difficulty' => 'beginner',
                'views' => rand(1000, 15000),
                'is_featured' => false,
                'order' => 4,
            ],
            [
                'slug' => 'health-insurance-explained',
                'title' => [
                    'az' => 'Tibbi sığorta izahı',
                    'en' => 'Health Insurance Explained',
                    'ru' => 'Объяснение медицинского страхования'
                ],
                'description' => [
                    'az' => 'Tibbi sığorta əhatəsi və faydaları haqqında',
                    'en' => 'Understanding health insurance coverage and benefits',
                    'ru' => 'Понимание покрытия и преимуществ медицинского страхования'
                ],
                'content' => [
                    'az' => '## Tibbi sığorta növləri\n\nFərdi və korporativ tibbi sığorta...\n\n## Əhatə dairəsi\n\nTibbi sığorta nəyi əhatə edir...\n\n## İstisna hallar\n\nTibbi sığortanın əhatə etmədiyi hallar...',
                    'en' => '## Types of Health Insurance\n\nIndividual and corporate health insurance...\n\n## Coverage\n\nWhat health insurance covers...\n\n## Exclusions\n\nCases not covered by health insurance...',
                    'ru' => '## Виды медицинского страхования\n\nИндивидуальное и корпоративное медицинское страхование...\n\n## Покрытие\n\nЧто покрывает медицинское страхование...\n\n## Исключения\n\nСлучаи, не покрываемые медицинским страхованием...'
                ],
                'category' => 'insurance',
                'read_time' => 12,
                'difficulty' => 'intermediate',
                'views' => rand(1000, 15000),
                'is_featured' => false,
                'order' => 5,
            ],

            // Banking Category
            [
                'slug' => 'opening-bank-account',
                'title' => [
                    'az' => 'Bank hesabı açmaq',
                    'en' => 'Opening a Bank Account',
                    'ru' => 'Открытие банковского счета'
                ],
                'description' => [
                    'az' => 'İlk bank hesabınızı açmaq üçün addım-addım bələdçi',
                    'en' => 'Step-by-step guide to opening your first bank account',
                    'ru' => 'Пошаговое руководство по открытию вашего первого банковского счета'
                ],
                'content' => [
                    'az' => '## Tələb olunan sənədlər\n\nBank hesabı açmaq üçün lazım olan sənədlər...\n\n## Hesab növləri\n\nCari hesab, əmanət hesabı və digər növlər...\n\n## Onlayn müraciət\n\nOnlayn bank hesabı necə açılır...',
                    'en' => '## Required Documents\n\nDocuments needed to open a bank account...\n\n## Account Types\n\nCurrent account, savings account and other types...\n\n## Online Application\n\nHow to open a bank account online...',
                    'ru' => '## Необходимые документы\n\nДокументы, необходимые для открытия банковского счета...\n\n## Типы счетов\n\nТекущий счет, сберегательный счет и другие виды...\n\n## Онлайн-заявка\n\nКак открыть банковский счет онлайн...'
                ],
                'category' => 'banking',
                'read_time' => 5,
                'difficulty' => 'beginner',
                'views' => rand(1000, 15000),
                'is_featured' => false,
                'order' => 6,
            ],
            [
                'slug' => 'online-banking-security',
                'title' => [
                    'az' => 'Onlayn bankçılıqda təhlükəsizlik',
                    'en' => 'Using Online Banking Safely',
                    'ru' => 'Безопасное использование онлайн-банкинга'
                ],
                'description' => [
                    'az' => 'Onlayn və mobil bankçılıq üçün təhlükəsizlik məsləhətləri',
                    'en' => 'Security tips for online and mobile banking',
                    'ru' => 'Советы по безопасности для онлайн и мобильного банкинга'
                ],
                'content' => [
                    'az' => '## Güclü parol\n\nTəhlükəsiz parol necə yaradılır...\n\n## İki faktorlu autentifikasiya\n\n2FA-nın əhəmiyyəti və qurulması...\n\n## Fişinq hücumları\n\nFişinq cəhdlərini necə tanımaq olar...',
                    'en' => '## Strong Password\n\nHow to create a secure password...\n\n## Two-Factor Authentication\n\nImportance and setup of 2FA...\n\n## Phishing Attacks\n\nHow to recognize phishing attempts...',
                    'ru' => '## Надежный пароль\n\nКак создать безопасный пароль...\n\n## Двухфакторная аутентификация\n\nВажность и настройка 2FA...\n\n## Фишинговые атаки\n\nКак распознать попытки фишинга...'
                ],
                'category' => 'banking',
                'read_time' => 7,
                'difficulty' => 'beginner',
                'views' => rand(1000, 15000),
                'is_featured' => true,
                'order' => 7,
            ],

            // Popular guides (different categories)
            [
                'slug' => 'understanding-credit-score',
                'title' => [
                    'az' => 'Kredit reytinqini anlamaq',
                    'en' => 'Understanding Your Credit Score',
                    'ru' => 'Понимание вашего кредитного рейтинга'
                ],
                'description' => [
                    'az' => 'Kredit reytinqi nədir və necə yaxşılaşdırılır',
                    'en' => 'What is credit score and how to improve it',
                    'ru' => 'Что такое кредитный рейтинг и как его улучшить'
                ],
                'content' => [
                    'az' => '## Kredit reytinqi nədir?\n\nKredit reytinqi sizin maliyyə etibarlılığınızın göstəricisidir...\n\n## Reytinqə təsir edən amillər\n\nÖdəniş tarixçəsi, borc yükü və digər amillər...\n\n## Reytinqi yaxşılaşdırmaq\n\nKredit reytinqini yüksəltmək üçün praktiki addımlar...',
                    'en' => '## What is Credit Score?\n\nCredit score is an indicator of your financial reliability...\n\n## Factors Affecting Score\n\nPayment history, debt burden and other factors...\n\n## Improving Your Score\n\nPractical steps to improve your credit score...',
                    'ru' => '## Что такое кредитный рейтинг?\n\nКредитный рейтинг - это показатель вашей финансовой надежности...\n\n## Факторы, влияющие на рейтинг\n\nИстория платежей, долговая нагрузка и другие факторы...\n\n## Улучшение рейтинга\n\nПрактические шаги для повышения кредитного рейтинга...'
                ],
                'category' => 'loans',
                'read_time' => 10,
                'difficulty' => 'intermediate',
                'views' => 12500,
                'is_featured' => true,
                'order' => 8,
            ],
            [
                'slug' => 'loan-calculator-guide',
                'title' => [
                    'az' => 'Kredit kalkulyatorundan istifadə',
                    'en' => 'How to Use Loan Calculators',
                    'ru' => 'Как использовать кредитные калькуляторы'
                ],
                'description' => [
                    'az' => 'Kredit kalkulyatorları ilə ödənişləri hesablamaq',
                    'en' => 'Calculate payments with loan calculators',
                    'ru' => 'Расчет платежей с помощью кредитных калькуляторов'
                ],
                'content' => [
                    'az' => '## Kalkulyator növləri\n\nMüxtəlif kredit kalkulyatorları və onların istifadəsi...\n\n## Annuitet və diferensial ödənişlər\n\nÖdəniş növləri arasındakı fərq...\n\n## Ümumi xərclər\n\nKreditin real dəyərini necə hesablamaq olar...',
                    'en' => '## Types of Calculators\n\nDifferent loan calculators and their usage...\n\n## Annuity and Differential Payments\n\nDifference between payment types...\n\n## Total Costs\n\nHow to calculate the real cost of a loan...',
                    'ru' => '## Типы калькуляторов\n\nРазличные кредитные калькуляторы и их использование...\n\n## Аннуитетные и дифференциальные платежи\n\nРазница между типами платежей...\n\n## Общие расходы\n\nКак рассчитать реальную стоимость кредита...'
                ],
                'category' => 'loans',
                'read_time' => 8,
                'difficulty' => 'beginner',
                'views' => 8300,
                'is_featured' => false,
                'order' => 9,
            ],
            [
                'slug' => 'comparing-credit-offers',
                'title' => [
                    'az' => 'Kredit təkliflərini müqayisə etmək',
                    'en' => 'Comparing Credit Offers',
                    'ru' => 'Сравнение кредитных предложений'
                ],
                'description' => [
                    'az' => 'Ən yaxşı kredit təklifini necə seçmək olar',
                    'en' => 'How to choose the best credit offer',
                    'ru' => 'Как выбрать лучшее кредитное предложение'
                ],
                'content' => [
                    'az' => '## Müqayisə meyarları\n\nFaiz dərəcəsi, müddət, komissiyalar...\n\n## Gizli xərclər\n\nDiqqət edilməli gizli ödənişlər...\n\n## Erkən ödəmə\n\nErkən ödəmə şərtləri və cərimələr...',
                    'en' => '## Comparison Criteria\n\nInterest rate, term, commissions...\n\n## Hidden Costs\n\nHidden fees to watch out for...\n\n## Early Repayment\n\nEarly repayment terms and penalties...',
                    'ru' => '## Критерии сравнения\n\nПроцентная ставка, срок, комиссии...\n\n## Скрытые расходы\n\nСкрытые платежи, на которые следует обратить внимание...\n\n## Досрочное погашение\n\nУсловия и штрафы за досрочное погашение...'
                ],
                'category' => 'loans',
                'read_time' => 10,
                'difficulty' => 'intermediate',
                'views' => 6700,
                'is_featured' => false,
                'order' => 10,
            ],
            [
                'slug' => 'avoiding-financial-scams',
                'title' => [
                    'az' => 'Maliyyə fırıldaqlarından qaçmaq',
                    'en' => 'Avoiding Financial Scams',
                    'ru' => 'Избежание финансовых мошенничеств'
                ],
                'description' => [
                    'az' => 'Özünüzü maliyyə fırıldaqlarından necə qoruyasınız',
                    'en' => 'How to protect yourself from financial scams',
                    'ru' => 'Как защитить себя от финансового мошенничества'
                ],
                'content' => [
                    'az' => '## Ümumi fırıldaq sxemləri\n\nTez-tez rast gəlinən fırıldaq növləri...\n\n## Xəbərdarlıq işarələri\n\nFırıldağı necə tanımaq olar...\n\n## Özünüzü qoruyun\n\nTəhlükəsizlik tədbirləri və tövsiyələr...',
                    'en' => '## Common Scam Schemes\n\nFrequently encountered types of scams...\n\n## Warning Signs\n\nHow to recognize a scam...\n\n## Protect Yourself\n\nSecurity measures and recommendations...',
                    'ru' => '## Распространенные схемы мошенничества\n\nЧасто встречающиеся виды мошенничества...\n\n## Предупреждающие знаки\n\nКак распознать мошенничество...\n\n## Защитите себя\n\nМеры безопасности и рекомендации...'
                ],
                'category' => 'banking',
                'read_time' => 6,
                'difficulty' => 'beginner',
                'views' => 5200,
                'is_featured' => false,
                'order' => 11,
            ],
        ];

        foreach ($guides as $guide) {
            Guide::updateOrCreate(
                ['slug' => $guide['slug']],
                $guide
            );
        }
    }
}