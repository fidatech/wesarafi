<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'branch_id', 'chart_of_account_id', 'account_id', 'currency_id',
        'amount', 'payment_type', 'payment_status', 'date', 'due_date',
        'description', 'created_by'
    ];

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function account() { return $this->belongsTo(Account::class); }
    public function chartOfAccount() { return $this->belongsTo(ChartOfAccount::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
