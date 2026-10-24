<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FxDailyValuationLine extends Model {
  protected $fillable = ['valuation_id','currency_id','qty_close','avg_cost_rate_afn','closing_rate_afn','unrealized_pl_afn'];
  protected $casts = [
    'qty_close'=>'decimal:8',
    'avg_cost_rate_afn'=>'decimal:8',
    'closing_rate_afn'=>'decimal:8',
    'unrealized_pl_afn'=>'decimal:2',
  ];
}
