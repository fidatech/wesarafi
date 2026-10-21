<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class CustomerExchange extends Model { use SoftDeletes; protected $guarded=[]; protected $casts=['date'=>'date'];
 public function branch(){return $this->belongsTo(Branch::class);} public function customer(){return $this->belongsTo(Account::class,'customer_id');}
 public function fromCurrency(){return $this->belongsTo(Currency::class,'from_currency_id');} public function toCurrency(){return $this->belongsTo(Currency::class,'to_currency_id');}
}
