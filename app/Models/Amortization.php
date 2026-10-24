<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amortization extends Model
{
    protected $fillable = [
        'asset_id', 'branch_id', 'currency_id', 'chart_of_account_id',
        'amount', 'date', 'note', 'created_by'
    ];

    public function asset()    { return $this->belongsTo(Asset::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
}
