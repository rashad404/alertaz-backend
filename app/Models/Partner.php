<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    // use SortableTrait; // Removed - was from Nova package

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'title',
        'image',
        'order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
