<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\News;

class SearchController extends Controller
{
    public function search(Request $request, $lang = 'az')
    {
        // Set the application locale
        app()->setLocale($lang);

        // Get search query
        $query = $request->get('q', '');

        // Validate minimum query length
        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Search query must be at least 2 characters long',
                'results' => []
            ]);
        }

        // Limit results per category
        $limit = $request->get('limit', 5);

        // Search in each model and collect results
        $results = [
            'companies' => $this->searchCompanies($query, $lang, $limit),
            'news' => $this->searchNews($query, $lang, $limit),
        ];

        // Count total results
        $totalResults = collect($results)->sum(function ($category) {
            return count($category['items']);
        });

        return response()->json([
            'success' => true,
            'query' => $query,
            'total_results' => $totalResults,
            'results' => $results,
            'locale' => $lang
        ]);
    }

    private function searchCompanies($query, $lang, $limit)
    {
        $companies = Company::with('companyType')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                // Search in company name (JSON or string)
                $q->where('name', 'LIKE', '%' . $query . '%')
                  ->orWhereRaw("JSON_VALID(name) AND (
                      JSON_EXTRACT(name, '$.az') LIKE ? OR
                      JSON_EXTRACT(name, '$.en') LIKE ? OR
                      JSON_EXTRACT(name, '$.ru') LIKE ?
                  )", ['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']);
            })
            ->take($limit)
            ->get()
            ->map(function ($company) use ($lang) {
                // Get company name (handle JSON)
                $name = $company->name;
                if (is_string($name) && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    $displayName = $decoded[$lang] ?? $decoded['az'] ?? $company->name;
                } else {
                    $displayName = $company->name;
                }

                // Get company type slug for URL
                $companyType = $company->companyType;
                $typeSlug = $companyType ? $companyType->slug : 'companies';

                // Get logo from EAV attributes if available
                $logo = null;
                try {
                    $logo = $company->logo ?? $company->getAttribute('logo');
                } catch (\Exception $e) {
                    // Ignore if attribute doesn't exist
                }

                return [
                    'id' => $company->id,
                    'title' => $displayName,
                    'subtitle' => $companyType ? $companyType->type_name : '',
                    'url' => '/sirketler/' . $typeSlug . '/' . $company->slug,
                    'image' => $logo ? asset('storage/' . $logo) : null
                ];
            });

        return [
            'category' => $lang === 'az' ? 'Şirkətlər' : ($lang === 'ru' ? 'Компании' : 'Companies'),
            'count' => $companies->count(),
            'items' => $companies
        ];
    }

    private function searchNews($query, $lang, $limit)
    {
        $news = News::where('status', 1)
            ->where(function ($q) use ($query) {
                // Search in title (plain text, not JSON for news)
                $q->where('title', 'LIKE', '%' . $query . '%')
                  // Also search in body
                  ->orWhere('body', 'LIKE', '%' . $query . '%');
            })
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($item) use ($lang) {
                // Extract subtitle from body
                $body = strip_tags($item->body ?? '');
                $subtitle = \Illuminate\Support\Str::limit($body, 100);

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => $subtitle,
                    'date' => $item->created_at ? $item->created_at->format('d.m.Y') : '',
                    'url' => '/xeberler/' . $item->slug,
                    'image' => $item->thumbnail_image ? asset('storage/' . $item->thumbnail_image) : null
                ];
            });

        return [
            'category' => $lang === 'az' ? 'Xəbərlər' : ($lang === 'ru' ? 'Новости' : 'News'),
            'count' => $news->count(),
            'items' => $news
        ];
    }
}