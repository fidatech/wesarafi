<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessDay;
use App\Services\BusinessDayReconciliation;
use Illuminate\Http\Request;

class HistoricalIntegrityController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('manage business day'), 403);
        $filters = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $isSuper = $request->user()->hasRole('Super Admin');
        $branchId = (int) ($filters['branch_id'] ?? ($request->user()->branch_id ?: 1));
        abort_unless($isSuper || $branchId === (int) ($request->user()->branch_id ?: 1), 403);
        $branches = $isSuper ? Branch::orderBy('name')->get(['id', 'name']) : Branch::whereKey($branchId)->get(['id', 'name']);
        $days = BusinessDay::query()->where('status', 'closed')->where('branch_id', $branchId)
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('business_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('business_date', '<=', $filters['to']))
            ->orderByDesc('business_date')->paginate(20)->withQueryString();
        $rows = $days->getCollection()->map(function ($day) use ($branchId) {
            $check = BusinessDayReconciliation::historicalIntegrity($branchId, $day->business_date->toDateString());
            return ['day' => $day, 'check' => $check];
        });
        return view('financial_reports.historical-integrity', compact('days', 'rows', 'branches', 'branchId', 'filters'));
    }
}
