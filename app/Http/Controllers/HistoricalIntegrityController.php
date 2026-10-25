<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessDay;
use App\Services\BusinessDayReconciliation;
use Illuminate\Http\Request;

class HistoricalIntegrityController extends Controller
{
    public function export(Request $request)
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('manage business day'), 403);
        $filters = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        abort_if(! $request->user()->hasRole('Super Admin') && ! $request->user()->branch_id, 403);
        $branchId = (int) ($filters['branch_id'] ?? ($request->user()->branch_id ?: 1));
        abort_unless($request->user()->hasRole('Super Admin') || $branchId === (int) $request->user()->branch_id, 403);
        // Persist an audit record before streaming the CSV response.
        activity('financial-audit')->causedBy($request->user())->withProperties([
            'action' => 'historical_integrity_csv_export',
            'branch_id' => $branchId,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'requested_at' => now()->toIso8601String(),
            'ip_address' => $request->ip(),
        ])->log('Historical closing integrity report export requested');
        $days = BusinessDay::query()->where('status', 'closed')->where('branch_id', $branchId)
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('business_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('business_date', '<=', $filters['to']))
            ->orderByDesc('business_date')->cursor();
        return response()->streamDownload(function () use ($days, $branchId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Business Date', 'Branch ID', 'Closed At', 'Closed By', 'Integrity', 'Field', 'Recorded', 'Recalculated']);
            foreach ($days as $day) {
                $check = BusinessDayReconciliation::historicalIntegrity($branchId, $day->business_date->toDateString());
                $status = ! $check['verifiable'] ? 'Unverifiable' : ($check['consistent'] ? 'Consistent' : 'Discrepancy');
                foreach (($check['comparisons'] ?? ['' => ['recorded' => null, 'recalculated' => null]]) as $field => $comparison) {
                    $safe = fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) ($value ?? '');
                    $cells = [$day->business_date->toDateString(), $branchId, $day->closed_at?->toDateTimeString(), $day->closed_by, $status, $field, $safe($comparison['recorded']), $safe($comparison['recalculated'])];
                    fputcsv($out, array_map(fn ($cell) => is_string($cell) && preg_match('/^[=+@\-\t\r]/', $cell) ? "'".$cell : $cell, $cells));
                }
            }
            fclose($out);
        }, 'closing-integrity-'.$branchId.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('manage business day'), 403);
        $filters = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $isSuper = $request->user()->hasRole('Super Admin');
        abort_if(! $request->user()->hasRole('Super Admin') && ! $request->user()->branch_id, 403);
        $branchId = (int) ($filters['branch_id'] ?? ($request->user()->branch_id ?: 1));
        abort_unless($isSuper || $branchId === (int) $request->user()->branch_id, 403);
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
