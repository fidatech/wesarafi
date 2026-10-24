<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';
    protected $fillable = [
        'code',
        'category',
        'subcategory',
        'account_name',
        'type',
        'is_active',
    ];
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'chart_of_account_id');
    }
}
