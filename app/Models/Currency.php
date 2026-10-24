<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'name', 'code', 'symbol', 'country', 'flag', 'exchange_rate',
        'is_default', 'is_active'
    ];
}
