<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'father_name',
        'phone',
        'email',
        'address',
        'salary',
        'joining_date',
        'national_id',
        'tin_number',
        'profile_picture',
        'is_active',
        'currency_id',
        'branch_id',
        'designation_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joining_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
}
