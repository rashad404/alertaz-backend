<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class InsuranceProvider extends Model
{
    use HasTranslations;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'website',
        'phones',
        'email',
        'status'
    ];

    public $translatable = ['name', 'description', 'phones', 'email'];

    protected $casts = [
        'status' => 'boolean',
        'phones' => 'array',
        'email' => 'array'
    ];

    public function insurances()
    {
        return $this->hasMany(Insurance::class, 'provider_id');
    }
}