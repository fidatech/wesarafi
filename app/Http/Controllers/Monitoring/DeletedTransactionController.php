<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DeletedTransactionController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from')?->toDateString() ?? now()->subMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $branchId = auth()->user()->branch_id ?: ($request->integer('branch_id') ?: null);

        $transactions = Transaction::onlyTrashed()
            ->with(['currency', 'chartOfAccount', 'account', 'branch', 'createdBy', 'deletedBy'])
            ->whereDate('deleted_at', '>=', $from)->whereDate('deleted_at', '<=', $to)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('deleted_at')
            ->paginate(100)
            ->withQueryString();

        return view('monitoring.deleted-transactions', [
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => Branch::when(auth()->user()->branch_id, fn ($q) => $q->whereKey(auth()->user()->branch_id))->orderBy('name')->get(),
        ]);
    }
}
