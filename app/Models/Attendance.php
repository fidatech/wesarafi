<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Attendance extends Model { protected $guarded=[]; protected $casts=['date'=>'date']; public function employee(){return $this->belongsTo(Employee::class);} public function branch(){return $this->belongsTo(Branch::class);} }
