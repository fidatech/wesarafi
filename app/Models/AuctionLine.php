<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- add this

class AuctionLine extends Model
{
    protected $fillable = [
        'auction_id',
        'give_currency_id','give_amount',
        'get_currency_id','rate_get_per_give','get_amount',
        'note',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function giveCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'give_currency_id');
    }

    public function getCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'get_currency_id');
    }
}
