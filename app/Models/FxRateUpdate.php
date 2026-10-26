<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRateUpdate extends Model
{
    protected $fillable = ['branch_id', 'currency_id', 'created_by', 'rate_date', 'rate_afn_per_unit', 'recorded_at'];

    protected $casts = ['rate_date' => 'date', 'recorded_at' => 'datetime', 'rate_afn_per_unit' => 'decimal:8'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
