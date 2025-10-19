<?php

namespace App\Services;

use App\Services\AI\AIProviderFactory;
use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Log;

class NewsAIService
{
    protected AIProviderInterface $aiProvider;

    public function __construct(?string $provider = null)
    {
        $this->aiProvider = AIProviderFactory::make($provider);
    }

    /**
     * Generate a daily summary article about exchange rates
     *
     * @param array $ratesData Exchange rate data with current and previous rates
     * @return array ['title' => string, 'content' => string]
     */
    public function generateExchangeRatesSummary(array $ratesData): array
    {
        $date = $this->formatDateInAzerbaijani(now());
        $currentRates = $ratesData['current'];
        $previousRates = $ratesData['previous'] ?? [];

        $prompt = $this->buildExchangeRatesSummaryPrompt($date, $currentRates, $previousRates);

        try {
            $response = $this->aiProvider->generateText($prompt);
            return $this->parseArticleResponse($response);
        } catch (\Exception $e) {
            Log::error('Failed to generate exchange rates summary', [
                'error' => $e->getMessage(),
                'provider' => $this->aiProvider->getProviderName()
            ]);
            throw $e;
        }
    }

    /**
     * Generate a breaking news article about significant exchange rate change
     *
     * @param string $currency Currency code (USD, EUR, etc)
     * @param array $data Change data with current rate, previous rate, change percentage
     * @return array ['title' => string, 'content' => string]
     */
    public function generateExchangeRatesBreakingNews(string $currency, array $data): array
    {
        $prompt = $this->buildExchangeRatesBreakingPrompt($currency, $data);

        try {
            $response = $this->aiProvider->generateText($prompt);
            return $this->parseArticleResponse($response);
        } catch (\Exception $e) {
            Log::error('Failed to generate breaking news', [
                'error' => $e->getMessage(),
                'currency' => $currency,
                'provider' => $this->aiProvider->getProviderName()
            ]);
            throw $e;
        }
    }

    /**
     * Build prompt for daily exchange rates summary
     */
    protected function buildExchangeRatesSummaryPrompt(string $date, array $currentRates, array $previousRates): string
    {
        $ratesText = $this->formatRatesForPrompt($currentRates, $previousRates);
        $minWords = config('ai.news.min_words', 30);
        $maxWords = config('ai.news.max_words', 350);

        return <<<PROMPT
Sen peşəkar Azərbaycan maliyyə jurnalistisən. Bugünkü valyuta məzənnələri haqqında gündəlik xəbər yazmalısan.

TARIX: {$date}

MƏLUMATLAR (YALNIZ BUNLARI İSTİFADƏ ET):
{$ratesText}

QATI QADAĞALAR:
🚫 NEFTİN, QIZIL, KRİPTOVALYUTA VƏ YA DİGƏR AKTİVLƏR HAQQINDA MƏLUMAT YAZMAQ QADAĞANDIR!
🚫 VERİLMƏYƏN MƏLUMATLARI UYDURMAQ/HALLÜSİNASİYA QADAĞANDIR!
🚫 YALNIZ YUXARIDA VERİLƏN 4 VALYUTA (USD, EUR, RUB, GBP) HAQQINDA YAZ!
🚫 BİLMƏDİYİN ŞEYLƏRİ UYDURMAQDAN ÇƏKIN!

Bu etibarlı maliyyə xəbər saytıdır. Yalnız faktiki məlumat yazmalısan!

TƏLƏBLƏR:
1. Məqalə {$minWords}-{$maxWords} söz olmalıdır
2. Yalnız Azərbaycan dilində yaz (tarix də Azərbaycan dilində olmalıdır!)
3. Peşəkar jurnalistik üslubda yaz
4. Başlıq: "Bugünkü valyuta məzənnələri - {$date}" formatında (heç bir ** və ya markdown simvolu olmadan, tarix Azərbaycanca)
5. Məzmun (yalnız verilmiş məlumatlar əsasında):
   - Bugünkü 4 valyuta məzənnəsini açıqla
   - 24 saat ərzində dəyişiklikləri qeyd et (əgər varsa)
   - Yalnız məzənnələr haqqında yaz, başqa heç nə

6. Paraqraflar arasında boş sətir əlavə et (oxunaqlılıq üçün)

FORMAT:
Bugünkü valyuta məzənnələri - {$date}
---
Birinci paraqraf məzmun.

İkinci paraqraf məzmun.

Üçüncü paraqraf məzmun.

VACIB: Yalnız verilmiş məlumatları istifadə et. Söz sayını artırmaq üçün uydurmaq qadağandır!
PROMPT;
    }

    /**
     * Build prompt for breaking news about exchange rate change
     */
    protected function buildExchangeRatesBreakingPrompt(string $currency, array $data): string
    {
        $currencyName = $this->getCurrencyNameInAzerbaijani($currency);
        $changePercent = $data['change_percent'];
        $direction = $changePercent > 0 ? 'bahalaşdı' : 'ucuzlaşdı';
        $currentRate = $data['current_rate'];
        $previousRate = $data['previous_rate'];
        $minWords = config('ai.news.min_words', 30);
        $maxWords = config('ai.news.max_words', 350);

        return <<<PROMPT
TƏCILI XƏBƏR! {$currencyName} valyutası kəskin dəyişiklik göstərib.

MƏLUMAT (YALNIZ BUNLARI İSTİFADƏ ET):
- Valyuta: {$currencyName} ({$currency})
- Cari məzənnə: {$currentRate} AZN
- Əvvəlki məzənnə: {$previousRate} AZN
- Dəyişiklik: {$changePercent}% ({$direction})

QATI QADAĞALAR:
🚫 VERİLMƏYƏN MƏLUMATLARI UYDURMAQ QADAĞANDIR!
🚫 NEFTİN, QIZIL, DİGƏR VALYUTALAR HAQQINDA YAZMAQ QADAĞANDIR!
🚫 BİLMƏDİYİN SƏBƏBLƏRİ UYDURMAQ QADAĞANDIR!
🚫 YALNIZ {$currencyName} HAQQINDA VƏ VERİLMİŞ MƏLUMATLAR ƏSASINDA YAZ!

Bu etibarlı maliyyə xəbər saytıdır. Faktiki məlumat ver!

TƏLƏBLƏR:
1. {$minWords}-{$maxWords} söz təcili xəbər məqaləsi
2. Yalnız Azərbaycan dilində
3. Təcili xəbər üslubu - dinamik və faktiki
4. Başlıq: Qısa, təcili, markdown simvolları olmadan (məsələn: "{$currencyName} kəskin {$direction} - {$changePercent}% dəyişiklik")

5. Məzmun (yalnız verilmiş faktlara əsasən):
   - Məzənnə nə qədər dəyişdi (faktlar)
   - Bu biznes və vətəndaşlara necə təsir edəcək
   - Paraqraflar arasında boş sətir

FORMAT:
{$currencyName} kəskin {$direction} - {$changePercent}% dəyişiklik
---
Birinci paraqraf - nə baş verdi.

İkinci paraqraf - təsir və nəticələr.

Üçüncü paraqraf - əhəmiyyəti.

VACIB: Yalnız verilmiş məlumatlar. Söz sayını artırmaq üçün uydurmaq qadağandır!
PROMPT;
    }

    /**
     * Format rates data for prompt
     */
    protected function formatRatesForPrompt(array $currentRates, array $previousRates): string
    {
        $text = '';
        foreach ($currentRates as $code => $rate) {
            $previousRate = $previousRates[$code] ?? $rate;
            $change = (($rate - $previousRate) / $previousRate) * 100;
            $changeFormatted = number_format($change, 2);

            $text .= "- {$code}: {$rate} AZN (24h dəyişiklik: {$changeFormatted}%)\n";
        }
        return $text;
    }

    /**
     * Parse AI response to extract title and content
     */
    protected function parseArticleResponse(string $response): array
    {
        $parts = explode('---', $response, 2);

        if (count($parts) !== 2) {
            $parts = explode("\n\n", $response, 2);
        }

        if (count($parts) === 2) {
            $title = trim(str_replace(['Başlıq:', 'Başliq:'], '', $parts[0]));
            $content = trim($parts[1]);
        } else {
            $lines = explode("\n", $response);
            $title = trim($lines[0]);
            $content = trim(implode("\n", array_slice($lines, 1)));
        }

        // Clean markdown symbols from title
        $title = $this->cleanMarkdown($title);

        // Clean excessive markdown from content but keep basic formatting
        $content = $this->cleanContent($content);

        return [
            'title' => $title,
            'content' => $content
        ];
    }

    /**
     * Remove markdown formatting symbols
     */
    protected function cleanMarkdown(string $text): string
    {
        // Remove bold/italic markers
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/__(.+?)__/', '$1', $text);
        $text = preg_replace('/_(.+?)_/', '$1', $text);

        // Remove heading markers
        $text = preg_replace('/^#+\s*/', '', $text);

        return trim($text);
    }

    /**
     * Clean content but preserve paragraph structure
     */
    protected function cleanContent(string $text): string
    {
        // Convert markdown bold to HTML strong
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

        // Remove heading markers
        $text = preg_replace('/^#+\s*/m', '', $text);

        // Convert paragraphs to HTML <p> tags
        $text = $this->convertToHtmlParagraphs($text);

        return trim($text);
    }

    /**
     * Convert plain text paragraphs to HTML <p> tags
     */
    protected function convertToHtmlParagraphs(string $text): string
    {
        // Split by double newlines (paragraph breaks)
        $paragraphs = preg_split('/\n\s*\n/', $text);

        // Wrap each paragraph in <p> tags
        $htmlParagraphs = array_map(function($paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                return '';
            }
            // Replace single newlines within paragraph with <br>
            $paragraph = nl2br($paragraph);
            return '<p>' . $paragraph . '</p>';
        }, $paragraphs);

        // Filter out empty paragraphs and join
        $htmlParagraphs = array_filter($htmlParagraphs);
        return implode('', $htmlParagraphs);
    }

    /**
     * Get currency name in Azerbaijani
     */
    protected function getCurrencyNameInAzerbaijani(string $code): string
    {
        return match($code) {
            'USD' => 'ABŞ dolları',
            'EUR' => 'Avro',
            'RUB' => 'Rusiya rublu',
            'GBP' => 'İngilis funtu sterlinqi',
            'TRY' => 'Türk lirəsi',
            default => $code
        };
    }

    /**
     * Format date in Azerbaijani (e.g., "11 oktyabr 2025")
     */
    protected function formatDateInAzerbaijani($date): string
    {
        $months = [
            1 => 'yanvar',
            2 => 'fevral',
            3 => 'mart',
            4 => 'aprel',
            5 => 'may',
            6 => 'iyun',
            7 => 'iyul',
            8 => 'avqust',
            9 => 'sentyabr',
            10 => 'oktyabr',
            11 => 'noyabr',
            12 => 'dekabr',
        ];

        $day = $date->format('d');
        $month = $months[(int)$date->format('n')];
        $year = $date->format('Y');

        return "{$day} {$month} {$year}";
    }
}
