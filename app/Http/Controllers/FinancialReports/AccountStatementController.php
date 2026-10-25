<?php

namespace App\Http\Controllers\FinancialReports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Transaction;
use App\Services\DailyValuationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AccountStatementController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        $isSuper = $request->user()->hasRole('Super Admin');
        abort_if(! $isSuper && ! $request->user()->branch_id, 403);
        abort_if(! $isSuper && $request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id, 403);
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $accountId = $request->integer('account_id') ?: null;
        $currencyId = $request->integer('currency_id') ?: null;
        $branchId = $request->filled('branch_id')
            ? ($request->integer('branch_id') ?: null)
            : (auth()->user()->branch_id ?? null);

        if ($accountId) {
            $account = Account::findOrFail($accountId);
            abort_if($branchId && (int) $account->branch_id !== (int) $branchId, 403);
            abort_if(! $isSuper && (int) $account->branch_id !== (int) $request->user()->branch_id, 403);
        }

        $transactions = collect();
        $openingBalance = 0.0;
        $closingBalance = 0.0;

        if ($accountId) {
            $base = Transaction::query()
                ->with(['currency', 'chartOfAccount', 'transactionType'])
                ->when(Schema::hasColumn('transactions', 'account_id'), fn ($q) => $q->where('account_id', $accountId), fn ($q) => $q->where('accountable_type', Account::class)->where('accountable_id', $accountId))
                ->where('is_approved', true)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId));

            $openingRows = (clone $base)->whereDate('date', '<', $from)->get();
            $openingBalance = $openingRows->sum(fn (Transaction $transaction) => $this->signedAfn($transaction, $branchId));

            $running = $openingBalance;
            $transactions = (clone $base)
                ->whereBetween('date', [$from, $to])
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->map(function (Transaction $transaction) use (&$running, $branchId) {
                    $afn = $this->signedAfn($transaction, $branchId);
                    $running += $afn;
                    $transaction->report_amount_afn = abs($afn);
                    $transaction->report_running_afn = $running;

                    return $transaction;
                });
            $closingBalance = $running;
        }

        return view('financial_reports.account-statement', [
            'accounts' => Account::when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('full_name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'branches' => $isSuper ? Branch::orderBy('name')->get() : Branch::whereKey($request->user()->branch_id)->get(),
            'transactions' => $transactions,
            'openingBalance' => round($openingBalance, 2),
            'closingBalance' => round($closingBalance, 2),
            'from' => $from,
            'to' => $to,
            'accountId' => $accountId,
            'currencyId' => $currencyId,
            'branchId' => $branchId,
        ]);
    }

    private function signedAfn(Transaction $transaction, ?int $branchId): float
    {
        $amountAfn = $transaction->amount_afn;
        if ($amountAfn === null) {
            $rate = $transaction->exchange_rate_afn ?: DailyValuationService::effectiveRate(
                $transaction->date->toDateString(),
                'close',
                (int) $transaction->currency_id,
                $branchId ?? $transaction->branch_id
            );
            $amountAfn = (float) $transaction->amount * (float) $rate;
        }

        return $transaction->direction === 'debit' ? (float) $amountAfn : -(float) $amountAfn;
    }
}
