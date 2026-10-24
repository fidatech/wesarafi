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
        $branchId = $request->filled('branch_id')
            ? ($request->integer('branch_id') ?: null)
            : (auth()->user()->branch_id ?? null);

        return view('financial_reports.trial-balance', [
            'report' => $reports->trialBalance($date, $branchId),
            'date' => $date,
            'branchId' => $branchId,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }
}
