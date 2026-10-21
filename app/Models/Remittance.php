<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remittance extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'amount' => 'decimal:4', 'commission' => 'decimal:4', 'bridge_commission' => 'decimal:4', 'sender_branch_commission' => 'decimal:4', 'receiver_branch_commission' => 'decimal:4'];

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function bridgeBranch()
    {
        return $this->belongsTo(Branch::class, 'bridge_branch_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function sender()
    {
        return $this->belongsTo(Account::class, 'sender_account_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Account::class, 'receiver_account_id');
    }

    public function legs()
    {
        return $this->hasMany(RemittanceLeg::class);
    }

    public function getTotalCollectedAttribute(): float
    {
        return round((float) $this->amount + (float) $this->commission, 4);
    }

    public function scopeForBranch($query, ?int $branch)
    {
        return $query->when($branch, fn ($q) => $q->where(fn ($q) => $q->where('from_branch_id', $branch)->orWhere('to_branch_id', $branch)->orWhere('bridge_branch_id', $branch)));
    }
}
