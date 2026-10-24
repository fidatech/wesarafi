<?php

namespace App\Support;

use App\Models\Transaction;

final class TransactionAudit
{
    public const FIELDS = ['id', 'linked_id', 'branch_id', 'date', 'account_id', 'currency_id', 'chart_of_account_id', 'direction', 'amount', 'amount_afn', 'exchange_rate_afn', 'description', 'module_name', 'module_id'];

    public static function snapshot(Transaction $transaction): array
    {
        $transaction->load(['account', 'currency', 'chartOfAccount']);

        return $transaction->only(self::FIELDS) + [
            'account' => $transaction->account?->full_name,
            'currency' => $transaction->currency?->code,
            'coa' => $transaction->chartOfAccount?->account_name,
        ];
    }

    public static function record(array $before, array $after, string $description): void
    {
        $branchIds = collect(array_merge($before, $after))->pluck('branch_id')->unique();
        foreach ($branchIds as $branchId) {
            activity('transaction_changes')->causedBy(auth()->user())
                ->withProperties(['branch_id' => $branchId,
                    'before' => array_values(array_filter($before, fn ($row) => $row['branch_id'] == $branchId)),
                    'after' => array_values(array_filter($after, fn ($row) => $row['branch_id'] == $branchId)),
                ])->log($description);
        }
    }
}
