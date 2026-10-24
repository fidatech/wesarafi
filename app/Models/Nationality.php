<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nationality extends Model
{
    protected $fillable = [
        'name_en',
        'name_fa',
        'name_ps'
    ];
}
