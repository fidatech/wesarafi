<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_en', 'name_fa', 'name_ps', 'line_manager',
    ];

    public function lineManager()
    {
        return $this->belongsTo(Designation::class, 'line_manager');
    }
}
