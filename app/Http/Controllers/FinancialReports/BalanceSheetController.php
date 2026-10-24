<?php

namespace App\Http\Controllers\FinancialReports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Setting;
use App\Services\DabStatementService;
use App\Services\FinancialReportService;
use App\Services\ReportExportService;
use App\Support\JalaliDate;
use Illuminate\Http\Request;

class BalanceSheetController extends Controller
{
    public function index(Request $request, FinancialReportService $reports, DabStatementService $dabStatements, ReportExportService $exporter)
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $branchId = $this->branchId($request);
        $report = $reports->balanceSheet($date, $branchId);
        $frequency = $request->input('frequency', 'quarterly');

        $payload = [
            'report' => $report,
            'statement' => $dabStatements->balanceSheet($report),
            'date' => $date,
            'jalaliDate' => JalaliDate::fromGregorian($date),
            'branchId' => $branchId,
            'branches' => Branch::orderBy('name')->get(),
            'selectedBranch' => $branchId ? Branch::find($branchId) : null,
            'setting' => Setting::query()->first(),
            'frequency' => in_array($frequency, ['monthly', 'quarterly', 'annual'], true) ? $frequency : 'quarterly',
        ];
        if (in_array($request->format, ['pdf', 'xlsx'], true)) {
            $rows = $this->statementRows($payload['statement']);
            return $request->format === 'xlsx'
                ? $exporter->xlsx('Balance Sheet', ['Code', 'Item', 'Amount AFN'], $rows, 'balance-sheet-'.$date)
                : $exporter->pdf('Balance Sheet', ['Code', 'Item', 'Amount AFN'], $rows, 'balance-sheet-'.$date, ['Date' => $date, 'Branch' => $payload['selectedBranch']?->name ?? 'All']);
        }
        return view('financial_reports.balance-sheet', $payload);
    }

    private function branchId(Request $request): ?int
    {
        if ($request->user()->branch_id && $request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id) { abort(403); }
        if ($request->filled('branch_id')) {
            return $request->integer('branch_id') ?: null;
        }

        return auth()->user()->branch_id ?? null;
    }

    private function statementRows(array $s): array
    {
        return [
            ['1.1', 'Cash', $s['current_assets']['cash'] ?? 0], ['1.2', 'Cash in bank', $s['current_assets']['cash_bank'] ?? 0],
            ['1.3', 'Receivables', $s['current_assets']['receivables'] ?? 0], ['1.7', 'Total current assets', $s['current_assets']['total'] ?? 0],
            ['2.1', 'Total tangible assets', $s['fixed_assets']['tangible_total'] ?? 0], ['2.4', 'Total intangible assets', $s['fixed_assets']['intangible_total'] ?? 0],
            ['2.6', 'Total assets', $s['total_assets'] ?? 0], ['5.1', 'Total liabilities', $s['liabilities']['total'] ?? 0],
            ['6', 'Total equity', $s['equity']['total'] ?? 0], ['', 'Liabilities and equity', $s['liabilities_equity'] ?? 0],
        ];
    }
}
