<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    protected $fillable = [
        'name',
        'category',
        'direction',
        'default_coa_id',
        'is_editable',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_coa_id');
    }
}
