<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Adver extends Model
{
    protected $fillable = [
        'position', // 'left' or 'right'
        'iframe',
        'link',
        'image',
    ];
} 