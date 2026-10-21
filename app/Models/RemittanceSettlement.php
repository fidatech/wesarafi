<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemittanceSettlement extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:4', 'exchange_rate_afn' => 'decimal:8'];

    public function leg()
    {
        return $this->belongsTo(RemittanceLeg::class, 'remittance_leg_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
