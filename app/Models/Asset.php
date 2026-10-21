<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'asset_type',
        'name',
        'branch_id',
        'account_id',
        'currency_id',
        'amount',
        'date',
        'chart_of_account_id',
        'description',
        'useful_life_years'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
