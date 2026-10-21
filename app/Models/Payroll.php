<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Payroll extends Model { protected $guarded=[]; protected $casts=['payment_date'=>'date','posted_at'=>'datetime']; public function items(){return $this->hasMany(PayrollItem::class);} public function branch(){return $this->belongsTo(Branch::class);} public function currency(){return $this->belongsTo(Currency::class);} }
