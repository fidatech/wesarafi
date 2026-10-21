<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['context' => 'array', 'snapshot' => 'array'];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
