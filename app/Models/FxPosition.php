<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxPosition extends Model
{
    protected $fillable = [
        'branch_id',
        'currency_id',
        'qty',
        'avg_cost_rate_afn',
    ];
}
