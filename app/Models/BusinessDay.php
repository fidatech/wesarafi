<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessDay extends Model
{
    protected $fillable = ['branch_id', 'business_date', 'status', 'opened_by', 'closed_by', 'opened_at', 'closed_at', 'closing_snapshot', 'opening_snapshot', 'cash_counts', 'closing_notes'];
    protected $casts = ['business_date' => 'date', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'closing_snapshot' => 'array', 'opening_snapshot' => 'array', 'cash_counts' => 'array'];
}
