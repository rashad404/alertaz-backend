<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StockController extends Controller
{
    private $cacheTime = 300; // 5 minutes cache

    /**
     * Get simplified list of stocks for dropdowns/selection
     */
    public function getStockList()
    {
        try {
            // Get all stocks from database
            $stocks = StockPrice::orderByRank()
                ->get(['symbol', 'name', 'exchange', 'current_price', 'change_percent'])
                ->map(function ($stock) {
                    return [
                        'symbol' => $stock->symbol,
                        'name' => $stock->name,
                        'exchange' => $stock->exchange,
                        'price' => (float) $stock->current_price,
                        'change_percent' => $stock->change_percent ? (float) $stock->change_percent : null,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $stocks,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock list: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock list',
            ], 500);
        }
    }

    /**
     * Get list of stocks with pagination
     */
    public function getMarkets(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $exchange = $request->get('exchange'); // Filter by exchange (NYSE, NASDAQ)
        $search = $request->get('search'); // Search by symbol or name

        try {
            $query = StockPrice::orderByRank();

            // Filter by exchange if provided
            if ($exchange) {
                $query->where('exchange', strtoupper($exchange));
            }

            // Search by symbol or name
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('symbol', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            // Paginate results
            $stocks = $query->paginate($perPage, ['*'], 'page', $page);

            // Format the data
            $formattedData = $stocks->map(function ($stock) {
                return [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'exchange' => $stock->exchange,
                    'current_price' => (float) $stock->current_price,
                    'open' => $stock->open ? (float) $stock->open : null,
                    'high' => $stock->high ? (float) $stock->high : null,
                    'low' => $stock->low ? (float) $stock->low : null,
                    'previous_close' => $stock->previous_close ? (float) $stock->previous_close : null,
                    'change' => $stock->change ? (float) $stock->change : null,
                    'change_percent' => $stock->change_percent ? (float) $stock->change_percent : null,
                    'volume' => $stock->volume,
                    'market_cap' => $stock->market_cap ? (float) $stock->market_cap : null,
                    'market_cap_rank' => $stock->market_cap_rank,
                    'fifty_two_week_high' => $stock->fifty_two_week_high ? (float) $stock->fifty_two_week_high : null,
                    'fifty_two_week_low' => $stock->fifty_two_week_low ? (float) $stock->fifty_two_week_low : null,
                    'last_updated' => $stock->last_updated ? $stock->last_updated->toIso8601String() : null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedData,
                'pagination' => [
                    'current_page' => $stocks->currentPage(),
                    'last_page' => $stocks->lastPage(),
                    'per_page' => $stocks->perPage(),
                    'total' => $stocks->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock markets: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock markets',
            ], 500);
        }
    }

    /**
     * Get single stock details
     */
    public function getStockDetails(string $symbol)
    {
        try {
            $symbol = strtoupper($symbol);

            // Try cache first
            $cacheKey = "stock_details_{$symbol}";
            $stock = Cache::remember($cacheKey, $this->cacheTime, function () use ($symbol) {
                return StockPrice::where('symbol', $symbol)->first();
            });

            if (!$stock) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'exchange' => $stock->exchange,
                    'current_price' => (float) $stock->current_price,
                    'open' => $stock->open ? (float) $stock->open : null,
                    'high' => $stock->high ? (float) $stock->high : null,
                    'low' => $stock->low ? (float) $stock->low : null,
                    'previous_close' => $stock->previous_close ? (float) $stock->previous_close : null,
                    'change' => $stock->change ? (float) $stock->change : null,
                    'change_percent' => $stock->change_percent ? (float) $stock->change_percent : null,
                    'volume' => $stock->volume,
                    'market_cap' => $stock->market_cap ? (float) $stock->market_cap : null,
                    'market_cap_rank' => $stock->market_cap_rank,
                    'fifty_two_week_high' => $stock->fifty_two_week_high ? (float) $stock->fifty_two_week_high : null,
                    'fifty_two_week_low' => $stock->fifty_two_week_low ? (float) $stock->fifty_two_week_low : null,
                    'last_updated' => $stock->last_updated ? $stock->last_updated->toIso8601String() : null,
                    'formatted_price' => $stock->getFormattedPrice(),
                    'formatted_market_cap' => $stock->market_cap ? $stock->getFormattedMarketCap() : null,
                    'is_price_up' => $stock->isPriceUp(),
                    'is_price_down' => $stock->isPriceDown(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching stock details for {$symbol}: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock details',
            ], 500);
        }
    }

    /**
     * Search stocks by symbol or name
     */
    public function searchStocks(Request $request)
    {
        $query = $request->get('q');

        if (!$query) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search query is required',
            ], 400);
        }

        try {
            $stocks = StockPrice::where('symbol', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orderByRank()
                ->limit(20)
                ->get(['symbol', 'name', 'exchange', 'current_price', 'change_percent'])
                ->map(function ($stock) {
                    return [
                        'symbol' => $stock->symbol,
                        'name' => $stock->name,
                        'exchange' => $stock->exchange,
                        'price' => (float) $stock->current_price,
                        'change_percent' => $stock->change_percent ? (float) $stock->change_percent : null,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $stocks,
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching stocks: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search stocks',
            ], 500);
        }
    }

    /**
     * Get top gainers
     */
    public function getTopGainers(Request $request)
    {
        $limit = $request->get('limit', 10);

        try {
            $gainers = StockPrice::whereNotNull('change_percent')
                ->where('change_percent', '>', 0)
                ->orderBy('change_percent', 'desc')
                ->limit($limit)
                ->get(['symbol', 'name', 'exchange', 'current_price', 'change', 'change_percent'])
                ->map(function ($stock) {
                    return [
                        'symbol' => $stock->symbol,
                        'name' => $stock->name,
                        'exchange' => $stock->exchange,
                        'price' => (float) $stock->current_price,
                        'change' => (float) $stock->change,
                        'change_percent' => (float) $stock->change_percent,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $gainers,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching top gainers: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch top gainers',
            ], 500);
        }
    }

    /**
     * Get top losers
     */
    public function getTopLosers(Request $request)
    {
        $limit = $request->get('limit', 10);

        try {
            $losers = StockPrice::whereNotNull('change_percent')
                ->where('change_percent', '<', 0)
                ->orderBy('change_percent', 'asc')
                ->limit($limit)
                ->get(['symbol', 'name', 'exchange', 'current_price', 'change', 'change_percent'])
                ->map(function ($stock) {
                    return [
                        'symbol' => $stock->symbol,
                        'name' => $stock->name,
                        'exchange' => $stock->exchange,
                        'price' => (float) $stock->current_price,
                        'change' => (float) $stock->change,
                        'change_percent' => (float) $stock->change_percent,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $losers,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching top losers: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch top losers',
            ], 500);
        }
    }
}
