<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessDay extends Model
{
    protected $fillable = ['branch_id', 'business_date', 'status', 'opened_by', 'closed_by', 'opened_at', 'closed_at', 'closing_snapshot'];
    protected $casts = ['business_date' => 'date', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'closing_snapshot' => 'array'];
}
