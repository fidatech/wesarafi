<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Depreciation extends Model
{
    protected $fillable = [
        'asset_id',
        'branch_id',
        'currency_id',
        'amount',
        'date',
        'note',
        'created_by',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
