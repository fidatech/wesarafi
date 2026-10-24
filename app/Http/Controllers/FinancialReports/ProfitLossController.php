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

class ProfitLossController extends Controller
{
    public function index(Request $request, FinancialReportService $reports, DabStatementService $dabStatements, ReportExportService $exporter)
    {
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $from = $request->date('from')?->toDateString() ?? now()->startOfQuarter()->toDateString();
        abort_if($request->user()->branch_id && $request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id, 403);
        $branchId = $request->filled('branch_id')
            ? ($request->integer('branch_id') ?: null)
            : (auth()->user()->branch_id ?? null);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $report = $reports->profitLoss($from, $to, $branchId);
        $frequency = $request->input('frequency', 'quarterly');

        $payload = [
            'report' => $report,
            'statement' => $dabStatements->profitLoss($report),
            'from' => $from,
            'to' => $to,
            'jalaliFrom' => JalaliDate::fromGregorian($from),
            'jalaliTo' => JalaliDate::fromGregorian($to),
            'branchId' => $branchId,
            'branches' => Branch::orderBy('name')->get(),
            'selectedBranch' => $branchId ? Branch::find($branchId) : null,
            'setting' => Setting::query()->first(),
            'frequency' => in_array($frequency, ['monthly', 'quarterly', 'annual'], true) ? $frequency : 'quarterly',
        ];
        if (in_array($request->format, ['pdf', 'xlsx'], true)) {
            $rows = collect($payload['statement'])->filter(fn ($value) => is_numeric($value))->map(fn ($value, $key) => [str($key)->replace('_', ' ')->title(), $value])->values()->all();
            return $request->format === 'xlsx'
                ? $exporter->xlsx('Profit and Loss', ['Item', 'Amount AFN'], $rows, 'profit-loss-'.$from.'-'.$to)
                : $exporter->pdf('Profit and Loss', ['Item', 'Amount AFN'], $rows, 'profit-loss-'.$from.'-'.$to, ['From' => $from, 'To' => $to, 'Branch' => $payload['selectedBranch']?->name ?? 'All']);
        }
        return view('financial_reports.profit-loss', $payload);
    }
}
