<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendedBank extends Model
{
    // use SortableTrait; // Removed - was from Nova package

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'company_id',
        'order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
} 