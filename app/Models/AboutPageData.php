<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class AboutPageData extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'description',
        'image',
        'image_alt_text',
        'mission_section_title',
        'video_image',
        'video_link',
        'our_mission_title',
        'our_mission_text',
        'carer_section_title',
        'carer_section_image',
        'carer_section_image_alt_text',
        'carer_section_desc',
    ];

    public array $translatable = [
        'title',
        'description',
        'image_alt_text',
        'mission_section_title',
        'our_mission_title',
        'our_mission_text',
        'carer_section_title',
        'carer_section_image_alt_text',
        'carer_section_desc',
    ];
}
