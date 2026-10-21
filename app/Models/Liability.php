<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Liability extends Model
{
    protected $fillable = [
        'branch_id',
        'account_id',
        'currency_id',
        'amount',
        'status',
        'date',
        'description',
        'chart_of_account_id',
        'created_by',
    ];

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(LiabilityPayment::class);
    }
}
