<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchCapitalTransfer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:4', 'exchange_rate_afn' => 'decimal:8'];

    public function source()
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destination()
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function repayments()
    {
        return $this->hasMany(self::class, 'advance_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getOutstandingAttribute(): float
    {
        return round((float) $this->amount - (float) ($this->repayments_sum_amount ?? $this->repayments()->sum('amount')), 4);
    }
}
