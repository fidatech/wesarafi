<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeSource extends Model
{
    protected $fillable = ['name_en', 'name_fa', 'name_ps', 'level'];
}
