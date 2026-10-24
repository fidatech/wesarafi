<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capital extends Model
{
    protected $fillable = [
        'shareholder_id',
        'branch_id',
        'account_id',
        'currency_id',
        'chart_of_account_id',
        'amount',
        'share_percentage',
        'date',
        'description',
        'created_by'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'share_percentage' => 'decimal:2',
    ];

    // Relationships

    public function shareholder()
    {
        return $this->belongsTo(Shareholder::class);
    }

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
}
