<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blogs = [
            [
                'title' => [
                    'az' => 'Kredit götürməzdən əvvəl nələri bilmək lazımdır?',
                    'en' => 'What You Need to Know Before Taking a Loan',
                    'ru' => 'Что нужно знать перед получением кредита'
                ],
                'slug' => 'kredit-goturmezden-evvel',
                'excerpt' => [
                    'az' => 'Kredit götürməzdən əvvəl maliyyə vəziyyətinizi qiymətləndirin və müxtəlif kredit təkliflərini müqayisə edin.',
                    'en' => 'Assess your financial situation and compare different loan offers before taking a loan.',
                    'ru' => 'Оцените свое финансовое положение и сравните различные кредитные предложения перед получением кредита.'
                ],
                'content' => [
                    'az' => '<h2>Kredit Götürməzdən Əvvəl Diqqət Edilməli Məqamlar</h2>
                    <p>Kredit götürmək böyük maliyyə məsuliyyətidir. Bu qərarı verməzdən əvvəl aşağıdakı məsələləri nəzərdən keçirin:</p>
                    <h3>1. Maliyyə Vəziyyətinizi Qiymətləndirin</h3>
                    <p>İlk öncə aylıq gəlir və xərclərinizi hesablayın. Kredit ödənişi üçün büdcənizdə kifayət qədər yer var mı?</p>
                    <h3>2. Kredit Tarixçənizi Yoxlayın</h3>
                    <p>Kredit tarixçəniz faiz dərəcənizə təsir edəcək. Təmiz kredit tarixçəsi daha yaxşı şərtlər deməkdir.</p>
                    <h3>3. Faiz Dərəcələrini Müqayisə Edin</h3>
                    <p>Müxtəlif bankların təkliflərini müqayisə edin. Kiçik faiz fərqi belə böyük məbləğdə ciddi fərq yarada bilər.</p>
                    <h3>4. Gizli Xərcləri Araşdırın</h3>
                    <p>Komissiyalar, sığorta və digər əlavə xərclər ümumi məbləği artıra bilər.</p>
                    <h3>5. Erkən Ödəmə Şərtlərini Öyrənin</h3>
                    <p>Bəzi banklar erkən ödəmə üçün cərimə tətbiq edir. Bu şərtləri əvvəlcədən öyrənin.</p>',
                    'en' => '<h2>Important Points Before Taking a Loan</h2>
                    <p>Taking a loan is a significant financial responsibility. Consider the following before making this decision:</p>
                    <h3>1. Assess Your Financial Situation</h3>
                    <p>First, calculate your monthly income and expenses. Is there enough room in your budget for loan payments?</p>
                    <h3>2. Check Your Credit History</h3>
                    <p>Your credit history will affect your interest rate. A clean credit history means better terms.</p>
                    <h3>3. Compare Interest Rates</h3>
                    <p>Compare offers from different banks. Even a small difference in interest can make a big difference in large amounts.</p>
                    <h3>4. Research Hidden Costs</h3>
                    <p>Commissions, insurance, and other additional costs can increase the total amount.</p>
                    <h3>5. Learn Early Payment Terms</h3>
                    <p>Some banks apply penalties for early payment. Learn these terms in advance.</p>',
                    'ru' => '<h2>Важные моменты перед получением кредита</h2>
                    <p>Получение кредита - это серьезная финансовая ответственность. Рассмотрите следующее перед принятием решения:</p>
                    <h3>1. Оцените свое финансовое положение</h3>
                    <p>Сначала рассчитайте свои ежемесячные доходы и расходы. Достаточно ли места в вашем бюджете для кредитных платежей?</p>
                    <h3>2. Проверьте свою кредитную историю</h3>
                    <p>Ваша кредитная история повлияет на процентную ставку. Чистая кредитная история означает лучшие условия.</p>
                    <h3>3. Сравните процентные ставки</h3>
                    <p>Сравните предложения разных банков. Даже небольшая разница в процентах может иметь большое значение при больших суммах.</p>
                    <h3>4. Изучите скрытые расходы</h3>
                    <p>Комиссии, страхование и другие дополнительные расходы могут увеличить общую сумму.</p>
                    <h3>5. Узнайте условия досрочного погашения</h3>
                    <p>Некоторые банки применяют штрафы за досрочное погашение. Узнайте эти условия заранее.</p>'
                ],
                'author' => 'Kredit.az Team',
                'tags' => ['kredit', 'maliyyə', 'məsləhət'],
                'reading_time' => 5,
                'featured' => true,
                'status' => true,
                'published_at' => now()->subDays(5),
                'seo_title' => [
                    'az' => 'Kredit Götürmək Haqqında Məsləhətlər',
                    'en' => 'Loan Taking Advice',
                    'ru' => 'Советы по получению кредита'
                ],
                'seo_keywords' => [
                    'az' => 'kredit, bank krediti, kredit məsləhəti',
                    'en' => 'loan, bank loan, loan advice',
                    'ru' => 'кредит, банковский кредит, советы по кредиту'
                ],
                'seo_description' => [
                    'az' => 'Kredit götürməzdən əvvəl bilməli olduğunuz vacib məsləhətlər',
                    'en' => 'Important advice you should know before taking a loan',
                    'ru' => 'Важные советы, которые нужно знать перед получением кредита'
                ]
            ],
            [
                'title' => [
                    'az' => 'Depozit Seçərkən Nələrə Diqqət Etməli?',
                    'en' => 'What to Consider When Choosing a Deposit',
                    'ru' => 'На что обратить внимание при выборе депозита'
                ],
                'slug' => 'depozit-secerken-diqqet',
                'excerpt' => [
                    'az' => 'Depozit seçimi zamanı faiz dərəcəsi, müddət və şərtlər düzgün qiymətləndirilməlidir.',
                    'en' => 'Interest rate, term and conditions should be properly evaluated when choosing a deposit.',
                    'ru' => 'При выборе депозита следует правильно оценить процентную ставку, срок и условия.'
                ],
                'content' => [
                    'az' => '<h2>Depozit Seçimində Əsas Kriteriyalar</h2>
                    <p>Pulunuzu depozitdə saxlamaq təhlükəsiz və gəlirli investisiya üsuludur. Düzgün seçim üçün bu məqamlara diqqət edin:</p>
                    <h3>1. Faiz Dərəcələrini Müqayisə Edin</h3>
                    <p>Müxtəlif bankların təklif etdiyi faiz dərəcələrini müqayisə edin. Yüksək faiz həmişə ən yaxşı seçim deyil.</p>
                    <h3>2. Depozit Müddətini Seçin</h3>
                    <p>Qısa müddətli və uzunmüddətli depozitlərin öz üstünlükləri var. Maliyyə məqsədlərinizə uyğun müddət seçin.</p>
                    <h3>3. Valyuta Seçimi</h3>
                    <p>Manat, dollar və ya avro depozitləri arasında seçim edərkən valyuta risklərini nəzərə alın.</p>
                    <h3>4. Erkən Çıxarış Şərtləri</h3>
                    <p>Təcili hallarda pulu çıxarmaq lazım olarsa, hansı şərtlər tətbiq olunur? Bu vacib məsələdir.</p>
                    <h3>5. Bankın Etibarlılığı</h3>
                    <p>Depozit sığortası və bankın maliyyə göstəriciləri haqqında məlumat toplayın.</p>',
                    'en' => '<h2>Key Criteria in Deposit Selection</h2>
                    <p>Keeping your money in a deposit is a safe and profitable investment method. Pay attention to these points for the right choice:</p>
                    <h3>1. Compare Interest Rates</h3>
                    <p>Compare interest rates offered by different banks. Higher interest is not always the best choice.</p>
                    <h3>2. Choose Deposit Term</h3>
                    <p>Short-term and long-term deposits have their advantages. Choose a term that suits your financial goals.</p>
                    <h3>3. Currency Selection</h3>
                    <p>Consider currency risks when choosing between manat, dollar, or euro deposits.</p>
                    <h3>4. Early Withdrawal Terms</h3>
                    <p>What conditions apply if you need to withdraw money in emergencies? This is an important issue.</p>
                    <h3>5. Bank Reliability</h3>
                    <p>Gather information about deposit insurance and the bank\'s financial indicators.</p>',
                    'ru' => '<h2>Ключевые критерии при выборе депозита</h2>
                    <p>Хранение денег на депозите - это безопасный и прибыльный метод инвестирования. Обратите внимание на эти моменты для правильного выбора:</p>
                    <h3>1. Сравните процентные ставки</h3>
                    <p>Сравните процентные ставки, предлагаемые разными банками. Более высокий процент не всегда лучший выбор.</p>
                    <h3>2. Выберите срок депозита</h3>
                    <p>Краткосрочные и долгосрочные депозиты имеют свои преимущества. Выберите срок, соответствующий вашим финансовым целям.</p>
                    <h3>3. Выбор валюты</h3>
                    <p>Учитывайте валютные риски при выборе между депозитами в манатах, долларах или евро.</p>
                    <h3>4. Условия досрочного снятия</h3>
                    <p>Какие условия применяются, если вам нужно снять деньги в экстренных случаях? Это важный вопрос.</p>
                    <h3>5. Надежность банка</h3>
                    <p>Соберите информацию о страховании депозитов и финансовых показателях банка.</p>'
                ],
                'author' => 'Maliyyə Məsləhətçisi',
                'tags' => ['depozit', 'əmanət', 'investisiya'],
                'reading_time' => 4,
                'featured' => true,
                'status' => true,
                'published_at' => now()->subDays(3),
                'seo_title' => [
                    'az' => 'Depozit Seçimi Məsləhətləri',
                    'en' => 'Deposit Selection Tips',
                    'ru' => 'Советы по выбору депозита'
                ],
                'seo_keywords' => [
                    'az' => 'depozit, əmanət, bank depoziti',
                    'en' => 'deposit, savings, bank deposit',
                    'ru' => 'депозит, сбережения, банковский депозит'
                ],
                'seo_description' => [
                    'az' => 'Depozit seçərkən diqqət edilməli vacib məqamlar',
                    'en' => 'Important points to consider when choosing a deposit',
                    'ru' => 'Важные моменты при выборе депозита'
                ]
            ],
            [
                'title' => [
                    'az' => 'İpoteka Krediti: Ev Almaq Üçün Doğru Yol',
                    'en' => 'Mortgage Loan: The Right Way to Buy a Home',
                    'ru' => 'Ипотечный кредит: Правильный путь к покупке дома'
                ],
                'slug' => 'ipoteka-krediti-rehber',
                'excerpt' => [
                    'az' => 'İpoteka krediti ilə ev almaq istəyənlər üçün ətraflı məlumat və tövsiyələr.',
                    'en' => 'Detailed information and recommendations for those who want to buy a house with a mortgage loan.',
                    'ru' => 'Подробная информация и рекомендации для тех, кто хочет купить дом с ипотечным кредитом.'
                ],
                'content' => [
                    'az' => '<h2>İpoteka Krediti ilə Ev Sahibi Olmaq</h2>
                    <p>İpoteka krediti uzunmüddətli maliyyə öhdəliyidir. Düzgün planlaşdırma ilə ev sahibi ola bilərsiniz.</p>
                    <h3>İlkin Ödəniş</h3>
                    <p>Adətən evin qiymətinin 20-30%-i qədər ilkin ödəniş tələb olunur. Bu məbləği əvvəlcədən yığmağa başlayın.</p>
                    <h3>Kredit Müddəti</h3>
                    <p>Uzun müddət aşağı aylıq ödəniş, lakin daha çox faiz deməkdir. Optimal müddəti seçin.</p>
                    <h3>Sənədlər</h3>
                    <p>Gəlir arayışı, iş yerindən təsdiq, şəxsiyyət vəsiqəsi və digər sənədləri hazırlayın.</p>
                    <h3>Əmlakın Qiymətləndirilməsi</h3>
                    <p>Bank evin bazar qiymətini qiymətləndirəcək. Bu prosesə hazır olun.</p>',
                    'en' => '<h2>Becoming a Homeowner with a Mortgage Loan</h2>
                    <p>A mortgage loan is a long-term financial commitment. With proper planning, you can become a homeowner.</p>
                    <h3>Down Payment</h3>
                    <p>Usually, a down payment of 20-30% of the house price is required. Start saving this amount in advance.</p>
                    <h3>Loan Term</h3>
                    <p>Longer term means lower monthly payment but more interest. Choose the optimal term.</p>
                    <h3>Documents</h3>
                    <p>Prepare income certificate, employment verification, ID card and other documents.</p>
                    <h3>Property Valuation</h3>
                    <p>The bank will evaluate the market value of the house. Be prepared for this process.</p>',
                    'ru' => '<h2>Стать домовладельцем с ипотечным кредитом</h2>
                    <p>Ипотечный кредит - это долгосрочное финансовое обязательство. При правильном планировании вы можете стать домовладельцем.</p>
                    <h3>Первоначальный взнос</h3>
                    <p>Обычно требуется первоначальный взнос в размере 20-30% от стоимости дома. Начните копить эту сумму заранее.</p>
                    <h3>Срок кредита</h3>
                    <p>Более длительный срок означает меньший ежемесячный платеж, но больше процентов. Выберите оптимальный срок.</p>
                    <h3>Документы</h3>
                    <p>Подготовьте справку о доходах, подтверждение с места работы, удостоверение личности и другие документы.</p>
                    <h3>Оценка недвижимости</h3>
                    <p>Банк оценит рыночную стоимость дома. Будьте готовы к этому процессу.</p>'
                ],
                'author' => 'Əmlak Eksperti',
                'tags' => ['ipoteka', 'ev krediti', 'əmlak'],
                'reading_time' => 6,
                'featured' => false,
                'status' => true,
                'published_at' => now()->subDays(7),
                'seo_title' => [
                    'az' => 'İpoteka Krediti Haqqında Hər Şey',
                    'en' => 'Everything About Mortgage Loans',
                    'ru' => 'Все об ипотечных кредитах'
                ],
                'seo_keywords' => [
                    'az' => 'ipoteka, ev krediti, ipoteka krediti',
                    'en' => 'mortgage, home loan, mortgage loan',
                    'ru' => 'ипотека, жилищный кредит, ипотечный кредит'
                ],
                'seo_description' => [
                    'az' => 'İpoteka krediti ilə ev almaq üçün bilməli olduğunuz hər şey',
                    'en' => 'Everything you need to know about buying a home with a mortgage loan',
                    'ru' => 'Все, что нужно знать о покупке дома с ипотечным кредитом'
                ]
            ],
            [
                'title' => [
                    'az' => 'Kredit Kartı İstifadəsində 5 Qızıl Qayda',
                    'en' => '5 Golden Rules for Credit Card Usage',
                    'ru' => '5 золотых правил использования кредитной карты'
                ],
                'slug' => 'kredit-karti-qaydalar',
                'excerpt' => [
                    'az' => 'Kredit kartından düzgün istifadə edərək maliyyə sağlamlığınızı qoruyun.',
                    'en' => 'Protect your financial health by using your credit card correctly.',
                    'ru' => 'Защитите свое финансовое здоровье, правильно используя кредитную карту.'
                ],
                'content' => [
                    'az' => '<h2>Kredit Kartı İstifadəsində Qızıl Qaydalar</h2>
                    <h3>1. Tam Ödəniş Edin</h3>
                    <p>Hər ay borcunuzu tam ödəyin. Minimum ödəniş faiz yükünü artırır.</p>
                    <h3>2. Limit Aşımından Qaçının</h3>
                    <p>Kredit limitinizin 30%-dən çoxunu istifadə etməyin. Bu, kredit reytinqinizə müsbət təsir edir.</p>
                    <h3>3. Nağd Avans Götürməyin</h3>
                    <p>Kredit kartından nağd pul çəkmək yüksək faiz və komissiya deməkdir.</p>
                    <h3>4. Ödəniş Tarixini Unutmayın</h3>
                    <p>Gecikmə cərimələrindən qaçmaq üçün avtomatik ödəmə qurun.</p>
                    <h3>5. Xərclərinizi İzləyin</h3>
                    <p>Kredit kartı ekstraktlarınızı müntəzəm yoxlayın və şübhəli əməliyyatları dərhal bildirin.</p>',
                    'en' => '<h2>Golden Rules for Credit Card Usage</h2>
                    <h3>1. Pay in Full</h3>
                    <p>Pay your debt in full every month. Minimum payment increases interest burden.</p>
                    <h3>2. Avoid Overlimit</h3>
                    <p>Don\'t use more than 30% of your credit limit. This positively affects your credit rating.</p>
                    <h3>3. Don\'t Take Cash Advances</h3>
                    <p>Withdrawing cash from a credit card means high interest and commission.</p>
                    <h3>4. Remember Payment Date</h3>
                    <p>Set up automatic payment to avoid late fees.</p>
                    <h3>5. Track Your Expenses</h3>
                    <p>Regularly check your credit card statements and immediately report suspicious transactions.</p>',
                    'ru' => '<h2>Золотые правила использования кредитной карты</h2>
                    <h3>1. Платите полностью</h3>
                    <p>Каждый месяц полностью погашайте свой долг. Минимальный платеж увеличивает процентную нагрузку.</p>
                    <h3>2. Избегайте превышения лимита</h3>
                    <p>Не используйте более 30% вашего кредитного лимита. Это положительно влияет на ваш кредитный рейтинг.</p>
                    <h3>3. Не берите наличные авансы</h3>
                    <p>Снятие наличных с кредитной карты означает высокие проценты и комиссии.</p>
                    <h3>4. Помните дату платежа</h3>
                    <p>Настройте автоматический платеж, чтобы избежать штрафов за просрочку.</p>
                    <h3>5. Отслеживайте расходы</h3>
                    <p>Регулярно проверяйте выписки по кредитной карте и немедленно сообщайте о подозрительных транзакциях.</p>'
                ],
                'author' => 'Kredit.az Team',
                'tags' => ['kredit kartı', 'maliyyə', 'ödəniş'],
                'reading_time' => 3,
                'featured' => false,
                'status' => true,
                'published_at' => now()->subDays(10),
                'seo_title' => [
                    'az' => 'Kredit Kartı İstifadə Qaydaları',
                    'en' => 'Credit Card Usage Rules',
                    'ru' => 'Правила использования кредитной карты'
                ],
                'seo_keywords' => [
                    'az' => 'kredit kartı, kredit kart qaydaları',
                    'en' => 'credit card, credit card rules',
                    'ru' => 'кредитная карта, правила кредитной карты'
                ],
                'seo_description' => [
                    'az' => 'Kredit kartından düzgün istifadə qaydaları',
                    'en' => 'Rules for proper credit card usage',
                    'ru' => 'Правила правильного использования кредитной карты'
                ]
            ],
            [
                'title' => [
                    'az' => 'Biznes Krediti: Kiçik Müəssisələr Üçün İmkanlar',
                    'en' => 'Business Loan: Opportunities for Small Businesses',
                    'ru' => 'Бизнес-кредит: Возможности для малого бизнеса'
                ],
                'slug' => 'biznes-krediti-imkanlar',
                'excerpt' => [
                    'az' => 'Kiçik və orta müəssisələr üçün biznes krediti imkanları və şərtləri.',
                    'en' => 'Business loan opportunities and conditions for small and medium enterprises.',
                    'ru' => 'Возможности и условия бизнес-кредитов для малых и средних предприятий.'
                ],
                'content' => [
                    'az' => '<h2>Biznesinizi Böyütmək Üçün Kredit İmkanları</h2>
                    <p>Biznes krediti müəssisənizin inkişafı üçün vacib maliyyə alətidir.</p>
                    <h3>Kredit Növləri</h3>
                    <ul>
                        <li>Dövriyyə vəsaiti krediti</li>
                        <li>İnvestisiya krediti</li>
                        <li>Overdraft</li>
                        <li>Kredit xətti</li>
                    </ul>
                    <h3>Tələb Olunan Sənədlər</h3>
                    <ul>
                        <li>Biznes plan</li>
                        <li>Maliyyə hesabatları</li>
                        <li>Vergi bəyannamələri</li>
                        <li>Müəssisənin qeydiyyat sənədləri</li>
                    </ul>
                    <h3>Kredit Şərtləri</h3>
                    <p>Faiz dərəcələri 8%-dən 15%-ə qədər dəyişir. Müddət biznesin növündən asılıdır.</p>
                    <h3>Uğurlu Müraciət Üçün Tövsiyələr</h3>
                    <p>Dəqiq biznes plan hazırlayın və maliyyə göstəricilərinizi şəffaf təqdim edin.</p>',
                    'en' => '<h2>Loan Opportunities to Grow Your Business</h2>
                    <p>Business loan is an important financial tool for your company\'s development.</p>
                    <h3>Types of Loans</h3>
                    <ul>
                        <li>Working capital loan</li>
                        <li>Investment loan</li>
                        <li>Overdraft</li>
                        <li>Credit line</li>
                    </ul>
                    <h3>Required Documents</h3>
                    <ul>
                        <li>Business plan</li>
                        <li>Financial statements</li>
                        <li>Tax returns</li>
                        <li>Company registration documents</li>
                    </ul>
                    <h3>Loan Terms</h3>
                    <p>Interest rates vary from 8% to 15%. The term depends on the type of business.</p>
                    <h3>Tips for Successful Application</h3>
                    <p>Prepare an accurate business plan and present your financial indicators transparently.</p>',
                    'ru' => '<h2>Кредитные возможности для развития вашего бизнеса</h2>
                    <p>Бизнес-кредит - важный финансовый инструмент для развития вашей компании.</p>
                    <h3>Виды кредитов</h3>
                    <ul>
                        <li>Кредит на оборотный капитал</li>
                        <li>Инвестиционный кредит</li>
                        <li>Овердрафт</li>
                        <li>Кредитная линия</li>
                    </ul>
                    <h3>Необходимые документы</h3>
                    <ul>
                        <li>Бизнес-план</li>
                        <li>Финансовая отчетность</li>
                        <li>Налоговые декларации</li>
                        <li>Регистрационные документы компании</li>
                    </ul>
                    <h3>Условия кредита</h3>
                    <p>Процентные ставки варьируются от 8% до 15%. Срок зависит от типа бизнеса.</p>
                    <h3>Советы для успешной заявки</h3>
                    <p>Подготовьте точный бизнес-план и прозрачно представьте свои финансовые показатели.</p>'
                ],
                'author' => 'Biznes Məsləhətçisi',
                'tags' => ['biznes', 'kredit', 'sahibkarlıq'],
                'reading_time' => 7,
                'featured' => true,
                'status' => true,
                'published_at' => now()->subDays(2),
                'seo_title' => [
                    'az' => 'Biznes Krediti İmkanları',
                    'en' => 'Business Loan Opportunities',
                    'ru' => 'Возможности бизнес-кредита'
                ],
                'seo_keywords' => [
                    'az' => 'biznes krediti, sahibkarlıq krediti',
                    'en' => 'business loan, entrepreneurship loan',
                    'ru' => 'бизнес-кредит, предпринимательский кредит'
                ],
                'seo_description' => [
                    'az' => 'Kiçik və orta biznes üçün kredit imkanları',
                    'en' => 'Loan opportunities for small and medium businesses',
                    'ru' => 'Кредитные возможности для малого и среднего бизнеса'
                ]
            ]
        ];

        foreach ($blogs as $blogData) {
            Blog::create($blogData);
        }
    }
}
