<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'linked_id',
        'date',
        'transaction_type_id',
        'currency_id',
        'account_id',
        'chart_of_account_id',
        'amount',
        'exchange_rate_afn',
        'amount_afn',
        'direction',
        'branch_id',
        'description',
        'module_name',
        'module_id',
        'created_by',
        'deleted_by',
        'is_safe',
        'is_approved',
        'accountable_type',
        'accountable_id',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'is_suspicious',
        'suspicious_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:8',
        'exchange_rate_afn' => 'decimal:8',
        'amount_afn' => 'decimal:2',
        'is_safe' => 'boolean',
        'is_approved' => 'boolean',
        'is_suspicious' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {
            $fields = array_diff(\App\Support\TransactionAudit::FIELDS, ['id']);
            if (! $transaction->wasChanged($fields)) return;
            $before = new self;
            $before->setRawAttributes($transaction->getRawOriginal());
            \App\Support\TransactionAudit::record(
                [\App\Support\TransactionAudit::snapshot($before)],
                [\App\Support\TransactionAudit::snapshot($transaction)],
                'Edited transaction #'.$transaction->id
            );
        });
        static::creating(function (Transaction $transaction) {
            $transaction->linked_id ??= (string) Str::uuid();
            if ($transaction->branch_id && ($transaction->is_safe
                || ChartOfAccount::whereKey($transaction->chart_of_account_id)->where('code', '1000')->exists())) {
                $treasury = Branch::findOrFail($transaction->branch_id)->ensureTreasuryAccount();
                $transaction->is_safe = true;
                $transaction->account_id = $treasury->id;
                $transaction->accountable_type = Account::class;
                $transaction->accountable_id = $treasury->id;
            }
        });
    }

    public function linkedEntries()
    {
        return $this->hasMany(self::class, 'linked_id', 'linked_id');
    }

    public function journalLinkedAccounts(): string
    {
        if (! $this->linked_id) return '—';

        return $this->linkedEntries
            ->filter(fn ($entry) => $entry->id !== $this->id
                && (int) $entry->branch_id === (int) $this->branch_id
                && (int) $entry->currency_id === (int) $this->currency_id)
            ->map(fn ($entry) => $entry->account?->full_name)
            ->filter()->unique()->implode(', ') ?: '—';
    }

    public function accountable()
    {
        return $this->morphTo();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    /** Backward-compatible alias used by older journal screens. */
    public function type()
    {
        return $this->transactionType();
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
