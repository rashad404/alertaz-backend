<?php

namespace Database\Seeders;

use App\Models\News;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GenerateNewsSlugs extends Seeder
{
    public function run()
    {
        $newsItems = News::whereNull('slug')->orWhere('slug', '')->get();
        
        foreach ($newsItems as $news) {
            // Get title - handle JSON or string format
            $titleField = $news->title;
            $title = null;
            
            if (is_string($titleField)) {
                // Try to decode as JSON
                $decoded = json_decode($titleField, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // It's JSON, get the first available translation
                    $title = $decoded['az'] ?? $decoded['en'] ?? $decoded['ru'] ?? reset($decoded);
                } else {
                    // It's a plain string
                    $title = $titleField;
                }
            }
            
            if ($title) {
                // Generate slug from title - limit to 60 characters for safety
                $baseSlug = Str::slug(Str::limit($title, 60, ''));
                $slug = $baseSlug;
                $counter = 1;
                
                // Ensure unique slug
                while (News::where('slug', $slug)->where('id', '!=', $news->id)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                
                $news->slug = $slug;
                $news->save();
                
                $this->command->info("Generated slug for news ID {$news->id}: {$slug}");
            }
        }
        
        $this->command->info('News slugs generated successfully!');
    }
}