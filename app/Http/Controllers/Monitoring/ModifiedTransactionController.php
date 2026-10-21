<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ModifiedTransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $request->date('from')?->toDateString() ?? now()->subMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $branchId = $request->user()->branch_id ?: ($request->integer('branch_id') ?: null);
        $changes = Activity::with('causer')->where('log_name', 'transaction_changes')
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('properties->branch_id', $branchId))
            ->latest()->paginate(30)->withQueryString();
        $branches = Branch::when($request->user()->branch_id, fn ($q) => $q->whereKey($request->user()->branch_id))->orderBy('name')->get();

        return view('monitoring.modified-transactions', compact('changes', 'branches', 'branchId', 'from', 'to'));
    }
}
