<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiabilityPayment extends Model
{
    protected $fillable = [
        'liability_id',
        'account_id',
        'currency_id',
        'amount',
        'date',
        'description',
        'created_by',
    ];

    // Relationships
    public function liability()
    {
        return $this->belongsTo(Liability::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
