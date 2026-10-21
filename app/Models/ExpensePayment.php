<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpensePayment extends Model
{
    protected $fillable = [
        'expense_id', 'account_id', 'currency_id', 'amount',
        'date', 'note', 'created_by'
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
