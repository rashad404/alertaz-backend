<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Insurance;
use Illuminate\Support\Str;

class GenerateInsuranceSlugs extends Seeder
{
    public function run()
    {
        $insurances = Insurance::all();
        
        foreach ($insurances as $insurance) {
            // Get the title in az language for slug generation
            $title = $insurance->getTranslation('title', 'az');
            
            // Generate slug from title
            $baseSlug = Str::slug($title);
            $slug = $baseSlug;
            $counter = 1;
            
            // Check for duplicates and add counter if needed
            while (Insurance::where('slug', $slug)->where('id', '!=', $insurance->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            // Update insurance with new slug
            $insurance->slug = $slug;
            $insurance->save();
            
            $this->command->info("Generated slug for insurance: {$title} -> {$slug}");
        }
        
        $this->command->info('Insurance slugs generated successfully!');
    }
}