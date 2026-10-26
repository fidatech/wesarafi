<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRateUpdate extends Model
{
    protected $fillable = ['branch_id', 'currency_id', 'created_by', 'rate_date', 'rate_afn_per_unit', 'recorded_at', 'buy_rate_afn', 'sell_rate_afn', 'source', 'to_currency_id', 'quoted_rate', 'rate_operator', 'customer_exchange_id'];

    protected $casts = ['rate_date' => 'date', 'recorded_at' => 'datetime', 'rate_afn_per_unit' => 'decimal:8', 'buy_rate_afn' => 'decimal:8', 'sell_rate_afn' => 'decimal:8', 'quoted_rate' => 'decimal:8'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
