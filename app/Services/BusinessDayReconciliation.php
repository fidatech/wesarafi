<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BusinessDayReconciliation
{
    public static function report(int $branchId, string $date): array
    {
        $transactions = Transaction::query()->where('branch_id', $branchId)->whereDate('date', '<=', $date);
        $pending = (clone $transactions)->whereDate('date', $date)
            ->where(function ($q) {
                $q->where('approval_status', 'pending')
                  ->orWhere('is_approved', false)->whereNull('approval_status');
            })->count();
        $cash = (clone $transactions)->where('is_safe', true)
            ->select('currency_id', 'direction', DB::raw('SUM(amount) AS total'))
            ->groupBy('currency_id', 'direction')->get()->groupBy('currency_id')
            ->map(function ($rows) {
                $debit = (float) $rows->where('direction', 'debit')->sum('total');
                $credit = (float) $rows->where('direction', 'credit')->sum('total');
                return round($debit - $credit, 2);
            })->toArray();
        $balances = (clone $transactions)
            ->select('currency_id', 'direction', DB::raw('SUM(amount) AS total'))
            ->groupBy('currency_id', 'direction')->get()
            ->groupBy('currency_id')->map(function ($lines) {
                $totals = [];
                foreach ($lines as $line) $totals[(string) $line->direction] = (string) $line->total;
                return $totals;
            })->toArray();

        return [
            'pending_approvals' => $pending,
            'cash_book_by_currency' => $cash,
            'ledger_totals_by_currency_and_direction' => $balances,
            'reconciled' => $pending === 0,
            'note' => 'Ledger totals are informational; physical cash count has not been independently verified.',
        ];
    }
}
