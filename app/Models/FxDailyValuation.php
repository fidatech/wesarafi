<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FxDailyValuation extends Model {
  protected $fillable = ['as_of','branch_id','realized_pl_afn','unrealized_pl_afn','temp_pnl_afn'];
  protected $casts = ['as_of'=>'date'];
  public function lines(): HasMany { return $this->hasMany(FxDailyValuationLine::class, 'valuation_id'); }
}
