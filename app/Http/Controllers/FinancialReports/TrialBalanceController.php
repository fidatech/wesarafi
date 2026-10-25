<?php

namespace App\Http\Controllers\FinancialReports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;

class TrialBalanceController extends Controller
{
    public function index(Request $request, FinancialReportService $reports)
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $isSuper = $request->user()->hasRole('Super Admin');
        abort_if(! $isSuper && ! $request->user()->branch_id, 403);
        abort_if(! $isSuper && $request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id, 403);
        $branchId = $request->filled('branch_id')
            ? ($request->integer('branch_id') ?: null)
            : ($request->user()->branch_id ?? null);

        return view('financial_reports.trial-balance', [
            'report' => $reports->trialBalance($date, $branchId),
            'date' => $date,
            'branchId' => $branchId,
            'branches' => $isSuper ? Branch::orderBy('name')->get() : Branch::whereKey($request->user()->branch_id)->get(),
        ]);
    }
}
