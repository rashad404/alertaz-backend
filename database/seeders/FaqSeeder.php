<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing FAQs in development
        if (app()->environment(['local', 'staging'])) {
            Faq::truncate();
        }

        $faqs = [
            [
                'question' => [
                    'az' => 'Kredit.az nədir və necə işləyir?',
                    'en' => 'What is Kredit.az and how does it work?',
                    'ru' => 'Что такое Kredit.az и как это работает?'
                ],
                'answer' => [
                    'az' => 'Kredit.az Azərbaycanda fəaliyyət göstərən bank və kredit təşkilatlarının kredit məhsullarını bir platformada toplayan müqayisə xidmətidir. Siz bizim platformamızda müxtəlif bankların kredit təkliflərini müqayisə edə, ən uyğun şərtləri seçə və birbaşa onlayn müraciət edə bilərsiniz.',
                    'en' => 'Kredit.az is a comparison service that brings together credit products from banks and credit institutions operating in Azerbaijan on one platform. On our platform, you can compare credit offers from various banks, choose the most suitable terms, and apply directly online.',
                    'ru' => 'Kredit.az - это сервис сравнения, который объединяет кредитные продукты банков и кредитных организаций, работающих в Азербайджане, на одной платформе. На нашей платформе вы можете сравнить кредитные предложения различных банков, выбрать наиболее подходящие условия и подать заявку напрямую онлайн.'
                ],
                'order' => 1,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Kredit müraciətimi necə edə bilərəm?',
                    'en' => 'How can I apply for a loan?',
                    'ru' => 'Как я могу подать заявку на кредит?'
                ],
                'answer' => [
                    'az' => 'Kredit müraciəti etmək çox sadədir: 1) İstədiyiniz kredit məbləği və müddətini daxil edin, 2) Müqayisə nəticələrindən sizə uyğun olan bankı seçin, 3) Tələb olunan məlumatları doldurun, 4) Müraciətinizi göndərin. Bank sizinlə 1-2 iş günü ərzində əlaqə saxlayacaq.',
                    'en' => 'Applying for a loan is very simple: 1) Enter your desired loan amount and term, 2) Choose the bank that suits you from the comparison results, 3) Fill in the required information, 4) Submit your application. The bank will contact you within 1-2 business days.',
                    'ru' => 'Подать заявку на кредит очень просто: 1) Введите желаемую сумму и срок кредита, 2) Выберите подходящий вам банк из результатов сравнения, 3) Заполните необходимую информацию, 4) Отправьте заявку. Банк свяжется с вами в течение 1-2 рабочих дней.'
                ],
                'order' => 2,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Kredit.az xidməti pulludur?',
                    'en' => 'Is Kredit.az service paid?',
                    'ru' => 'Платный ли сервис Kredit.az?'
                ],
                'answer' => [
                    'az' => 'Xeyr, Kredit.az xidməti tamamilə pulsuzdur. Siz heç bir ödəniş etmədən bütün kredit təkliflərini müqayisə edə və müraciət edə bilərsiniz. Biz banklar və kredit təşkilatları ilə əməkdaşlıq əsasında çalışırıq.',
                    'en' => 'No, Kredit.az service is completely free. You can compare all loan offers and apply without any payment. We work on a partnership basis with banks and credit institutions.',
                    'ru' => 'Нет, сервис Kredit.az абсолютно бесплатный. Вы можете сравнивать все кредитные предложения и подавать заявки без какой-либо оплаты. Мы работаем на партнерской основе с банками и кредитными организациями.'
                ],
                'order' => 3,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Hansı sənədlər tələb olunur?',
                    'en' => 'What documents are required?',
                    'ru' => 'Какие документы требуются?'
                ],
                'answer' => [
                    'az' => 'Əsas tələb olunan sənədlər: Şəxsiyyət vəsiqəsi, gəliri təsdiq edən sənəd (əmək haqqı arayışı), iş yerindən arayış. Bəzi banklar əlavə sənədlər də tələb edə bilər. Hər bankın öz tələbləri var və müraciət zamanı sizə məlumat veriləcək.',
                    'en' => 'Main required documents: ID card, income certificate (salary certificate), certificate from workplace. Some banks may require additional documents. Each bank has its own requirements and you will be informed during the application.',
                    'ru' => 'Основные требуемые документы: удостоверение личности, справка о доходах (справка о зарплате), справка с места работы. Некоторые банки могут потребовать дополнительные документы. У каждого банка свои требования, и вы будете проинформированы при подаче заявки.'
                ],
                'order' => 4,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Kredit tarixçəm pisdirsə, kredit ala bilərəmmi?',
                    'en' => 'Can I get a loan if I have bad credit history?',
                    'ru' => 'Могу ли я получить кредит с плохой кредитной историей?'
                ],
                'answer' => [
                    'az' => 'Kredit tarixçəsi kreditin təsdiqlənməsində mühüm rol oynayır. Lakin bəzi banklar və kredit təşkilatları kiçik problemləri olan müştərilərə də kredit verə bilər. Hər bankın öz qiymətləndirmə meyarları var. Müraciət edərək şansınızı yoxlaya bilərsiniz.',
                    'en' => 'Credit history plays an important role in loan approval. However, some banks and credit institutions may provide loans to customers with minor problems. Each bank has its own evaluation criteria. You can check your chances by applying.',
                    'ru' => 'Кредитная история играет важную роль в одобрении кредита. Однако некоторые банки и кредитные организации могут предоставить кредиты клиентам с незначительными проблемами. У каждого банка свои критерии оценки. Вы можете проверить свои шансы, подав заявку.'
                ],
                'order' => 5,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Kreditin ödəniş müddətini uzada bilərəmmi?',
                    'en' => 'Can I extend the loan payment period?',
                    'ru' => 'Могу ли я продлить срок погашения кредита?'
                ],
                'answer' => [
                    'az' => 'Bəli, əksər banklar restrukturizasiya xidməti təklif edir. Əgər maliyyə vəziyyətiniz dəyişibsə, bankınızla əlaqə saxlayaraq ödəniş müddətinin uzadılması və ya aylıq ödənişin azaldılması haqqında danışıqlar apara bilərsiniz.',
                    'en' => 'Yes, most banks offer restructuring services. If your financial situation has changed, you can contact your bank to negotiate extending the payment period or reducing monthly payments.',
                    'ru' => 'Да, большинство банков предлагают услуги реструктуризации. Если ваше финансовое положение изменилось, вы можете связаться со своим банком, чтобы договориться о продлении срока платежа или уменьшении ежемесячных платежей.'
                ],
                'order' => 6,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Vaxtından əvvəl krediti bağlaya bilərəmmi?',
                    'en' => 'Can I close the loan early?',
                    'ru' => 'Могу ли я досрочно погасить кредит?'
                ],
                'answer' => [
                    'az' => 'Bəli, Azərbaycan qanunvericiliyinə əsasən siz istənilən vaxt krediti tam və ya qismən vaxtından əvvəl bağlaya bilərsiniz. Bəzi banklar bunun üçün komissiya tələb edə bilər, lakin bu komissiya qalan faiz məbləğinin müəyyən faizindən çox ola bilməz.',
                    'en' => 'Yes, according to Azerbaijan legislation, you can fully or partially close the loan early at any time. Some banks may charge a commission for this, but this commission cannot exceed a certain percentage of the remaining interest amount.',
                    'ru' => 'Да, согласно законодательству Азербайджана, вы можете полностью или частично досрочно погасить кредит в любое время. Некоторые банки могут взимать за это комиссию, но эта комиссия не может превышать определенный процент от оставшейся суммы процентов.'
                ],
                'order' => 7,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Zamin və ya girov olmadan kredit ala bilərəmmi?',
                    'en' => 'Can I get a loan without a guarantor or collateral?',
                    'ru' => 'Могу ли я получить кредит без поручителя или залога?'
                ],
                'answer' => [
                    'az' => 'Bəli, bir çox banklar nağd kredit və ya istehlak krediti adı altında zaminsiz və girovsuz kreditlər təklif edir. Bu kreditlərin məbləği və şərtləri sizin gəliriniz və kredit tarixçənizə əsasən müəyyən edilir.',
                    'en' => 'Yes, many banks offer loans without guarantor and collateral under the name of cash loans or consumer loans. The amount and terms of these loans are determined based on your income and credit history.',
                    'ru' => 'Да, многие банки предлагают кредиты без поручителя и залога под названием наличные кредиты или потребительские кредиты. Сумма и условия этих кредитов определяются на основе вашего дохода и кредитной истории.'
                ],
                'order' => 8,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Onlayn müraciət etdikdən sonra nə baş verir?',
                    'en' => 'What happens after I apply online?',
                    'ru' => 'Что происходит после онлайн-заявки?'
                ],
                'answer' => [
                    'az' => 'Onlayn müraciət etdikdən sonra: 1) Bank nümayəndəsi 1-2 iş günü ərzində sizinlə əlaqə saxlayacaq, 2) Əlavə məlumat və sənədlər tələb oluna bilər, 3) Müraciətiniz qiymətləndiriləcək, 4) Təsdiqlənərsə, müqavilə imzalanması üçün banka dəvət olunacaqsınız.',
                    'en' => 'After applying online: 1) A bank representative will contact you within 1-2 business days, 2) Additional information and documents may be required, 3) Your application will be evaluated, 4) If approved, you will be invited to the bank to sign the contract.',
                    'ru' => 'После онлайн-заявки: 1) Представитель банка свяжется с вами в течение 1-2 рабочих дней, 2) Могут потребоваться дополнительная информация и документы, 3) Ваша заявка будет оценена, 4) В случае одобрения вы будете приглашены в банк для подписания договора.'
                ],
                'order' => 9,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Eyni anda bir neçə banka müraciət edə bilərəmmi?',
                    'en' => 'Can I apply to several banks at the same time?',
                    'ru' => 'Могу ли я подать заявку в несколько банков одновременно?'
                ],
                'answer' => [
                    'az' => 'Bəli, eyni anda bir neçə banka müraciət edə bilərsiniz. Bu sizə ən yaxşı şərtləri müqayisə etmək və seçmək imkanı verir. Lakin unutmayın ki, hər müraciət kredit bürosunda qeydə alınır və çoxlu müraciətlər kredit reytinqinizə təsir edə bilər.',
                    'en' => 'Yes, you can apply to several banks at the same time. This gives you the opportunity to compare and choose the best terms. However, remember that each application is registered with the credit bureau and multiple applications can affect your credit rating.',
                    'ru' => 'Да, вы можете подать заявку в несколько банков одновременно. Это дает вам возможность сравнить и выбрать лучшие условия. Однако помните, что каждая заявка регистрируется в кредитном бюро, и множественные заявки могут повлиять на ваш кредитный рейтинг.'
                ],
                'order' => 10,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Kredit faizləri necə hesablanır?',
                    'en' => 'How are loan interest rates calculated?',
                    'ru' => 'Как рассчитываются процентные ставки по кредитам?'
                ],
                'answer' => [
                    'az' => 'Kredit faizləri illik faiz dərəcəsi əsasında hesablanır. Məsələn, 15% illik faizlə 12.000 AZN kredit götürsəniz, il ərzində təxminən 1.800 AZN faiz ödəyəcəksiniz. Aylıq ödəniş məbləği kredit məbləği, faiz dərəcəsi və müddətdən asılı olaraq hesablanır. Bizim kalkulyatorumuzdan istifadə edərək dəqiq hesablamanı görə bilərsiniz.',
                    'en' => 'Loan interest is calculated based on the annual interest rate. For example, if you take a loan of 12,000 AZN at 15% annual interest, you will pay approximately 1,800 AZN in interest per year. The monthly payment amount is calculated depending on the loan amount, interest rate and term. You can see the exact calculation using our calculator.',
                    'ru' => 'Проценты по кредиту рассчитываются на основе годовой процентной ставки. Например, если вы возьмете кредит в размере 12 000 AZN под 15% годовых, вы заплатите около 1 800 AZN процентов в год. Сумма ежемесячного платежа рассчитывается в зависимости от суммы кредита, процентной ставки и срока. Вы можете увидеть точный расчет с помощью нашего калькулятора.'
                ],
                'order' => 11,
                'status' => true
            ],
            [
                'question' => [
                    'az' => 'Minimum və maksimum kredit məbləği nə qədərdir?',
                    'en' => 'What are the minimum and maximum loan amounts?',
                    'ru' => 'Каковы минимальная и максимальная суммы кредита?'
                ],
                'answer' => [
                    'az' => 'Kredit məbləği bankdan banka dəyişir. Ümumiyyətlə, minimum kredit məbləği 300-500 AZN, maksimum isə 50.000-100.000 AZN arasında dəyişir. İpoteka kreditləri üçün bu məbləğ daha yüksək ola bilər. Dəqiq məbləğ sizin gəliriniz və kredit qabiliyyətinizdən asılıdır.',
                    'en' => 'The loan amount varies from bank to bank. Generally, the minimum loan amount ranges from 300-500 AZN, and the maximum ranges from 50,000-100,000 AZN. For mortgage loans, this amount can be higher. The exact amount depends on your income and creditworthiness.',
                    'ru' => 'Сумма кредита варьируется от банка к банку. Как правило, минимальная сумма кредита составляет от 300-500 AZN, а максимальная - от 50 000-100 000 AZN. Для ипотечных кредитов эта сумма может быть выше. Точная сумма зависит от вашего дохода и кредитоспособности.'
                ],
                'order' => 12,
                'status' => true
            ]
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }

        $this->command->info('FAQ items seeded successfully with multilingual content!');
    }
}