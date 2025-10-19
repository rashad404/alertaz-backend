<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'currency',
        'central_bank_rate',
        'order',
        'status',
    ];

    public function buySellRates()
    {
        return $this->hasMany(BuySellRate::class);
    }
}
