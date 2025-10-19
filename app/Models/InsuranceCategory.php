<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class InsuranceCategory extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'slug',
        'icon',
        'order',
        'status'
    ];

    public $translatable = ['title'];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function insurances()
    {
        return $this->hasMany(Insurance::class, 'category_id');
    }
}