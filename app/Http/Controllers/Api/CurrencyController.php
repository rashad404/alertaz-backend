<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\BuySellRate;
use App\Models\Company;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /**
     * Get all active currencies
     */
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        // Get currencies from database
        $currencies = Currency::where('status', true)
            ->orderBy('order')
            ->get();
        
        // Get latest exchange rates from CBAR
        $latestRates = ExchangeRate::getLatestRates();
        
        // Update currencies with latest CBAR rates
        $currencies = $currencies->map(function($currency) use ($latestRates) {
            $exchangeRate = $latestRates->firstWhere('currency_code', $currency->currency);
            if ($exchangeRate) {
                // Override with fresh CBAR rate
                $currency->central_bank_rate = $exchangeRate->actual_rate;
            }
            return $currency;
        });
            
        return response()->json([
            'success' => true,
            'data' => $currencies
        ]);
    }
    
    /**
     * Get currency rates with bank buy/sell prices
     */
    public function rates(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $currencies = Currency::where('status', true)
            ->with(['buySellRates.company' => function($query) {
                $query->where('is_active', true)
                    ->where('company_type_id', 1); // 1 is for banks
            }])
            ->orderBy('order')
            ->get();
        
        // Get latest exchange rates from CBAR
        $latestRates = ExchangeRate::getLatestRates();
            
        $result = $currencies->map(function($currency) use ($lang, $latestRates) {
            // Update with latest CBAR rate
            $exchangeRate = $latestRates->firstWhere('currency_code', $currency->currency);
            $centralBankRate = $exchangeRate ? $exchangeRate->actual_rate : $currency->central_bank_rate;
            
            $rates = $currency->buySellRates->map(function($rate) use ($lang) {
                $bankName = null;
                if ($rate->company && $rate->company->name) {
                    $name = $rate->company->name;
                    // Check if name is JSON-encoded
                    if (is_string($name) && (strpos($name, '{') === 0 || strpos($name, '[') === 0)) {
                        $decoded = json_decode($name, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $bankName = $decoded[$lang] ?? $decoded['az'] ?? $decoded['en'] ?? $name;
                        } else {
                            $bankName = $name;
                        }
                    } else {
                        $bankName = $name;
                    }
                }

                return [
                    'bank_id' => $rate->company_id,
                    'bank_name' => $bankName,
                    'bank_logo' => $rate->company && $rate->company->logo
                        ? asset('storage/' . $rate->company->logo)
                        : null,
                    'buy_price' => $rate->buy_price,
                    'sell_price' => $rate->sell_price,
                ];
            })->filter(function($rate) {
                return $rate['bank_name'] !== null;
            })->values();
            
            return [
                'id' => $currency->id,
                'currency' => $currency->currency,
                'central_bank_rate' => $centralBankRate,
                'bank_rates' => $rates
            ];
        });
            
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
    
    /**
     * Calculate currency conversion
     */
    public function calculate(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $request->validate([
            'from_currency' => 'required|string',
            'to_currency' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'operation' => 'required|in:buy,sell',
            'bank_id' => 'nullable|exists:companies,id'
        ]);
        
        $fromCurrency = $request->from_currency;
        $toCurrency = $request->to_currency;
        $amount = $request->amount;
        $operation = $request->operation;
        $bankId = $request->bank_id;
        
        // Handle AZN to AZN conversion
        if ($fromCurrency === 'AZN' && $toCurrency === 'AZN') {
            return response()->json([
                'success' => true,
                'data' => [
                    'from_currency' => 'AZN',
                    'to_currency' => 'AZN',
                    'amount' => $amount,
                    'result' => $amount,
                    'rate' => 1,
                    'operation' => $operation
                ]
            ]);
        }
        
        // Get currency rates
        $result = null;
        $rate = null;
        $usedRate = null;
        
        if ($fromCurrency === 'AZN') {
            // Converting from AZN to foreign currency (selling foreign currency)
            $currency = Currency::where('currency', $toCurrency)
                ->where('status', true)
                ->first();
                
            if (!$currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency not found'
                ], 404);
            }
            
            if ($bankId) {
                $bankRate = BuySellRate::where('currency_id', $currency->id)
                    ->where('company_id', $bankId)
                    ->first();
                    
                if ($bankRate) {
                    // When converting AZN to foreign currency, we use sell price (bank sells foreign currency)
                    $rate = $operation === 'buy' ? $bankRate->sell_price : $bankRate->buy_price;
                    $usedRate = 'bank';
                }
            }
            
            if (!$rate) {
                // Get latest CBAR rate
                $exchangeRate = ExchangeRate::getRateForCurrency($currency->currency);
                $rate = $exchangeRate ? $exchangeRate->actual_rate : $currency->central_bank_rate;
                $usedRate = 'central_bank';
            }
            
            $result = $amount / $rate;
            
        } elseif ($toCurrency === 'AZN') {
            // Converting from foreign currency to AZN (buying foreign currency)
            $currency = Currency::where('currency', $fromCurrency)
                ->where('status', true)
                ->first();
                
            if (!$currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency not found'
                ], 404);
            }
            
            if ($bankId) {
                $bankRate = BuySellRate::where('currency_id', $currency->id)
                    ->where('company_id', $bankId)
                    ->first();
                    
                if ($bankRate) {
                    // When converting foreign currency to AZN, we use buy price (bank buys foreign currency)
                    $rate = $operation === 'buy' ? $bankRate->buy_price : $bankRate->sell_price;
                    $usedRate = 'bank';
                }
            }
            
            if (!$rate) {
                // Get latest CBAR rate
                $exchangeRate = ExchangeRate::getRateForCurrency($currency->currency);
                $rate = $exchangeRate ? $exchangeRate->actual_rate : $currency->central_bank_rate;
                $usedRate = 'central_bank';
            }
            
            $result = $amount * $rate;
            
        } else {
            // Cross-currency conversion (e.g., USD to EUR)
            $fromCurrencyObj = Currency::where('currency', $fromCurrency)
                ->where('status', true)
                ->first();
            $toCurrencyObj = Currency::where('currency', $toCurrency)
                ->where('status', true)
                ->first();
                
            if (!$fromCurrencyObj || !$toCurrencyObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency not found'
                ], 404);
            }
            
            // Convert through AZN using central bank rates
            $fromExchangeRate = ExchangeRate::getRateForCurrency($fromCurrency);
            $toExchangeRate = ExchangeRate::getRateForCurrency($toCurrency);
            
            $fromRate = $fromExchangeRate ? $fromExchangeRate->actual_rate : $fromCurrencyObj->central_bank_rate;
            $toRate = $toExchangeRate ? $toExchangeRate->actual_rate : $toCurrencyObj->central_bank_rate;
            
            $aznAmount = $amount * $fromRate;
            $result = $aznAmount / $toRate;
            $rate = $fromRate / $toRate;
            $usedRate = 'cross_rate';
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'amount' => $amount,
                'result' => round($result, 4),
                'rate' => round($rate, 4),
                'operation' => $operation,
                'rate_source' => $usedRate
            ]
        ]);
    }
    
    /**
     * Get bank rates for specific currency
     */
    public function bankRates(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $request->validate([
            'currency' => 'required|string'
        ]);
        
        $currency = Currency::where('currency', $request->currency)
            ->where('status', true)
            ->first();
            
        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }
        
        $rates = BuySellRate::where('currency_id', $currency->id)
            ->with(['company' => function($query) {
                $query->where('is_active', true)
                    ->where('company_type_id', 1); // 1 is for banks
            }])
            ->get()
            ->map(function($rate) use ($lang) {
                if (!$rate->company) {
                    return null;
                }

                $bankName = null;
                if ($rate->company->name) {
                    $name = $rate->company->name;
                    // Check if name is JSON-encoded
                    if (is_string($name) && (strpos($name, '{') === 0 || strpos($name, '[') === 0)) {
                        $decoded = json_decode($name, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $bankName = $decoded[$lang] ?? $decoded['az'] ?? $decoded['en'] ?? $name;
                        } else {
                            $bankName = $name;
                        }
                    } else {
                        $bankName = $name;
                    }
                }

                return [
                    'bank_id' => $rate->company_id,
                    'bank_name' => $bankName,
                    'bank_logo' => $rate->company->logo
                        ? asset('storage/' . $rate->company->logo)
                        : null,
                    'buy_price' => $rate->buy_price,
                    'sell_price' => $rate->sell_price,
                    'spread' => round($rate->sell_price - $rate->buy_price, 4)
                ];
            })
            ->filter()
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $currency->currency,
                'central_bank_rate' => $currency->central_bank_rate,
                'bank_rates' => $rates
            ]
        ]);
    }
}