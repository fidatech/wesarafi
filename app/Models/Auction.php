<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = ['type','branch_id','account_id', 'chart_of_account_id', 'date','description','created_by'];
    protected $casts = ['date'=>'date'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function lines()
    {
        return $this->hasMany(AuctionLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
