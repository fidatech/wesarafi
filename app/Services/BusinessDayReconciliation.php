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
                  ->orWhere(function ($q) {
                      $q->where('is_approved', false)
                        ->where(function ($q) {
                            $q->whereNull('approval_status')->orWhere('approval_status', '!=', 'rejected');
                        });
                  });
            })->count();
        // Only approved postings affect physical cash and the finalized ledger.
        $approved = (clone $transactions)->where('is_approved', true)->where('approval_status', 'approved');
        $cash = (clone $approved)->where('is_safe', true)
            ->select('currency_id', 'direction', DB::raw('SUM(amount) AS total'))
            ->groupBy('currency_id', 'direction')->get()->groupBy('currency_id')
            ->map(function ($rows) {
                $debit = (float) $rows->where('direction', 'debit')->sum('total');
                $credit = (float) $rows->where('direction', 'credit')->sum('total');
                return round($debit - $credit, 2);
            })->toArray();
        $balances = (clone $approved)
            ->select('currency_id', 'direction', DB::raw('SUM(amount) AS total'))
            ->groupBy('currency_id', 'direction')->get()
            ->groupBy('currency_id')->map(function ($lines) {
                $totals = [];
                foreach ($lines as $line) $totals[(string) $line->direction] = (string) $line->total;
                return $totals;
            })->toArray();

        $invalidJournals = [];
        $journals = (clone $transactions)->get(['linked_id', 'is_approved', 'approval_status', 'direction', 'amount_afn'])->groupBy('linked_id');
        foreach ($journals as $reference => $lines) {
            $approvedLines = $lines->filter(fn ($line) => $line->is_approved && $line->approval_status === 'approved');
            if ($approvedLines->isEmpty()) continue;
            $debit = (float) $approvedLines->where('direction', 'debit')->sum('amount_afn');
            $credit = (float) $approvedLines->where('direction', 'credit')->sum('amount_afn');
            if (! $reference || $approvedLines->count() !== $lines->count() || $approvedLines->count() < 2 || abs($debit - $credit) > 0.02) {
                $invalidJournals[] = $reference ?: '(missing reference)';
            }
        }

        return [
            'invalid_journal_count' => count($invalidJournals),
            'invalid_journal_references' => $invalidJournals,
            'pending_approvals' => $pending,
            'cash_book_by_currency' => $cash,
            'ledger_totals_by_currency_and_direction' => $balances,
            'reconciled' => $pending === 0 && $invalidJournals === [],
            'note' => 'Ledger totals are informational; physical cash count has not been independently verified.',
        ];
    }
}
