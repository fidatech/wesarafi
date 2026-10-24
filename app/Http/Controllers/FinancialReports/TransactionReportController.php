<?php

namespace App\Http\Controllers\FinancialReports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Transaction;
use Illuminate\Http\Request;

abstract class TransactionReportController extends Controller
{
    protected function renderTransactionReport(
        Request $request,
        string $title,
        array $moduleNames,
        string $routeName
    ) {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $branchId = auth()->user()->branch_id
            ?: ($request->filled('branch_id') ? ($request->integer('branch_id') ?: null) : null);

        $transactions = Transaction::query()
            ->with(['currency', 'chartOfAccount', 'account', 'transactionType', 'branch', 'createdBy'])
            ->whereBetween('date', [$from, $to])
            ->where('is_approved', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query) use ($moduleNames, $title) {
                $query->whereIn('module_name', $moduleNames)
                    ->orWhereHas('transactionType', function ($typeQuery) use ($title) {
                        $keyword = str($title)->before(' ')->lower()->toString();
                        $typeQuery->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                            ->orWhereRaw('LOWER(category) LIKE ?', ["%{$keyword}%"]);
                    });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        return view('financial_reports.module-transactions', [
            'title' => $title,
            'routeName' => $routeName,
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => auth()->user()->branch_id ? Branch::whereKey(auth()->user()->branch_id)->get() : Branch::orderBy('name')->get(),
        ]);
    }
}
