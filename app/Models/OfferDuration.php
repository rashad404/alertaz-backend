<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferDuration extends Model
{
    // use SortableTrait; // Removed - was from Nova package

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'title',
        'order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function offers()
    {
        return $this->hasMany(Offer::class, 'duration_id');
    }
} 