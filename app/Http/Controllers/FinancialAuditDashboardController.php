<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessDay;
use App\Services\BusinessDayReconciliation;
use App\Services\InterbranchSettlementAudit;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class FinancialAuditDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuper = $user->hasRole('Super Admin');
        abort_unless($isSuper || $user->can('manage business day'), 403);
        abort_if(! $isSuper && ! $user->branch_id, 403);
        $filters = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $branchId = (int) ($filters['branch_id'] ?? ($user->branch_id ?: 1));
        abort_unless($isSuper || $branchId === (int) $user->branch_id, 403);
        $branches = $isSuper ? Branch::orderBy('name')->get(['id', 'name']) : Branch::whereKey($branchId)->get(['id', 'name']);
        $days = BusinessDay::query()->where('branch_id', $branchId)->where('status', 'closed')
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('business_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('business_date', '<=', $filters['to']))
            ->orderByDesc('business_date');
        $counts = ['closed' => 0, 'consistent' => 0, 'discrepancy' => 0, 'unverifiable' => 0];
        foreach ($days->cursor() as $day) {
            $counts['closed']++;
            $check = BusinessDayReconciliation::historicalIntegrity($branchId, $day->business_date->toDateString());
            $counts[! $check['verifiable'] ? 'unverifiable' : ($check['consistent'] ? 'consistent' : 'discrepancy')]++;
        }
        $exports = Activity::query()->where('log_name', 'financial-audit')
            ->where('description', 'Historical closing integrity report export requested')
            ->where('properties->branch_id', $branchId)
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));
        $exportCount = (clone $exports)->count();
        $recentExports = $exports->with('causer')->latest('id')->limit(10)->get();
        $interbranchAudit = app(InterbranchSettlementAudit::class)->inspect($branchId);
        return view('financial_reports.audit-dashboard', compact('counts', 'exportCount', 'recentExports', 'branches', 'branchId', 'filters', 'interbranchAudit'));
    }
}
