<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class InsuranceAdvantage extends Model
{
    use HasTranslations;

    protected $fillable = [
        'insurance_id',
        'title',
        'description',
        'icon',
        'order'
    ];

    public $translatable = ['title', 'description'];

    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }
}