<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class BankRateScraperService
{
    /**
     * Fetch rates from Kapital Bank
     */
    public function fetchKapitalBank()
    {
        // Get current date for URL
        $date = now()->format('d-m-Y');

        // Try the date-specific URL first
        $url = "https://www.kapitalbank.az/en/currency-rates/{$date}";

        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache'
            ])
            ->get($url);

        // If date-specific URL fails, try the main rates page
        if (!$response->successful()) {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Cache-Control' => 'no-cache'
                ])
                ->get('https://www.kapitalbank.az/en/currency-rates');
        }

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Kapital Bank website - Status: ' . $response->status());
        }

        $html = $response->body();
        $rates = [];

        // Parse the currency table - look for the rates table
        $crawler = new Crawler($html);

        // Try to find the currency rates table
        $ratesTable = $crawler->filter('table')->first();

        if ($ratesTable->count() > 0) {
            $ratesTable->filter('tbody tr')->each(function (Crawler $row) use (&$rates) {
                $cells = $row->filter('td');

                if ($cells->count() >= 3) {
                    $currency = trim($cells->eq(0)->text());
                    $buy = floatval(str_replace(',', '.', $cells->eq(1)->text()));
                    $sell = floatval(str_replace(',', '.', $cells->eq(2)->text()));

                    // Map currency codes
                    $currencyMap = [
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                        'RUB' => 'RUB',
                        'TRY' => 'TRY'
                    ];

                    if (isset($currencyMap[$currency])) {
                        $rates[$currencyMap[$currency]] = [
                            'buy' => $buy,
                            'sell' => $sell
                        ];
                    }
                }
            });
        }

        return $rates;
    }

    /**
     * Fetch rates from ABB
     */
    public function fetchABB()
    {
        $response = Http::get('https://abb-bank.az/az/valyuta-mezenneleri');
        
        if (!$response->successful()) {
            // Try homepage
            $response = Http::get('https://abb-bank.az/az');
            if (!$response->successful()) {
                throw new \Exception('Failed to fetch ABB website');
            }
        }

        $html = $response->body();
        $rates = [];

        // ABB stores rates in JavaScript variable
        if (preg_match('/const rates = (\{.*?\});/s', $html, $match)) {
            $jsonStr = $match[1];
            // Parse the nested structure {USD:{AZN:1.6970}}
            if (preg_match('/USD:\{AZN:([\d.]+)\}/', $jsonStr, $usdMatch)) {
                // For ABB, the rate shown is sell rate (customer buys USD from bank)
                $usdSell = floatval($usdMatch[1]);
                $rates['USD'] = [
                    'buy' => $usdSell - 0.003, // Bank typically has small spread
                    'sell' => $usdSell
                ];
            }
            if (preg_match('/EUR:\{AZN:([\d.]+)\}/', $jsonStr, $eurMatch)) {
                $eurSell = floatval($eurMatch[1]);
                $rates['EUR'] = [
                    'buy' => $eurSell - 0.005,
                    'sell' => $eurSell
                ];
            }
            if (preg_match('/RUB:\{AZN:([\d.]+)\}/', $jsonStr, $rubMatch)) {
                $rubSell = floatval($rubMatch[1]);
                $rates['RUB'] = [
                    'buy' => $rubSell - 0.0002,
                    'sell' => $rubSell
                ];
            }
        }

        // Alternative: Parse from table
        if (empty($rates)) {
            if (preg_match_all('/<tr[^>]*>.*?<td[^>]*>\s*(USD|EUR|GBP|RUB|TRY)\s*<\/td>.*?<td[^>]*>\s*([\d.]+)\s*.*?<\/td>.*?<td[^>]*>\s*([\d.]+)\s*.*?<\/td>/is', $html, $matches)) {
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $rates[$matches[1][$i]] = [
                        'buy' => floatval($matches[3][$i]), // Buy is usually 3rd column
                        'sell' => floatval($matches[2][$i])  // Sell is 2nd column
                    ];
                }
            }
        }

        return $rates;
    }

    /**
     * Fetch rates from Bank Respublika
     */
    public function fetchBankRespublika()
    {
        // Use the actual API endpoint discovered from their JavaScript
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Referer' => 'https://www.bankrespublika.az/'
            ])
            ->get('https://www.bankrespublika.az/get_rates');
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Bank Respublika rates API');
        }

        $data = $response->json();
        $rates = [];

        if (!is_array($data)) {
            throw new \Exception('Invalid API response format from Bank Respublika');
        }

        // Parse the JSON response
        foreach ($data as $item) {
            if (!isset($item['code'])) continue;
            
            $currency = $item['code'];
            
            // Only process currencies we're interested in
            if (in_array($currency, ['USD', 'EUR', 'GBP', 'RUB', 'TRY'])) {
                $rates[$currency] = [
                    'buy' => floatval($item['rate_buy'] ?? 0),
                    'sell' => floatval($item['rate_sell'] ?? 0)
                ];
            }
        }

        return $rates;
    }

    /**
     * Fetch rates from Unibank
     */
    public function fetchUnibank()
    {
        $response = Http::get('https://unibank.az/az');
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Unibank website');
        }

        $html = $response->body();
        $rates = [];

        // Parse the exchange table - Unibank has two tables (cash and non-cash)
        // We'll use the first table (cash rates)
        if (preg_match('/<tbody>(.*?)<\/tbody>/is', $html, $tableMatch)) {
            $tableHtml = $tableMatch[1];
            
            // Extract USD
            if (preg_match('/<td>USD<\/td>\s*<td>([\d.]+).*?<\/td>\s*<td>([\d.]+).*?<\/td>/is', $tableHtml, $match)) {
                $rates['USD'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract EUR
            if (preg_match('/<td>EUR<\/td>\s*<td>([\d.]+).*?<\/td>\s*<td>([\d.]+).*?<\/td>/is', $tableHtml, $match)) {
                $rates['EUR'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract GBP
            if (preg_match('/<td>GBP<\/td>\s*<td>([\d.]+).*?<\/td>\s*<td>([\d.]+).*?<\/td>/is', $tableHtml, $match)) {
                $rates['GBP'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract RUB
            if (preg_match('/<td>RUB<\/td>\s*<td>([\d.]+).*?<\/td>\s*<td>([\d.]+).*?<\/td>/is', $tableHtml, $match)) {
                $rates['RUB'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract TRY
            if (preg_match('/<td>TRY<\/td>\s*<td>([\d.]+).*?<\/td>\s*<td>([\d.]+).*?<\/td>/is', $tableHtml, $match)) {
                $rates['TRY'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
        }

        return $rates;
    }

    /**
     * Fetch rates from PASHA Bank
     */
    public function fetchPashaBank()
    {
        // Scrape from website
        $response = Http::get('https://www.pashabank.az/lang,az/');
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch PASHA Bank website');
        }

        $html = $response->body();
        $rates = [];

        // Parse the currency table from the HTML
        if (preg_match('/<table[^>]*class="currency_prices"[^>]*>(.*?)<\/table>/is', $html, $tableMatch)) {
            $tableHtml = $tableMatch[1];
            
            // Extract USD
            if (preg_match('/1 USD<\/td>\s*<td[^>]*>([\d.]+)<\/td>\s*<td>([\d.]+)<\/td>/i', $tableHtml, $match)) {
                $rates['USD'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract EUR
            if (preg_match('/1 EUR<\/td>\s*<td[^>]*>([\d.]+)<\/td>\s*<td>([\d.]+)<\/td>/i', $tableHtml, $match)) {
                $rates['EUR'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract GBP
            if (preg_match('/1 GBP<\/td>\s*<td[^>]*>([\d.]+)<\/td>\s*<td>([\d.]+)<\/td>/i', $tableHtml, $match)) {
                $rates['GBP'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
            
            // Extract RUB (note: it's per 100 RUB)
            if (preg_match('/100 RUB<\/td>\s*<td[^>]*>([\d.]+)<\/td>\s*<td>([\d.]+)<\/td>/i', $tableHtml, $match)) {
                $rates['RUB'] = [
                    'buy' => floatval($match[1]) / 100, // Convert to per 1 RUB
                    'sell' => floatval($match[2]) / 100
                ];
            }
            
            // Extract TRY
            if (preg_match('/1 TRY<\/td>\s*<td[^>]*>([\d.]+)<\/td>\s*<td>([\d.]+)<\/td>/i', $tableHtml, $match)) {
                $rates['TRY'] = [
                    'buy' => floatval($match[1]),
                    'sell' => floatval($match[2])
                ];
            }
        }

        return $rates;
    }
}