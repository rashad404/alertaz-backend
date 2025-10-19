<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuySellRate extends Model
{
    protected $fillable = [
        'currency_id',
        'company_id',
        'buy_price',
        'sell_price',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
