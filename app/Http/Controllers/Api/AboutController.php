<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutPageData;
use App\Models\AboutPageDynamicData;
use App\Models\OurMission;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    /**
     * Get about page main content
     */
    public function index(Request $request, $locale = null)
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        
        $lang = $locale ?? app()->getLocale();
        
        $aboutData = AboutPageData::first();
        
        if (!$aboutData) {
            return response()->json([
                'success' => false,
                'message' => 'About page data not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $aboutData->id,
                'purpose_title' => $this->getTranslatedValue($aboutData->title, $lang),
                'purpose_description' => $this->getTranslatedValue($aboutData->description, $lang),
                'purpose_image' => $aboutData->image ? asset('storage/' . $aboutData->image) : null,
                'purpose_image_alt' => $this->getTranslatedValue($aboutData->image_alt_text, $lang),
                'video_section_title' => $this->getTranslatedValue($aboutData->mission_section_title, $lang),
                'video_url' => $aboutData->video_link,
                'video_thumbnail' => $aboutData->video_image ? asset('storage/' . $aboutData->video_image) : null,
                'mission_title' => $this->getTranslatedValue($aboutData->our_mission_title, $lang),
                'mission_description' => $this->getTranslatedValue($aboutData->our_mission_text, $lang),
                'career_section' => [
                    'title' => $this->getTranslatedValue($aboutData->carer_section_title, $lang),
                    'description' => $this->getTranslatedValue($aboutData->carer_section_desc, $lang),
                    'image' => $aboutData->carer_section_image ? asset('storage/' . $aboutData->carer_section_image) : null,
                    'image_alt' => $this->getTranslatedValue($aboutData->carer_section_image_alt_text, $lang),
                ],
            ]
        ]);
    }
    
    /**
     * Get missions list
     */
    public function missions(Request $request, $locale = null)
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        
        $lang = $locale ?? app()->getLocale();
        
        $missions = OurMission::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($mission) use ($lang) {
                return [
                    'id' => $mission->id,
                    'title' => $this->getTranslatedValue($mission->title, $lang),
                    'description' => $this->getTranslatedValue($mission->description, $lang),
                    'icon' => $mission->icon ? asset('storage/' . $mission->icon) : null,
                    'order' => $mission->order,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $missions
        ]);
    }
    
    /**
     * Helper function to get translated value from JSON or handle double-encoded JSON
     */
    private function getTranslatedValue($value, $lang)
    {
        if (!$value) {
            return null;
        }

        // If it's already a string and not JSON, return as is
        if (is_string($value) && !$this->isJson($value)) {
            return $value;
        }

        // Try to decode JSON
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        
        if (is_array($decoded)) {
            // Check if it's double-encoded (has a single language key with JSON string value)
            if (count($decoded) === 1 && isset($decoded['en']) && $this->isJson($decoded['en'])) {
                // It's double-encoded, decode again
                $innerDecoded = json_decode($decoded['en'], true);
                if (is_array($innerDecoded) && isset($innerDecoded[$lang])) {
                    return $innerDecoded[$lang];
                }
                // Fallback to any available language
                return is_array($innerDecoded) ? reset($innerDecoded) : $decoded['en'];
            }
            
            // Normal case - properly encoded translations
            if (isset($decoded[$lang])) {
                return $decoded[$lang];
            }
            
            // Fallback to 'az' if requested language not found
            if (isset($decoded['az'])) {
                return $decoded['az'];
            }
            
            // Return first available translation
            return reset($decoded);
        }
        
        // If all else fails, return the original value
        return $value;
    }
    
    /**
     * Check if a string is valid JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Get bank count helper
     */
    private function getBankCount()
    {
        try {
            // Try to find bank type
            $bankType = \App\Models\CompanyType::where('slug', 'bank')
                ->orWhere('title->az', 'Bank')
                ->first();
            
            if ($bankType) {
                return \App\Models\Company::where('company_type_id', $bankType->id)->count();
            }
            
            // If no bank type found, return a default value
            return 132;
        } catch (\Exception $e) {
            // If there's any error, return default value
            return 132;
        }
    }
    
    /**
     * Get dynamic sections
     */
    public function dynamicSections(Request $request, $locale = null)
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        
        $lang = $locale ?? app()->getLocale();
        
        $sections = AboutPageDynamicData::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($section) use ($lang) {
                return [
                    'id' => $section->id,
                    'title' => $this->getTranslatedValue($section->title, $lang),
                    'subtitle' => $this->getTranslatedValue($section->subtitle, $lang),
                    'content' => $this->getTranslatedValue($section->desc, $lang),
                    'order' => $section->order,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $sections
        ]);
    }
}