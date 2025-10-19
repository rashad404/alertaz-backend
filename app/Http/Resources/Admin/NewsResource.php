<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\AdminTranslatable;

class NewsResource extends JsonResource
{
    use AdminTranslatable;
    public function toArray($request)
    {
        $publishDate = $this->publish_date ? \Carbon\Carbon::parse($this->publish_date) : null;
        $isScheduled = $publishDate && $publishDate->isFuture();

        return [
            'id' => $this->id,
            'language' => $this->language,
            'title' => $this->title,
            'sub_title' => $this->sub_title,
            'body' => $this->body,
            'slug' => $this->slug,
            'author' => $this->author,
            'hashtags' => $this->hashtags,
            'views' => $this->views,
            'status' => $this->status,
            // Return publish_date as-is from database (Azerbaijan time) without timezone conversion
            'publish_date' => $publishDate ? $publishDate->format('Y-m-d\TH:i:s') : null,
            'is_scheduled' => $isScheduled,
            'thumbnail_image' => $this->thumbnail_image ? asset('storage/' . $this->thumbnail_image) : null,
            'category_id' => $this->category_id,
            'news_type' => $this->news_type ?? 'other',
            'category' => $this->when($this->category, function() {
                return [
                    'id' => $this->category->id,
                    'title' => $this->getCategoryTitle(),
                    'slug' => $this->category->slug,
                ];
            }),
            'categories' => $this->when($this->relationLoaded('categories'), function() {
                return $this->categories->map(function($category) {
                    return [
                        'id' => $category->id,
                        'title' => $this->getCategoryTitleForCategory($category),
                        'slug' => $category->slug,
                        'is_primary' => $category->pivot->is_primary ?? false,
                    ];
                });
            }),
            'category_ids' => $this->when($this->relationLoaded('categories'), function() {
                return $this->categories->pluck('id')->toArray();
            }),
            'company_id' => $this->company_id,
            'company' => $this->when($this->relationLoaded('company') && $this->company, function() {
                return [
                    'id' => $this->company->id,
                    'name' => $this->getCompanyName(),
                    'logo' => $this->company->logo,
                ];
            }),
            'seo_title' => $this->seo_title,
            'seo_keywords' => $this->seo_keywords,
            'seo_description' => $this->seo_description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function getCategoryTitle()
    {
        if (!$this->category) {
            return 'Uncategorized';
        }

        return $this->getCategoryTitleForCategory($this->category);
    }
    
    private function getCategoryTitleForCategory($category)
    {
        if (!$category) {
            return 'Uncategorized';
        }

        return $this->getTranslatedValue($category->title) ?? 'Uncategorized';
    }
    
    private function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    private function getCompanyName()
    {
        if (!$this->company) {
            return null;
        }
        
        return $this->getTranslatedValue($this->company->name) ?? 'Company';
    }
}