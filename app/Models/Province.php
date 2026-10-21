<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $fillable = [
        'id',
        'name_fa',
        'name_pa',
        'name_en',
        'country_id',
    ];
}
