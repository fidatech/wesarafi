<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shareholder extends Model
{
    protected $fillable = [
        'name',
        'father_name',
        'phone',
        'email',
        'address',
        'share_percentage',
        'joining_date',
        'national_id',
        'profile_picture',
        'is_active',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'is_active' => 'boolean',
    ];
}
