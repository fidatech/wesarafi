<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    protected $fillable = ['as_of','type','currency_id','branch_id','rate_afn_per_unit'];
    protected $casts = ['as_of'=>'date','rate_afn_per_unit'=>'decimal:8'];
}
