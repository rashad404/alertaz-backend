<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OffersCategory;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    private function parseTranslation($value, $lang = 'en')
    {
        if (!$value) {
            return '';
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ($decoded && is_array($decoded)) {
                return $decoded[$lang] ?? $decoded['en'] ?? $decoded['az'] ?? '';
            }
            return $value;
        }
        
        if (is_array($value)) {
            return $value[$lang] ?? $value['en'] ?? $value['az'] ?? '';
        }
        
        return $value;
    }
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $perPage = $request->get('per_page', 12);
        $categorySlug = $request->get('category');
        $sortBy = $request->get('sort_by', 'order'); // order, min_interest_rate, min_amount, duration_id, views
        $sortOrder = $request->get('sort_order', 'asc'); // asc, desc
        $search = $request->get('search'); // Axtarış üçün
        $durationId = $request->get('duration_id'); // Müddət filter üçün
        $minAmount = $request->get('min_amount'); // Minimal məbləğ filter
        $maxAmount = $request->get('max_amount'); // Maksimum məbləğ filter
        
        $query = Offer::with(['category', 'duration', 'advantages', 'bank'])->where('status', true);
        
        // Filter by category if provided
        if ($categorySlug) {
            $category = OffersCategory::where('slug', $categorySlug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }
        
        // Search filter
        if ($search) {
            $query->whereHas('bank', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            })->orWhere('title', 'like', '%' . $search . '%');
        }
        
        // Duration filter
        if ($durationId) {
            $query->where('duration_id', $durationId);
        }
        
        // Amount range filter
        if ($minAmount) {
            $query->where('min_amount', '>=', $minAmount);
        }
        
        if ($maxAmount) {
            $query->where('max_amount', '<=', $maxAmount);
        }
        
        // Apply sorting
        switch ($sortBy) {
            case 'min_interest_rate':
                $query->orderBy('min_interest_rate', $sortOrder);
                break;
            case 'min_amount':
                $query->orderBy('min_amount', $sortOrder);
                break;
            case 'duration_id':
                $query->orderBy('duration_id', $sortOrder);
                break;
            case 'max_amount':
                $query->orderBy('max_amount', $sortOrder);
                break;
            case 'views':
                $query->orderBy('views', $sortOrder);
                break;
            default:
                $query->orderBy('order', $sortOrder);
        }
        
        $offers = $query->paginate($perPage);
            
        $lang = $lang ?? app()->getLocale();
        
        $offers->getCollection()->transform(function ($item) use ($lang) {
            return [
                'id' => $item->id,
                'bank' => $item->bank ? [
                    'id' => $item->bank->id,
                    'name' => $item->bank->name, // Name is not translatable in new companies table
                    'slug' => $item->bank->slug,
                ] : null,
                'bank_name' => $item->bank ? $item->bank->name : null, // For backward compatibility
                'company' => $item->bank ? [
                    'id' => $item->bank->id,
                    'name' => $item->bank->name,
                    'slug' => $item->bank->slug,
                ] : null,
                'title' => $this->parseTranslation($item->title, $lang),
                'annual_interest_rate' => $item->annual_interest_rate ? (float)$item->annual_interest_rate : null,
                'min_interest_rate' => $item->min_interest_rate ? (float)$item->min_interest_rate : null,
                'max_interest_rate' => $item->max_interest_rate ? (float)$item->max_interest_rate : null,
                'duration' => $item->duration ? [
                    'id' => $item->duration->id,
                    'title' => $item->duration->title,
                ] : null,
                'duration_id' => $item->duration_id,
                'max_duration' => $item->max_duration ? (int)$item->max_duration : null,
                'monthly_payment' => $item->monthly_payment ? (float)$item->monthly_payment : null,
                'min_amount' => $item->min_amount ? (float)$item->min_amount : null,
                'max_amount' => $item->max_amount ? (float)$item->max_amount : null,
                'site_link' => $item->site_link,
                'order' => $item->order,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'category' => [
                    'id' => $item->category->id,
                    'title' => $this->parseTranslation($item->category->title, $lang),
                    'slug' => $item->category->slug,
                ],
                'advantages' => $item->advantages->where('status', true)->map(function ($advantage) use ($lang) {
                    return [
                        'id' => $advantage->id,
                        'title' => $this->parseTranslation($advantage->title, $lang),
                    ];
                }),
            ];
        });
        
        return response()->json($offers);
    }

    public function byCategory($categorySlug, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $perPage = request()->get('per_page', 12);
        $sortBy = request()->get('sort_by', 'order'); // order, min_interest_rate, min_amount, duration_id
        $sortOrder = request()->get('sort_order', 'asc'); // asc, desc
        $search = request()->get('search'); // Axtarış üçün
        $durationId = request()->get('duration_id'); // Müddət filter üçün
        $minAmount = request()->get('min_amount'); // Minimal məbləğ filter
        $maxAmount = request()->get('max_amount'); // Maksimum məbləğ filter
        
        $category = OffersCategory::where('slug', $categorySlug)
            ->where('status', true)
            ->first();
            
        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }
        
        $query = Offer::with(['category', 'duration', 'advantages', 'bank'])
            ->where('category_id', $category->id)
            ->where('status', true);
            
        // Search filter
        if ($search) {
            $query->whereHas('bank', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            })->orWhere('title', 'like', '%' . $search . '%');
        }
        
        // Duration filter
        if ($durationId) {
            $query->where('duration_id', $durationId);
        }
        
        // Amount range filter
        if ($minAmount) {
            $query->where('min_amount', '>=', $minAmount);
        }
        
        if ($maxAmount) {
            $query->where('max_amount', '<=', $maxAmount);
        }
            
        // Apply sorting
        switch ($sortBy) {
            case 'min_interest_rate':
                $query->orderBy('min_interest_rate', $sortOrder);
                break;
            case 'min_amount':
                $query->orderBy('min_amount', $sortOrder);
                break;
            case 'duration_id':
                $query->orderBy('duration_id', $sortOrder);
                break;
            case 'max_amount':
                $query->orderBy('max_amount', $sortOrder);
                break;
            case 'views':
                $query->orderBy('views', $sortOrder);
                break;
            default:
                $query->orderBy('order', $sortOrder);
        }
        
        $offers = $query->paginate($perPage);
            
        $lang = $lang ?? app()->getLocale();
        
        $offers->getCollection()->transform(function ($item) use ($lang) {
            return [
                'id' => $item->id,
                'bank' => $item->bank ? [
                    'id' => $item->bank->id,
                    'name' => $item->bank->name, // Name is not translatable in new companies table
                    'slug' => $item->bank->slug,
                ] : null,
                'bank_name' => $item->bank ? $item->bank->name : null, // For backward compatibility
                'company' => $item->bank ? [
                    'id' => $item->bank->id,
                    'name' => $item->bank->name,
                    'slug' => $item->bank->slug,
                ] : null,
                'title' => $this->parseTranslation($item->title, $lang),
                'annual_interest_rate' => $item->annual_interest_rate ? (float)$item->annual_interest_rate : null,
                'min_interest_rate' => $item->min_interest_rate ? (float)$item->min_interest_rate : null,
                'max_interest_rate' => $item->max_interest_rate ? (float)$item->max_interest_rate : null,
                'duration' => $item->duration ? [
                    'id' => $item->duration->id,
                    'title' => $item->duration->title,
                ] : null,
                'duration_id' => $item->duration_id,
                'max_duration' => $item->max_duration ? (int)$item->max_duration : null,
                'monthly_payment' => $item->monthly_payment ? (float)$item->monthly_payment : null,
                'min_amount' => $item->min_amount ? (float)$item->min_amount : null,
                'max_amount' => $item->max_amount ? (float)$item->max_amount : null,
                'site_link' => $item->site_link,
                'order' => $item->order,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'category' => [
                    'id' => $item->category->id,
                    'title' => $this->parseTranslation($item->category->title, $lang),
                    'slug' => $item->category->slug,
                ],
                'advantages' => $item->advantages->where('status', true)->map(function ($advantage) use ($lang) {
                    return [
                        'id' => $advantage->id,
                        'title' => $this->parseTranslation($advantage->title, $lang),
                    ];
                }),
            ];
        });
        
        return response()->json($offers);
    }


    public function show($langOrId, $id = null)
    {
        // Handle both /{id} and /{locale}/{id} routes
        if ($id === null) {
            $offerId = $langOrId;
            $lang = app()->getLocale();
        } else {
            $offerId = $id;
            $lang = $langOrId;
            app()->setLocale($lang);
        }
        
        $offer = Offer::with(['category', 'duration', 'advantages', 'bank'])
            ->where('id', $offerId)
            ->where('status', true)
            ->first();
            
        if (!$offer) {
            return response()->json(['error' => 'Offer not found'], 404);
        }
        
        // Increment views - commented out for now as it might cause issues
        // $offer->increment('views');
        
        $data = [
            'id' => $offer->id,
            'bank' => $offer->bank ? [
                'id' => $offer->bank->id,
                'name' => $offer->bank->name, // Direct field in new companies table
                'slug' => $offer->bank->slug,
            ] : null,
            'bank_name' => $offer->bank ? $offer->bank->name : null, // For backward compatibility
            'company' => $offer->bank ? [
                'id' => $offer->bank->id,
                'name' => $offer->bank->name,
                'slug' => $offer->bank->slug,
            ] : null,
            'title' => is_string($offer->title) ? json_decode($offer->title, true) : $offer->title,
            'description' => $offer->note ? $offer->getTranslation('note', $lang) : null,
            'annual_interest_rate' => $offer->annual_interest_rate,
            'min_interest_rate' => $offer->min_interest_rate,
            'max_interest_rate' => $offer->max_interest_rate,
            'duration' => $offer->duration ? [
                'id' => $offer->duration->id,
                'title' => $offer->duration->title,
            ] : null,
            'duration_id' => $offer->duration_id,
            'monthly_payment' => $offer->monthly_payment,
            'min_amount' => $offer->min_amount,
            'max_amount' => $offer->max_amount,
            'max_duration' => $offer->max_duration,
            'site_link' => $offer->site_link,
            'order' => $offer->order,
            'status' => $offer->status,
            'views' => $offer->views,
            'created_at' => $offer->created_at,
            'updated_at' => $offer->updated_at,
            'category' => [
                'id' => $offer->category->id,
                'title' => $offer->category->getTranslation('title', $lang),
                'slug' => $offer->category->slug,
            ],
            'advantages' => $offer->advantages->where('status', true)->map(function ($advantage) use ($lang) {
                return [
                    'id' => $advantage->id,
                    'title' => $advantage->getTranslation('title', $lang),
                ];
            }),
        ];
        
        return response()->json(['offer' => $data]);
    }

    public function categories($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $categories = \App\Models\OffersCategory::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($category) use ($lang) {
                $lang = $lang ?? app()->getLocale();
                return [
                    'id' => $category->id,
                    'title' => $category->getTranslation('title', $lang),
                    'slug' => $category->slug,
                ];
            });
        return response()->json($categories);
    }
} 