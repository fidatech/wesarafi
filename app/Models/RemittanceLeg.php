<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemittanceLeg extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:4'];

    public function remittance()
    {
        return $this->belongsTo(Remittance::class);
    }

    public function debtor()
    {
        return $this->belongsTo(Branch::class, 'debtor_branch_id');
    }

    public function creditor()
    {
        return $this->belongsTo(Branch::class, 'creditor_branch_id');
    }

    public function settlements()
    {
        return $this->hasMany(RemittanceSettlement::class);
    }

    public function getOutstandingAttribute(): float
    {
        return round((float) $this->amount - (float) $this->settlements()->sum('amount'), 4);
    }
}
