<?php

namespace App\Http\Controllers\FinancialReports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Transaction;
use App\Services\DailyValuationService;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomerReportController extends Controller
{
    public function index(Request $request, ReportExportService $exporter)
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $branchId = $request->filled('branch_id')
            ? ($request->integer('branch_id') ?: null)
            : (auth()->user()->branch_id ?? null);

        $customers = Account::query()
            ->with(['branch', 'subCategory'])
            ->where('type', 'customer')
            ->when($request->customer_type && $request->customer_type !== 'all', function ($query) use ($request) {
                $map = ['regular' => 'Regular Customer', 'permanent' => 'Regular Customer', 'corporate' => 'Corporate Customer', 'temporary' => 'Temporary Customer', 'street_vendor' => 'Street Vendor'];
                if ($name = $map[$request->customer_type] ?? null) $query->whereHas('subCategory', fn ($q) => $q->where('name_en', $name));
            })
            ->when($request->risk_level, fn ($query, $value) => $query->where('risk_level', $value))
            ->when($request->status !== null && $request->status !== '', fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->when($request->search, fn ($query, $value) => $query->where(fn ($q) => $q->where('full_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%")->orWhere('account_code', 'like', "%{$value}%")->orWhere('id_card_number', 'like', "%{$value}%")))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('full_name')
            ->get();

        $transactions = Transaction::query()
            ->with('currency')
            ->when(Schema::hasColumn('transactions', 'account_id'), fn ($q) => $q->whereIn('account_id', $customers->pluck('id')), fn ($q) => $q->where('accountable_type', Account::class)->whereIn('accountable_id', $customers->pluck('id')))
            ->whereDate('date', '<=', $date)
            ->where('is_approved', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->groupBy(fn ($transaction) => $transaction->account_id ?? $transaction->accountable_id);

        $rows = $customers->map(function (Account $customer) use ($transactions, $branchId) {
            $items = $transactions->get($customer->id, collect());
            $balance = 0.0;

            foreach ($items as $transaction) {
                $amountAfn = $transaction->amount_afn;
                if ($amountAfn === null) {
                    $rate = $transaction->exchange_rate_afn ?: DailyValuationService::effectiveRate(
                        $transaction->date->toDateString(),
                        'close',
                        (int) $transaction->currency_id,
                        $branchId ?? $transaction->branch_id
                    );
                    $amountAfn = (float) $transaction->amount * (float) $rate;
                }

                $balance += $transaction->direction === 'debit' ? (float) $amountAfn : -(float) $amountAfn;
            }

            return [
                'account' => $customer,
                'transactions_count' => $items->count(),
                'balance_afn' => round($balance, 2),
            ];
        });

        if (in_array($request->format, ['pdf', 'xlsx'], true)) {
            $exportRows = $rows->map(fn ($row) => [$row['account']->account_code, $row['account']->full_name, $row['account']->subCategory?->name_en, $row['account']->phone, $row['account']->branch?->name, $row['account']->risk_level, $row['transactions_count'], \App\Support\LedgerBalance::format($row['balance_afn'])]);
            $headers = ['Code', 'Customer', 'Category', 'Phone', 'Branch', 'Risk', 'Entries', 'Balance AFN'];
            return $request->format === 'xlsx' ? $exporter->xlsx('Customers Report', $headers, $exportRows, 'customers-'.$date) : $exporter->pdf('Customers Report', $headers, $exportRows, 'customers-'.$date, ['As of' => $date]);
        }

        return view('financial_reports.customers', [
            'rows' => $rows,
            'date' => $date,
            'branchId' => $branchId,
            'branches' => Branch::orderBy('name')->get(),
            'filters' => $request->only(['customer_type', 'risk_level', 'status', 'search']),
        ]);
    }
}
