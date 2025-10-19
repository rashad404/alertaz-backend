<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\Bank;
use App\Models\CreditType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreditController extends Controller
{
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $perPage = $request->get('per_page', 12);
        $creditTypeSlug = $request->get('type');
        $minAmount = $request->get('min_amount');
        $maxAmount = $request->get('max_amount');
        $minRate = $request->get('min_rate');
        $maxRate = $request->get('max_rate');
        $bank = $request->get('bank');
        $sortBy = $request->get('sort', 'popular'); // popular, rate_low, rate_high, amount_high
        
        $query = Credit::with('creditType')->where('status', true);
        
        // Filter by credit type slug
        if ($creditTypeSlug) {
            // Find credit type by slug
            $creditType = CreditType::where('slug', $creditTypeSlug)->first();
            if ($creditType) {
                $query->where('credit_type_id', $creditType->id);
            }
        }
        
        // Filter by amount range
        if ($minAmount) {
            $query->where('credit_amount', '>=', $minAmount);
        }
        if ($maxAmount) {
            $query->where('credit_amount', '<=', $maxAmount);
        }
        
        // Filter by interest rate
        if ($minRate) {
            $query->where('interest_rate', '>=', $minRate);
        }
        if ($maxRate) {
            $query->where('interest_rate', '<=', $maxRate);
        }
        
        // Filter by bank
        if ($bank) {
            $query->where('bank_name', 'LIKE', '%' . $bank . '%');
        }
        
        // Sorting
        switch ($sortBy) {
            case 'rate_low':
                $query->orderBy('interest_rate', 'asc');
                break;
            case 'rate_high':
                $query->orderBy('interest_rate', 'desc');
                break;
            case 'amount_high':
                $query->orderBy('credit_amount', 'desc');
                break;
            case 'popular':
            default:
                $query->orderBy('views', 'desc');
                break;
        }
        
        $credits = $query->orderBy('order')->paginate($perPage);
        
        $lang = $lang ?? app()->getLocale();
        
        $credits->getCollection()->transform(function ($item) use ($lang) {
            // Generate slug if not exists
            $slug = Str::slug($item->getTranslation('credit_name', 'en') ?: $item->getTranslation('credit_name', 'az')) . '-' . $item->id;
            
            return [
                'id' => $item->id,
                'slug' => $slug,
                'bank_name' => $item->bank_name,
                'credit_name' => $item->getTranslation('credit_name', $lang),
                'credit_image' => $item->credit_image ? asset('storage/' . $item->credit_image) : null,
                'about' => $item->getTranslation('about', $lang),
                'credit_type' => $item->creditType ? $item->creditType->getTranslation('name', $lang) : null,
                'credit_type_slug' => $item->creditType ? $item->creditType->slug : null,
                'credit_amount' => $item->credit_amount,
                'credit_amount_formatted' => number_format($item->credit_amount, 0, ',', ' ') . ' AZN',
                'min_amount' => $item->min_amount ?? 1000,
                'min_amount_formatted' => number_format($item->min_amount ?? 1000, 0, ',', ' ') . ' AZN',
                'max_amount' => $item->max_amount ?? $item->credit_amount,
                'max_amount_formatted' => number_format($item->max_amount ?? $item->credit_amount, 0, ',', ' ') . ' AZN',
                'credit_term' => $item->credit_term,
                'credit_term_formatted' => $item->credit_term . ' ' . ($lang === 'az' ? 'ay' : ($lang === 'ru' ? 'месяцев' : 'months')),
                'min_term_months' => $item->min_term_months ?? 6,
                'max_term_months' => $item->max_term_months ?? $item->credit_term,
                'interest_rate' => $item->interest_rate,
                'interest_rate_formatted' => $item->interest_rate . '%',
                'commission_rate' => $item->commission_rate ?? 0,
                'guarantor' => $item->getTranslation('guarantor', $lang),
                'collateral' => $item->getTranslation('collateral', $lang),
                'method_of_purchase' => $item->getTranslation('method_of_purchase', $lang),
                'description' => $item->getTranslation('about', $lang),
                'bank_phone' => $item->bank_phone ?? '+994 12 310 0310',
                'bank_address' => $item->bank_address ?? 'Baku, Azerbaijan',
                'views' => $item->views,
                'monthly_payment' => $this->calculateMonthlyPayment($item->credit_amount, $item->interest_rate, $item->credit_term),
            ];
        });
        
        // Get credit types for filter
        $creditTypes = CreditType::active()
            ->ordered()
            ->get()
            ->map(function ($type) use ($lang) {
                return [
                    'slug' => $type->slug,
                    'name' => $type->getTranslation('name', $lang)
                ];
            });
        
        // Get banks for filter
        $banks = Credit::where('status', true)
            ->pluck('bank_name')
            ->unique()
            ->values();
        
        return response()->json([
            'credits' => $credits,
            'filters' => [
                'credit_types' => $creditTypes,
                'banks' => $banks,
                'amount_range' => [
                    'min' => Credit::where('status', true)->min('credit_amount') ?? 0,
                    'max' => Credit::where('status', true)->max('credit_amount') ?? 100000
                ],
                'rate_range' => [
                    'min' => Credit::where('status', true)->min('interest_rate') ?? 0,
                    'max' => Credit::where('status', true)->max('interest_rate') ?? 30
                ]
            ]
        ]);
    }
    
    public function show($lang = null, $slug)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        // Extract ID from slug (format: credit-name-123)
        $parts = explode('-', $slug);
        $id = end($parts);
        
        $credit = Credit::with('creditType')->find($id);
        
        if (!$credit) {
            return response()->json(['message' => 'Credit not found'], 404);
        }
        
        // Increment views
        $credit->increment('views');
        
        $lang = $lang ?? app()->getLocale();
        
        // Generate proper slug
        $properSlug = Str::slug($credit->getTranslation('credit_name', 'en') ?: $credit->getTranslation('credit_name', 'az')) . '-' . $credit->id;
        
        $data = [
            'id' => $credit->id,
            'slug' => $properSlug,
            'bank_name' => $credit->bank_name,
            'credit_name' => $credit->getTranslation('credit_name', $lang),
            'credit_image' => $credit->credit_image ? asset('storage/' . $credit->credit_image) : null,
            'about' => $credit->getTranslation('about', $lang),
            'credit_type' => $credit->creditType ? $credit->creditType->getTranslation('name', $lang) : null,
            'credit_type_slug' => $credit->creditType ? $credit->creditType->slug : null,
            'credit_amount' => $credit->credit_amount,
            'credit_amount_formatted' => number_format($credit->credit_amount, 0, ',', ' ') . ' AZN',
            'min_amount' => $credit->min_amount ?? 1000,
            'min_amount_formatted' => number_format($credit->min_amount ?? 1000, 0, ',', ' ') . ' AZN',
            'max_amount' => $credit->max_amount ?? $credit->credit_amount,
            'max_amount_formatted' => number_format($credit->max_amount ?? $credit->credit_amount, 0, ',', ' ') . ' AZN',
            'credit_term' => $credit->credit_term,
            'credit_term_formatted' => $credit->credit_term . ' ' . ($lang === 'az' ? 'ay' : ($lang === 'ru' ? 'месяцев' : 'months')),
            'min_term_months' => $credit->min_term_months ?? 6,
            'max_term_months' => $credit->max_term_months ?? $credit->credit_term,
            'interest_rate' => $credit->interest_rate,
            'interest_rate_formatted' => $credit->interest_rate . '%',
            'commission_rate' => $credit->commission_rate ?? 0,
            'guarantor' => $credit->getTranslation('guarantor', $lang),
            'collateral' => $credit->getTranslation('collateral', $lang),
            'method_of_purchase' => $credit->getTranslation('method_of_purchase', $lang),
            'description' => $credit->getTranslation('about', $lang),
            'bank_phone' => $credit->bank_phone ?? '+994 12 310 0310',
            'bank_address' => $credit->bank_address ?? 'Baku, Azerbaijan',
            'views' => $credit->views,
            'monthly_payment' => $this->calculateMonthlyPayment($credit->credit_amount, $credit->interest_rate, $credit->credit_term),
            'seo_title' => $credit->getTranslation('seo_title', $lang),
            'seo_keywords' => $credit->getTranslation('seo_keywords', $lang),
            'seo_description' => $credit->getTranslation('seo_description', $lang),
        ];
        
        // Get similar credits
        $similarCredits = Credit::with('creditType')
            ->where('status', true)
            ->where('id', '!=', $credit->id)
            ->where('credit_type_id', $credit->credit_type_id)
            ->limit(4)
            ->get()
            ->map(function ($item) use ($lang) {
                $slug = Str::slug($item->getTranslation('credit_name', 'en') ?: $item->getTranslation('credit_name', 'az')) . '-' . $item->id;
                
                return [
                    'id' => $item->id,
                    'slug' => $slug,
                    'bank_name' => $item->bank_name,
                    'credit_name' => $item->getTranslation('credit_name', $lang),
                    'credit_image' => $item->credit_image ? asset('storage/' . $item->credit_image) : null,
                    'interest_rate' => $item->interest_rate,
                    'interest_rate_formatted' => $item->interest_rate . '%',
                    'credit_amount' => $item->credit_amount,
                    'credit_amount_formatted' => number_format($item->credit_amount, 0, ',', ' ') . ' AZN',
                    'credit_term' => $item->credit_term,
                    'credit_term_formatted' => $item->credit_term . ' ' . ($lang === 'az' ? 'ay' : ($lang === 'ru' ? 'месяцев' : 'months')),
                ];
            });
        
        return response()->json([
            'success' => true,
            'credit' => $data,
            'similar_credits' => $similarCredits
        ]);
    }
    
    public function compare(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $ids = $request->get('ids', []);
        
        if (count($ids) < 2 || count($ids) > 4) {
            return response()->json(['message' => 'Please select 2-4 credits to compare'], 400);
        }
        
        $lang = $lang ?? app()->getLocale();
        
        $credits = Credit::with('creditType')
            ->whereIn('id', $ids)
            ->where('status', true)
            ->get()
            ->map(function ($item) use ($lang) {
                $slug = Str::slug($item->getTranslation('credit_name', 'en') ?: $item->getTranslation('credit_name', 'az')) . '-' . $item->id;
                
                return [
                    'id' => $item->id,
                    'slug' => $slug,
                    'bank_name' => $item->bank_name,
                    'credit_name' => $item->getTranslation('credit_name', $lang),
                    'credit_image' => $item->credit_image ? asset('storage/' . $item->credit_image) : null,
                    'about' => $item->getTranslation('about', $lang),
                    'credit_type' => $item->creditType ? $item->creditType->getTranslation('name', $lang) : null,
                    'credit_type_slug' => $item->creditType ? $item->creditType->slug : null,
                    'credit_amount' => $item->credit_amount,
                    'credit_amount_formatted' => number_format($item->credit_amount, 0, ',', ' ') . ' AZN',
                    'credit_term' => $item->credit_term,
                    'credit_term_formatted' => $item->credit_term . ' ' . ($lang === 'az' ? 'ay' : ($lang === 'ru' ? 'месяцев' : 'months')),
                    'interest_rate' => $item->interest_rate,
                    'interest_rate_formatted' => $item->interest_rate . '%',
                    'guarantor' => $item->getTranslation('guarantor', $lang),
                    'collateral' => $item->getTranslation('collateral', $lang),
                    'method_of_purchase' => $item->getTranslation('method_of_purchase', $lang),
                    'monthly_payment' => $this->calculateMonthlyPayment($item->credit_amount, $item->interest_rate, $item->credit_term),
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $credits
        ]);
    }
    
    public function calculate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100|max:1000000',
            'rate' => 'required|numeric|min:0.1|max:100',
            'term' => 'required|integer|min:1|max:360' // in months
        ]);
        
        $amount = $request->amount;
        $rate = $request->rate;
        $term = $request->term;
        
        $monthlyPayment = $this->calculateMonthlyPayment($amount, $rate, $term);
        $totalPayment = $monthlyPayment * $term;
        $totalInterest = $totalPayment - $amount;
        
        // Generate amortization schedule
        $schedule = [];
        $remainingBalance = $amount;
        $monthlyRate = $rate / 100 / 12;
        
        for ($month = 1; $month <= $term; $month++) {
            $interestPayment = $remainingBalance * $monthlyRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            $remainingBalance -= $principalPayment;
            
            $schedule[] = [
                'month' => $month,
                'payment' => round($monthlyPayment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'balance' => round(max(0, $remainingBalance), 2)
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'loan_amount' => $amount,
                'interest_rate' => $rate,
                'loan_term_months' => $term,
                'monthly_payment' => round($monthlyPayment, 2),
                'total_payment' => round($totalPayment, 2),
                'total_interest' => round($totalInterest, 2),
                'schedule' => $schedule
            ]
        ]);
    }
    
    private function calculateMonthlyPayment($amount, $annualRate, $termMonths)
    {
        if ($annualRate == 0) {
            return $amount / $termMonths;
        }
        
        $monthlyRate = $annualRate / 100 / 12;
        $payment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / (pow(1 + $monthlyRate, $termMonths) - 1);
        
        return round($payment, 2);
    }

    /**
     * Get similar credits
     */
    public function similar($lang = null, $slug)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        // Extract ID from slug
        $parts = explode('-', $slug);
        $id = end($parts);
        
        $currentCredit = Credit::find($id);
        
        if (!$currentCredit) {
            return response()->json(['error' => 'Credit not found'], 404);
        }
        
        $lang = $lang ?? app()->getLocale();
        
        // Find similar credits (same type or same bank, excluding current)
        $similarCredits = Credit::with('creditType')
            ->where('status', true)
            ->where('id', '!=', $currentCredit->id)
            ->where(function($query) use ($currentCredit) {
                $query->where('credit_type_id', $currentCredit->credit_type_id)
                      ->orWhere('bank_name', $currentCredit->bank_name);
            })
            ->limit(4)
            ->get()
            ->map(function ($item) use ($lang) {
                $slug = Str::slug($item->getTranslation('credit_name', 'en') ?: $item->getTranslation('credit_name', 'az')) . '-' . $item->id;
                
                return [
                    'id' => $item->id,
                    'slug' => $slug,
                    'bank_name' => $item->bank_name,
                    'credit_name' => $item->getTranslation('credit_name', $lang),
                    'credit_amount_formatted' => number_format($item->credit_amount, 0, ',', ' ') . ' AZN',
                    'interest_rate_formatted' => $item->interest_rate . '%',
                    'credit_type' => $item->getTranslation('credit_type', $lang)
                ];
            });
        
        return response()->json([
            'similar_credits' => $similarCredits
        ]);
    }
}