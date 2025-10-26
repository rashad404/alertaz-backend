<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CryptoPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CryptoController extends Controller
{
    private $apiKey;
    private $baseUrl = 'https://api.coingecko.com/api/v3';
    private $cacheTime = 300; // 5 minutes cache (was 60 seconds)

    public function __construct()
    {
        $this->apiKey = config('services.coingecko.api_key');
    }

    /**
     * Get simplified list of cryptocurrencies for dropdowns/selection
     */
    public function getCryptoList()
    {
        try {
            // Get top cryptocurrencies from database (increased to 100 to include more altcoins)
            $cryptos = CryptoPrice::orderByRank()
                ->limit(100)
                ->get(['coin_id', 'symbol', 'name', 'image'])
                ->map(function ($crypto) {
                    return [
                        'id' => $crypto->coin_id,
                        'symbol' => strtoupper($crypto->symbol),
                        'name' => $crypto->name,
                        'image' => $crypto->image,
                    ];
                });

            // If database is empty, return popular cryptos as fallback
            if ($cryptos->isEmpty()) {
                $cryptos = collect([
                    ['id' => 'bitcoin', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'image' => null],
                    ['id' => 'ethereum', 'symbol' => 'ETH', 'name' => 'Ethereum', 'image' => null],
                    ['id' => 'ethereum-classic', 'symbol' => 'ETC', 'name' => 'Ethereum Classic', 'image' => null],
                    ['id' => 'binancecoin', 'symbol' => 'BNB', 'name' => 'BNB', 'image' => null],
                    ['id' => 'ripple', 'symbol' => 'XRP', 'name' => 'XRP', 'image' => null],
                    ['id' => 'cardano', 'symbol' => 'ADA', 'name' => 'Cardano', 'image' => null],
                    ['id' => 'solana', 'symbol' => 'SOL', 'name' => 'Solana', 'image' => null],
                    ['id' => 'dogecoin', 'symbol' => 'DOGE', 'name' => 'Dogecoin', 'image' => null],
                    ['id' => 'polygon', 'symbol' => 'MATIC', 'name' => 'Polygon', 'image' => null],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $cryptos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching crypto list: ' . $e->getMessage());

            // Return fallback list on error
            return response()->json([
                'status' => 'success',
                'data' => [
                    ['id' => 'bitcoin', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'image' => null],
                    ['id' => 'ethereum', 'symbol' => 'ETH', 'name' => 'Ethereum', 'image' => null],
                    ['id' => 'binancecoin', 'symbol' => 'BNB', 'name' => 'BNB', 'image' => null],
                    ['id' => 'ripple', 'symbol' => 'XRP', 'name' => 'XRP', 'image' => null],
                ],
            ]);
        }
    }

    /**
     * Get list of top cryptocurrencies
     */
    public function getMarkets(Request $request)
    {
        $requestedCurrency = $request->get('vs_currency', 'azn');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $sparkline = $request->get('sparkline', true);
        
        try {
            // Get data from database
            $query = CryptoPrice::orderByRank();
            
            // Paginate results
            $coins = $query->paginate($perPage, ['*'], 'page', $page);
            
            // Check if we have data
            if ($coins->isEmpty()) {
                // If database is empty, try to fetch from API once
                $this->fetchAndStoreFromApi();
                $coins = CryptoPrice::orderByRank()->paginate($perPage, ['*'], 'page', $page);
            }
            
            // Transform data for response
            $data = $coins->map(function ($coin) use ($requestedCurrency, $sparkline) {
                $price = (float) $coin->current_price;
                $marketCap = (float) $coin->market_cap;
                $volume = (float) $coin->total_volume;
                $high24h = $coin->high_24h ? (float) $coin->high_24h : null;
                $low24h = $coin->low_24h ? (float) $coin->low_24h : null;
                $priceChange24h = $coin->price_change_24h ? (float) $coin->price_change_24h : null;
                
                // Convert to AZN if requested
                if ($requestedCurrency === 'azn') {
                    $aznRate = 1.7;
                    $price = $price * $aznRate;
                    $marketCap = $marketCap * $aznRate;
                    $volume = $volume * $aznRate;
                    $high24h = $high24h ? $high24h * $aznRate : null;
                    $low24h = $low24h ? $low24h * $aznRate : null;
                    $priceChange24h = $priceChange24h ? $priceChange24h * $aznRate : null;
                }
                
                return [
                    'id' => $coin->coin_id,
                    'symbol' => $coin->symbol,
                    'name' => $coin->name,
                    'image' => $coin->image,
                    'current_price' => $price,
                    'market_cap' => $marketCap,
                    'market_cap_rank' => $coin->market_cap_rank,
                    'total_volume' => $volume,
                    'high_24h' => $high24h,
                    'low_24h' => $low24h,
                    'price_change_24h' => $priceChange24h,
                    'price_change_percentage_24h' => $coin->price_change_percentage_24h ? (float) $coin->price_change_percentage_24h : null,
                    'price_change_percentage_1h_in_currency' => $coin->price_change_percentage_1h ? (float) $coin->price_change_percentage_1h : null,
                    'price_change_percentage_7d_in_currency' => $coin->price_change_percentage_7d ? (float) $coin->price_change_percentage_7d : null,
                    'price_change_percentage_30d_in_currency' => $coin->price_change_percentage_30d ? (float) $coin->price_change_percentage_30d : null,
                    'circulating_supply' => $coin->circulating_supply ? (float) $coin->circulating_supply : null,
                    'total_supply' => $coin->total_supply ? (float) $coin->total_supply : null,
                    'max_supply' => $coin->max_supply ? (float) $coin->max_supply : null,
                    'sparkline_in_7d' => $sparkline ? ['price' => $coin->sparkline_7d] : null,
                    'popular_in_azerbaijan' => $coin->popular_in_azerbaijan,
                    'currency' => $requestedCurrency,
                    'formatted_price' => $this->formatPrice($price, $requestedCurrency),
                    'formatted_market_cap' => $this->formatLargeNumber($marketCap, $requestedCurrency),
                    'formatted_volume' => $this->formatLargeNumber($volume, $requestedCurrency),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'currency' => $requestedCurrency,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $coins->total(),
                'last_updated' => CryptoPrice::max('last_updated'),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching crypto prices from database: ' . $e->getMessage());
            
            // Fallback to mock data if database fails
            return $this->getMockMarketData($requestedCurrency, $page, $perPage, $sparkline);
        }
    }
    
    /**
     * Fetch and store data from API (called when database is empty)
     */
    private function fetchAndStoreFromApi()
    {
        if (empty($this->apiKey)) {
            return;
        }
        
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/coins/markets", [
                    'vs_currency' => 'usd',
                    'order' => 'market_cap_desc',
                    'per_page' => 50,
                    'page' => 1,
                    'sparkline' => true,
                    'price_change_percentage' => '1h,24h,7d,30d',
                ]);

            if ($response->successful()) {
                $coins = $response->json();
                $popularCoins = ['btc', 'eth', 'usdt', 'bnb', 'usdc', 'xrp'];
                
                foreach ($coins as $coin) {
                    CryptoPrice::updateOrCreate(
                        ['coin_id' => $coin['id']],
                        [
                            'symbol' => $coin['symbol'],
                            'name' => $coin['name'],
                            'image' => $coin['image'] ?? null,
                            'current_price' => $coin['current_price'] ?? 0,
                            'market_cap' => $coin['market_cap'] ?? 0,
                            'market_cap_rank' => $coin['market_cap_rank'] ?? null,
                            'total_volume' => $coin['total_volume'] ?? 0,
                            'high_24h' => $coin['high_24h'] ?? null,
                            'low_24h' => $coin['low_24h'] ?? null,
                            'price_change_24h' => $coin['price_change_24h'] ?? null,
                            'price_change_percentage_24h' => $coin['price_change_percentage_24h'] ?? null,
                            'price_change_percentage_1h' => $coin['price_change_percentage_1h_in_currency'] ?? null,
                            'price_change_percentage_7d' => $coin['price_change_percentage_7d_in_currency'] ?? null,
                            'price_change_percentage_30d' => $coin['price_change_percentage_30d_in_currency'] ?? null,
                            'circulating_supply' => $coin['circulating_supply'] ?? null,
                            'total_supply' => $coin['total_supply'] ?? null,
                            'max_supply' => $coin['max_supply'] ?? null,
                            'sparkline_7d' => isset($coin['sparkline_in_7d']) ? $coin['sparkline_in_7d']['price'] : null,
                            'popular_in_azerbaijan' => in_array($coin['symbol'], $popularCoins),
                            'last_updated' => now(),
                        ]
                    );
                }
                
                Log::info('Fetched and stored crypto prices from API');
            }
        } catch (\Exception $e) {
            Log::error('Error fetching from CoinGecko API: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed information about a specific coin
     */
    public function getCoinDetails($locale = null, $id = null, Request $request)
    {
        // Handle when locale is not provided (id becomes first parameter)
        if ($request->route()->parameters() && count($request->route()->parameters()) == 1) {
            $id = $locale;
            $locale = 'az';
        }
        
        $currency = $request->get('vs_currency', 'azn');
        
        // Check if API key is available
        $useMockData = empty($this->apiKey);
        
        if ($useMockData) {
            Log::warning('CoinGecko API key not found, using mock data for coin details');
            return $this->getMockCoinDetails($id, $currency);
        }
        
        $cacheKey = "crypto_coin_{$id}_{$currency}";
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($id, $currency) {
            try {
                Log::info("Fetching coin details for: {$id}, currency: {$currency}");
                
                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/coins/{$id}", [
                        'localization' => false,
                        'tickers' => false,
                        'market_data' => true,
                        'community_data' => false,
                        'developer_data' => false,
                        'sparkline' => true,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Process market data for Azerbaijani users
                    $marketData = $data['market_data'] ?? [];
                    
                    $processedData = [
                        'id' => $data['id'],
                        'symbol' => $data['symbol'],
                        'name' => $data['name'],
                        'image' => $data['image']['large'] ?? '',
                        'description' => $data['description']['en'] ?? '',
                        'market_data' => [
                            'current_price' => [
                                'azn' => $marketData['current_price']['azn'] ?? 0,
                                'usd' => $marketData['current_price']['usd'] ?? 0,
                            ],
                            'market_cap' => [
                                'azn' => $marketData['market_cap']['azn'] ?? 0,
                                'usd' => $marketData['market_cap']['usd'] ?? 0,
                            ],
                            'total_volume' => [
                                'azn' => $marketData['total_volume']['azn'] ?? 0,
                                'usd' => $marketData['total_volume']['usd'] ?? 0,
                            ],
                            'high_24h' => [
                                'azn' => $marketData['high_24h']['azn'] ?? 0,
                                'usd' => $marketData['high_24h']['usd'] ?? 0,
                            ],
                            'low_24h' => [
                                'azn' => $marketData['low_24h']['azn'] ?? 0,
                                'usd' => $marketData['low_24h']['usd'] ?? 0,
                            ],
                            'price_change_percentage_1h' => $marketData['price_change_percentage_1h_in_currency']['azn'] ?? 0,
                            'price_change_percentage_24h' => $marketData['price_change_percentage_24h'] ?? 0,
                            'price_change_percentage_7d' => $marketData['price_change_percentage_7d'] ?? 0,
                            'price_change_percentage_30d' => $marketData['price_change_percentage_30d'] ?? 0,
                            'circulating_supply' => $marketData['circulating_supply'] ?? 0,
                            'total_supply' => $marketData['total_supply'] ?? 0,
                            'max_supply' => $marketData['max_supply'] ?? null,
                        ],
                        'sparkline_7d' => $data['market_data']['sparkline_7d']['price'] ?? [],
                        'local_info' => $this->getLocalExchangeInfo($data['symbol']),
                    ];
                    
                    return response()->json([
                        'success' => true,
                        'data' => $processedData,
                        'currency' => $currency,
                    ]);
                }

                Log::warning("Coin not found or API returned error for: {$id}, status: " . $response->status());
                return response()->json([
                    'success' => false,
                    'message' => 'Coin not found'
                ], 404);
            } catch (\Exception $e) {
                Log::error('CoinGecko API error for coin ' . $id . ': ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'API request failed'
                ], 500);
            }
        });
    }

    /**
     * Get OHLC chart data for a coin
     */
    public function getOHLCData($locale = null, $id = null, Request $request)
    {
        // Handle when locale is not provided (id becomes first parameter)
        if ($request->route()->parameters() && count($request->route()->parameters()) == 1) {
            $id = $locale;
            $locale = 'az';
        }
        
        $currency = $request->get('vs_currency', 'azn');
        $days = $request->get('days', 7);
        
        // Check if API key is available
        $useMockData = empty($this->apiKey);
        
        if ($useMockData) {
            Log::warning('CoinGecko API key not found, using mock OHLC data');
            return $this->getMockOHLCData($id, $currency, $days);
        }
        
        $cacheKey = "crypto_ohlc_{$id}_{$currency}_{$days}";
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($id, $currency, $days) {
            try {
                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/coins/{$id}/ohlc", [
                        'vs_currency' => $currency,
                        'days' => $days,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Format for TradingView Lightweight Charts
                    $formattedData = array_map(function ($item) {
                        return [
                            'time' => $item[0] / 1000, // Convert to seconds
                            'open' => $item[1],
                            'high' => $item[2],
                            'low' => $item[3],
                            'close' => $item[4],
                        ];
                    }, $data);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $formattedData,
                        'currency' => $currency,
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch OHLC data'
                ], 500);
            } catch (\Exception $e) {
                Log::error('CoinGecko API error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'API request failed'
                ], 500);
            }
        });
    }

    /**
     * Get exchange rates (AZN to USD, etc.)
     */
    public function getExchangeRates()
    {
        $cacheKey = 'exchange_rates';
        
        return Cache::remember($cacheKey, $this->cacheTime, function () {
            try {
                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/simple/price", [
                        'ids' => 'bitcoin,ethereum',
                        'vs_currencies' => 'azn,usd',
                        'include_24hr_change' => true,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Get USD/AZN rate from Central Bank or use approximate
                    $usdToAzn = 1.7; // This should be fetched from CBAR API
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'rates' => $data,
                            'usd_to_azn' => $usdToAzn,
                            'gold_price_azn' => $this->getGoldPriceAZN(),
                        ],
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch exchange rates'
                ], 500);
            } catch (\Exception $e) {
                Log::error('Exchange rate API error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'API request failed'
                ], 500);
            }
        });
    }

    /**
     * Search cryptocurrencies
     */
    public function searchCoins(Request $request)
    {
        $query = $request->get('query', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Query must be at least 2 characters'
            ], 400);
        }
        
        $cacheKey = "crypto_search_" . md5($query);
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($query) {
            try {
                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/search", [
                        'query' => $query,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Add Azerbaijani translations for common coins
                    $translations = [
                        'bitcoin' => 'Bitkoin',
                        'ethereum' => 'Ethereum',
                        'tether' => 'Tether',
                        'binancecoin' => 'Binance Coin',
                    ];
                    
                    $coins = array_map(function ($coin) use ($translations) {
                        return [
                            'id' => $coin['id'],
                            'name' => $coin['name'],
                            'symbol' => $coin['symbol'],
                            'name_az' => $translations[$coin['id']] ?? $coin['name'],
                            'thumb' => $coin['thumb'],
                        ];
                    }, array_slice($data['coins'], 0, 10));
                    
                    return response()->json([
                        'success' => true,
                        'data' => $coins,
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Search failed'
                ], 500);
            } catch (\Exception $e) {
                Log::error('Search API error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Search request failed'
                ], 500);
            }
        });
    }

    /**
     * Get trending coins
     */
    public function getTrending()
    {
        $cacheKey = 'crypto_trending';
        
        return Cache::remember($cacheKey, 1800, function () { // 30 minutes for trending
            try {
                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/search/trending");

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'data' => $response->json(),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch trending coins'
                ], 500);
            } catch (\Exception $e) {
                Log::error('Trending API error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'API request failed'
                ], 500);
            }
        });
    }

    /**
     * Get local exchange information for Azerbaijan
     */
    private function getLocalExchangeInfo($symbol)
    {
        $localExchanges = [
            'btc' => [
                'available' => true,
                'exchanges' => ['Binance', 'LocalBitcoins', 'P2P platformalar'],
                'buying_guide' => 'Bank kartı ilə Binance P2P vasitəsilə ala bilərsiniz.',
            ],
            'eth' => [
                'available' => true,
                'exchanges' => ['Binance', 'KuCoin'],
                'buying_guide' => 'Binance və ya KuCoin birjalarından ala bilərsiniz.',
            ],
            'usdt' => [
                'available' => true,
                'exchanges' => ['Binance P2P', 'LocalCryptos'],
                'buying_guide' => 'Ən asan yol Binance P2P ilə AZN qarşılığı almaqdır.',
            ],
            'bnb' => [
                'available' => true,
                'exchanges' => ['Binance'],
                'buying_guide' => 'Binance birjasından birbaşa ala bilərsiniz.',
            ],
            'default' => [
                'available' => false,
                'exchanges' => [],
                'buying_guide' => 'Bu kriptovalyutanı almaq üçün əvvəlcə USDT alıb, sonra dəyişdirə bilərsiniz.',
            ],
        ];
        
        return $localExchanges[strtolower($symbol)] ?? $localExchanges['default'];
    }

    /**
     * Get gold price in AZN (for comparison)
     */
    private function getGoldPriceAZN()
    {
        // This should be fetched from a local API or hardcoded based on current rates
        // For now, using approximate value
        return 85; // AZN per gram
    }

    /**
     * Format price with currency symbol
     */
    private function formatPrice($price, $currency = 'azn')
    {
        if ($currency === 'azn') {
            return '₼' . number_format($price, 2, '.', ',');
        }
        return '$' . number_format($price, 2, '.', ',');
    }

    /**
     * Format large numbers (market cap, volume)
     */
    private function formatLargeNumber($number, $currency = 'azn')
    {
        $symbol = $currency === 'azn' ? '₼' : '$';
        
        if ($number >= 1000000000) {
            return $symbol . number_format($number / 1000000000, 2) . ' mlrd';
        } elseif ($number >= 1000000) {
            return $symbol . number_format($number / 1000000, 2) . ' mln';
        } elseif ($number >= 1000) {
            return $symbol . number_format($number / 1000, 2) . ' min';
        }
        
        return $symbol . number_format($number, 2);
    }

    /**
     * Get headers for API requests
     */
    private function getHeaders()
    {
        $headers = [
            'Accept' => 'application/json',
        ];
        
        // Use the API key from environment
        if ($this->apiKey) {
            // CoinGecko uses x-cg-demo-api-key for demo/free tier
            // and x-cg-pro-api-key for pro tier
            $headers['x-cg-demo-api-key'] = $this->apiKey;
        }
        
        return $headers;
    }
    
    /**
     * Get mock market data for development
     */
    private function getMockMarketData($currency, $page, $perPage, $sparkline)
    {
        $aznRate = 1.7;
        
        // Mock data for top cryptocurrencies (realistic USD prices as of 2025)
        $allCoins = [
            [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'image' => 'https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png',
                'current_price' => 110234.00,  // Realistic BTC price ~$110k
                'market_cap' => 2165456789000,
                'market_cap_rank' => 1,
                'total_volume' => 38456789000,
                'high_24h' => 112543.00,
                'low_24h' => 108321.00,
                'price_change_24h' => 2134.00,
                'price_change_percentage_24h' => 2.45,
                'price_change_percentage_1h_in_currency' => 0.34,
                'price_change_percentage_7d_in_currency' => 5.67,
                'price_change_percentage_30d_in_currency' => 12.34,
                'circulating_supply' => 19600000,
                'total_supply' => 21000000,
                'max_supply' => 21000000,
                'sparkline_in_7d' => $sparkline ? ['price' => $this->generateSparkline()] : null,
            ],
            [
                'id' => 'ethereum',
                'symbol' => 'eth',
                'name' => 'Ethereum',
                'image' => 'https://coin-images.coingecko.com/coins/images/279/large/ethereum.png',
                'current_price' => 3856.78,  // Realistic ETH price ~$3.8k
                'market_cap' => 463678900000,
                'market_cap_rank' => 2,
                'total_volume' => 18678900000,
                'high_24h' => 3967.89,
                'low_24h' => 3745.67,
                'price_change_24h' => 109.12,
                'price_change_percentage_24h' => 3.21,
                'price_change_percentage_1h_in_currency' => -0.12,
                'price_change_percentage_7d_in_currency' => 8.90,
                'price_change_percentage_30d_in_currency' => 15.67,
                'circulating_supply' => 120200000,
                'total_supply' => 120200000,
                'max_supply' => null,
                'sparkline_in_7d' => $sparkline ? ['price' => $this->generateSparkline()] : null,
            ],
            [
                'id' => 'tether',
                'symbol' => 'usdt',
                'name' => 'Tether',
                'image' => 'https://coin-images.coingecko.com/coins/images/325/large/Tether.png',
                'current_price' => 1.00,
                'market_cap' => 95678900000,
                'market_cap_rank' => 3,
                'total_volume' => 45678900000,
                'high_24h' => 1.01,
                'low_24h' => 0.99,
                'price_change_24h' => 0.00,
                'price_change_percentage_24h' => 0.01,
                'price_change_percentage_1h_in_currency' => 0.00,
                'price_change_percentage_7d_in_currency' => 0.02,
                'price_change_percentage_30d_in_currency' => -0.01,
                'circulating_supply' => 95678900000,
                'total_supply' => 95678900000,
                'max_supply' => null,
                'sparkline_in_7d' => $sparkline ? ['price' => $this->generateSparkline(1, 0.02)] : null,
            ],
            [
                'id' => 'binancecoin',
                'symbol' => 'bnb',
                'name' => 'BNB',
                'image' => 'https://coin-images.coingecko.com/coins/images/825/large/bnb-icon2_2x.png',
                'current_price' => 687.89,  // Realistic BNB price ~$688
                'market_cap' => 106154321000,
                'market_cap_rank' => 4,
                'total_volume' => 1834567890,
                'high_24h' => 698.90,
                'low_24h' => 676.78,
                'price_change_24h' => 15.34,
                'price_change_percentage_24h' => 2.23,
                'price_change_percentage_1h_in_currency' => 0.56,
                'price_change_percentage_7d_in_currency' => 4.56,
                'price_change_percentage_30d_in_currency' => 9.87,
                'circulating_supply' => 154400000,
                'total_supply' => 154400000,
                'max_supply' => 200000000,
                'sparkline_in_7d' => $sparkline ? ['price' => $this->generateSparkline()] : null,
            ],
            [
                'id' => 'solana',
                'symbol' => 'sol',
                'name' => 'Solana',
                'image' => 'https://coin-images.coingecko.com/coins/images/4128/large/solana.png',
                'current_price' => 256.78,  // Realistic SOL price ~$257
                'market_cap' => 116634567890,
                'market_cap_rank' => 5,
                'total_volume' => 3845678901,
                'high_24h' => 262.34,
                'low_24h' => 251.23,
                'price_change_24h' => 6.56,
                'price_change_percentage_24h' => 3.00,
                'price_change_percentage_1h_in_currency' => 1.23,
                'price_change_percentage_7d_in_currency' => 12.34,
                'price_change_percentage_30d_in_currency' => 25.67,
                'circulating_supply' => 454000000,
                'total_supply' => 567000000,
                'max_supply' => null,
                'sparkline_in_7d' => $sparkline ? ['price' => $this->generateSparkline()] : null,
            ],
        ];
        
        // Add more mock coins for pagination
        for ($i = 6; $i <= 100; $i++) {
            $price = rand(1, 1000) + rand(0, 99) / 100;
            $allCoins[] = [
                'id' => 'coin-' . $i,
                'symbol' => 'coin' . $i,
                'name' => 'Crypto Coin ' . $i,
                'image' => 'https://via.placeholder.com/150',
                'current_price' => $price,
                'market_cap' => $price * rand(1000000, 100000000),
                'market_cap_rank' => $i,
                'total_volume' => rand(1000000, 10000000),
                'high_24h' => $price * 1.1,
                'low_24h' => $price * 0.9,
                'price_change_24h' => $price * (rand(-10, 10) / 100),
                'price_change_percentage_24h' => rand(-10, 10) + rand(0, 99) / 100,
                'price_change_percentage_1h_in_currency' => rand(-5, 5) + rand(0, 99) / 100,
                'price_change_percentage_7d_in_currency' => rand(-20, 20) + rand(0, 99) / 100,
                'price_change_percentage_30d_in_currency' => rand(-30, 30) + rand(0, 99) / 100,
                'circulating_supply' => rand(1000000, 100000000),
                'total_supply' => rand(1000000, 200000000),
                'max_supply' => rand(0, 1) ? rand(100000000, 500000000) : null,
                'sparkline_in_7d' => $sparkline ? ['price' => $this->generateSparkline()] : null,
            ];
        }
        
        // Paginate results
        $start = ($page - 1) * $perPage;
        $paginatedCoins = array_slice($allCoins, $start, $perPage);
        
        // Convert to AZN if requested
        if ($currency === 'azn') {
            $paginatedCoins = array_map(function ($coin) use ($aznRate) {
                $coin['current_price'] = $coin['current_price'] * $aznRate;
                $coin['market_cap'] = $coin['market_cap'] * $aznRate;
                $coin['total_volume'] = $coin['total_volume'] * $aznRate;
                $coin['high_24h'] = $coin['high_24h'] * $aznRate;
                $coin['low_24h'] = $coin['low_24h'] * $aznRate;
                $coin['price_change_24h'] = $coin['price_change_24h'] * $aznRate;
                return $coin;
            }, $paginatedCoins);
        }
        
        // Add Azerbaijan-specific data
        $processedData = array_map(function ($coin) use ($currency) {
            $popularInAzerbaijan = in_array($coin['symbol'], ['btc', 'eth', 'usdt', 'bnb', 'usdc']);
            
            return array_merge($coin, [
                'popular_in_azerbaijan' => $popularInAzerbaijan,
                'currency' => $currency,
                'formatted_price' => $this->formatPrice($coin['current_price'], $currency),
                'formatted_market_cap' => $this->formatLargeNumber($coin['market_cap'], $currency),
                'formatted_volume' => $this->formatLargeNumber($coin['total_volume'], $currency),
            ]);
        }, $paginatedCoins);
        
        return response()->json([
            'success' => true,
            'data' => $processedData,
            'currency' => $currency,
            'page' => $page,
            'per_page' => $perPage,
            'is_mock_data' => true, // Flag to indicate this is mock data
        ]);
    }
    
    /**
     * Generate mock sparkline data
     */
    private function generateSparkline($baseValue = 100, $volatility = 10)
    {
        $points = [];
        $value = $baseValue;
        
        for ($i = 0; $i < 168; $i++) { // 7 days * 24 hours
            $change = (rand(-100, 100) / 100) * $volatility;
            $value = $value + ($value * $change / 100);
            $points[] = $value;
        }
        
        return $points;
    }
    
    /**
     * Get mock coin details for development
     */
    private function getMockCoinDetails($id, $currency)
    {
        $aznRate = 1.7;
        
        // Mock data for specific coins (realistic prices)
        $mockCoins = [
            'bitcoin' => [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'image' => 'https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png',
                'description' => 'Bitcoin is the first successful internet money based on peer-to-peer technology.',
                'current_price_usd' => 110234.00,  // Realistic BTC price
                'market_cap_usd' => 2165456789000,
                'total_volume_usd' => 38456789000,
                'high_24h_usd' => 112543.00,
                'low_24h_usd' => 108321.00,
                'price_change_percentage_1h' => 0.34,
                'price_change_percentage_24h' => 2.45,
                'price_change_percentage_7d' => 5.67,
                'price_change_percentage_30d' => 12.34,
                'circulating_supply' => 19600000,
                'total_supply' => 21000000,
                'max_supply' => 21000000,
            ],
            'ethereum' => [
                'id' => 'ethereum',
                'symbol' => 'eth',
                'name' => 'Ethereum',
                'image' => 'https://coin-images.coingecko.com/coins/images/279/large/ethereum.png',
                'description' => 'Ethereum is a decentralized platform that runs smart contracts.',
                'current_price_usd' => 3856.78,  // Realistic ETH price
                'market_cap_usd' => 463678900000,
                'total_volume_usd' => 18678900000,
                'high_24h_usd' => 3967.89,
                'low_24h_usd' => 3745.67,
                'price_change_percentage_1h' => -0.12,
                'price_change_percentage_24h' => 3.21,
                'price_change_percentage_7d' => 8.90,
                'price_change_percentage_30d' => 15.67,
                'circulating_supply' => 120200000,
                'total_supply' => 120200000,
                'max_supply' => null,
            ],
        ];
        
        // Default mock data for unknown coins
        $defaultCoin = [
            'id' => $id,
            'symbol' => substr($id, 0, 3),
            'name' => ucfirst($id),
            'image' => 'https://via.placeholder.com/150',
            'description' => 'A cryptocurrency token.',
            'current_price_usd' => rand(1, 1000) + rand(0, 99) / 100,
            'market_cap_usd' => rand(1000000, 100000000),
            'total_volume_usd' => rand(100000, 10000000),
            'high_24h_usd' => rand(1, 1000) + rand(0, 99) / 100,
            'low_24h_usd' => rand(1, 1000) + rand(0, 99) / 100,
            'price_change_percentage_1h' => rand(-5, 5) + rand(0, 99) / 100,
            'price_change_percentage_24h' => rand(-10, 10) + rand(0, 99) / 100,
            'price_change_percentage_7d' => rand(-20, 20) + rand(0, 99) / 100,
            'price_change_percentage_30d' => rand(-30, 30) + rand(0, 99) / 100,
            'circulating_supply' => rand(1000000, 100000000),
            'total_supply' => rand(1000000, 200000000),
            'max_supply' => rand(0, 1) ? rand(100000000, 500000000) : null,
        ];
        
        $coinData = $mockCoins[$id] ?? $defaultCoin;
        
        // Convert prices to AZN if needed
        $currentPriceAzn = $coinData['current_price_usd'] * $aznRate;
        $marketCapAzn = $coinData['market_cap_usd'] * $aznRate;
        $volumeAzn = $coinData['total_volume_usd'] * $aznRate;
        $high24hAzn = $coinData['high_24h_usd'] * $aznRate;
        $low24hAzn = $coinData['low_24h_usd'] * $aznRate;
        
        $processedData = [
            'id' => $coinData['id'],
            'symbol' => $coinData['symbol'],
            'name' => $coinData['name'],
            'image' => $coinData['image'],
            'description' => $coinData['description'],
            'market_data' => [
                'current_price' => [
                    'azn' => $currentPriceAzn,
                    'usd' => $coinData['current_price_usd'],
                ],
                'market_cap' => [
                    'azn' => $marketCapAzn,
                    'usd' => $coinData['market_cap_usd'],
                ],
                'total_volume' => [
                    'azn' => $volumeAzn,
                    'usd' => $coinData['total_volume_usd'],
                ],
                'high_24h' => [
                    'azn' => $high24hAzn,
                    'usd' => $coinData['high_24h_usd'],
                ],
                'low_24h' => [
                    'azn' => $low24hAzn,
                    'usd' => $coinData['low_24h_usd'],
                ],
                'price_change_percentage_1h' => $coinData['price_change_percentage_1h'],
                'price_change_percentage_24h' => $coinData['price_change_percentage_24h'],
                'price_change_percentage_7d' => $coinData['price_change_percentage_7d'],
                'price_change_percentage_30d' => $coinData['price_change_percentage_30d'],
                'circulating_supply' => $coinData['circulating_supply'],
                'total_supply' => $coinData['total_supply'],
                'max_supply' => $coinData['max_supply'],
            ],
            'sparkline_7d' => $this->generateSparkline(),
            'local_info' => $this->getLocalExchangeInfo($coinData['symbol']),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $processedData,
            'currency' => $currency,
            'is_mock_data' => true,
        ]);
    }
    
    /**
     * Get mock OHLC data for development
     */
    private function getMockOHLCData($id, $currency, $days)
    {
        $aznRate = 1.7;
        $dataPoints = [];
        
        // Base prices for known coins (realistic USD prices)
        $basePrices = [
            'bitcoin' => 110234.00,  // ~$110k
            'ethereum' => 3856.78,   // ~$3.8k
            'tether' => 1.00,
            'binancecoin' => 687.89,  // ~$688
            'solana' => 256.78,       // ~$257
        ];
        
        $basePrice = $basePrices[$id] ?? rand(10, 1000);
        
        // Convert to AZN if needed
        if ($currency === 'azn') {
            $basePrice = $basePrice * $aznRate;
        }
        
        // Generate OHLC data points
        $pointsPerDay = 24; // One candle per hour
        $totalPoints = min($days * $pointsPerDay, 365 * $pointsPerDay);
        
        $currentTime = time();
        $interval = 3600; // 1 hour in seconds
        
        for ($i = $totalPoints - 1; $i >= 0; $i--) {
            $timestamp = $currentTime - ($i * $interval);
            
            // Generate random OHLC values around base price
            $volatility = 0.02; // 2% volatility
            $open = $basePrice * (1 + (rand(-100, 100) / 10000) * $volatility);
            $close = $basePrice * (1 + (rand(-100, 100) / 10000) * $volatility);
            $high = max($open, $close) * (1 + (rand(0, 100) / 10000) * $volatility);
            $low = min($open, $close) * (1 - (rand(0, 100) / 10000) * $volatility);
            
            // Adjust base price for trend
            $basePrice = $close;
            
            $dataPoints[] = [
                'time' => $timestamp,
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $dataPoints,
            'currency' => $currency,
            'is_mock_data' => true,
        ]);
    }
}