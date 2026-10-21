<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'province_id',
        'phone',
        'address',
        'is_main',
        'allows_direct_remittance',
        'type',
    ];

    protected static function booted(): void
    {
        static::created(fn (Branch $branch) => $branch->ensureTreasuryAccount());
    }

    protected $casts = ['is_main' => 'boolean', 'allows_direct_remittance' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hasRemittanceAccess(): bool
    {
        return $this->allows_direct_remittance && $this->users()->where('status', true)->exists();
    }

    public function treasuryAccount()
    {
        return $this->hasOne(Account::class)->where('type', 'safe');
    }

    public function ensureTreasuryAccount(): Account
    {
        return Account::firstOrCreate(
            ['account_code' => sprintf('TREASURY-%03d', $this->id)],
            ['full_name' => $this->name.' Treasury', 'branch_id' => $this->id,
                'type' => 'safe', 'is_active' => true]
        );
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
