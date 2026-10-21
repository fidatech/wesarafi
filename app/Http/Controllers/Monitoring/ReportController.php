<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\MonitoringReview;
use App\Models\User;
use App\Services\MonitoringReportService;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request, string $report, MonitoringReportService $service, ReportExportService $exporter)
    {
        $context = $service->context($request);
        $rows = $service->build($report, $context);
        $title = MonitoringReportService::TITLES[$report];
        if ($request->filled('format')) {
            $request->validate(['format' => 'in:pdf,xlsx']);
            $headers = array_merge(array_keys($rows->first()['cells'] ?? ['Result' => '']), ['Supporting transactions']);
            $exportRows = $rows->map(fn ($row) => array_merge(array_values($row['cells']), [collect($row['details'])->map(fn ($detail) => collect($detail)->map(fn ($v, $k) => $k.': '.$v)->implode('; '))->implode("\n")]));
            if ($request->format === 'xlsx') {
                return $exporter->xlsx($title, $headers, $exportRows, 'monitoring-'.$report);
            }

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('monitoring.report-export', compact('title', 'rows', 'context', 'report') + ['warnings' => array_unique($service->warnings)])
                ->setPaper('a4', 'landscape')->download('monitoring-'.$report.'.pdf');
        }
        $page = max(1, $request->integer('page', 1));
        $items = new LengthAwarePaginator($rows->forPage($page, 25)->values(), $rows->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $branchId = $context['branch_id'];

        return view('monitoring.report', compact('items', 'report', 'title', 'context') + [
            'warnings' => array_unique($service->warnings), 'titles' => MonitoringReportService::TITLES,
            'branches' => Branch::when($request->user()->branch_id, fn ($q) => $q->whereKey($request->user()->branch_id))->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'accounts' => Account::whereNull('deleted_at')->whereIn('type', ['customer', 'bank'])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->orderBy('full_name')->get(),
            'reviewers' => User::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get(),
        ]);
    }

    public function review(Request $request, string $report, MonitoringReportService $service)
    {
        $context = $service->context($request);
        $data = $request->validate(['item_key' => 'required|string', 'status' => 'required|in:open,reviewing,cleared,escalated',
            'notes' => 'required|string|max:5000', 'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(function ($q) use ($context) {
                if ($context['branch_id']) {
                    $q->where('branch_id', $context['branch_id']);
                }
            })]]);
        $row = $service->build($report, $context)->firstWhere('key', $data['item_key']);
        abort_unless($row, 404, 'This result is no longer present for the selected filters.');
        MonitoringReview::create($data + ['report' => $report, 'branch_id' => $row['branch_id'],
            'title' => MonitoringReportService::TITLES[$report], 'reviewed_by' => $request->user()->id,
            'context' => $context, 'snapshot' => $row]);

        return redirect()->route('monitoring.reviews')->with('success', 'Review saved with its report evidence.');
    }

    public function history(Request $request)
    {
        $request->validate(['status' => 'nullable|in:open,reviewing,cleared,escalated', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $rows = MonitoringReview::with(['reviewer', 'assignee', 'branch'])
            ->when($request->user()->branch_id, fn ($q) => $q->where('branch_id', $request->user()->branch_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()->paginate(25)->withQueryString();

        return view('monitoring.reviews', compact('rows'));
    }
}
