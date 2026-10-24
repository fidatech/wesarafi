<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Support\JournalEntryFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class TransactionController extends Controller
{
    public function index()
    {
        return view('journal.transactions.index', [
            'accounts' => Account::where('is_active', true)->orderBy('full_name')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'types' => TransactionType::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function data(Request $request)
    {
        $query = $this->journalQuery($request);

        return DataTables::of($query)
            ->addColumn('account', fn (Transaction $transaction) => $transaction->account?->full_name ?? $transaction->branch?->name ?? '—')
            ->addColumn('linked_account', fn (Transaction $transaction) => $transaction->journalLinkedAccounts())
            ->addColumn('currency', fn (Transaction $transaction) => $transaction->currency?->code ?? '-')
            ->addColumn('type', fn (Transaction $transaction) => $transaction->transactionType?->name ?? ucfirst($transaction->direction))
            ->addColumn('coa', fn (Transaction $transaction) => $transaction->chartOfAccount?->account_name ?? '-')
            ->addColumn('money_in', fn (Transaction $transaction) => $transaction->direction === 'debit' ? number_format((float) $transaction->amount, 2) : '—')
            ->addColumn('money_out', fn (Transaction $transaction) => $transaction->direction === 'credit' ? number_format((float) $transaction->amount, 2) : '—')
            ->editColumn('amount', fn (Transaction $transaction) => number_format((float) $transaction->amount, 2))
            ->editColumn('date', fn (Transaction $transaction) => $transaction->date?->format('Y-m-d'))
            ->make(true);
    }

    public function accountLedgers(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['Super Admin', 'Admin', 'Branch']) || $request->user()->can('view journal'), 403);
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        if ($from > $to) [$from, $to] = [$to, $from];

        $userBranchId = auth()->user()->branch_id;
        $branchId = $userBranchId ?: ($request->integer('branch_id') ?: null);
        $accountType = in_array($request->account_type, ['customer', 'bank'], true) ? $request->account_type : null;

        $accountReference = Schema::hasColumn('transactions', 'account_id')
            ? 'transactions.account_id'
            : 'transactions.accountable_id';

        $query = DB::table('transactions')
            ->join('accounts', function ($join) use ($accountReference) {
                $join->on($accountReference, '=', 'accounts.id');
            })
            ->join('currencies', 'transactions.currency_id', '=', 'currencies.id')
            ->leftJoin('branches', 'transactions.branch_id', '=', 'branches.id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.is_approved', true)
            ->whereIn('accounts.type', ['customer', 'bank'])
            ->whereBetween('transactions.date', [$from, $to])
            ->when(! Schema::hasColumn('transactions', 'account_id'), fn ($q) => $q->where('transactions.accountable_type', Account::class))
            ->when($branchId, fn ($q, $value) => $q->where('transactions.branch_id', $value))
            ->when($accountType, fn ($q, $value) => $q->where('accounts.type', $value))
            ->when($request->account_id, fn ($q, $value) => $q->where('accounts.id', $value))
            ->when($request->currency_id, fn ($q, $value) => $q->where('transactions.currency_id', $value))
            ->select([
                'accounts.id as account_id', 'accounts.account_code', 'accounts.full_name', 'accounts.type as account_type',
                'currencies.id as currency_id', 'currencies.code as currency', 'branches.name as branch_name',
                DB::raw("SUM(CASE WHEN transactions.direction = 'debit' THEN transactions.amount ELSE 0 END) as debit"),
                DB::raw("SUM(CASE WHEN transactions.direction = 'credit' THEN transactions.amount ELSE 0 END) as credit"),
                DB::raw("SUM(CASE WHEN transactions.direction = 'debit' THEN transactions.amount ELSE -transactions.amount END) as balance"),
                DB::raw('COUNT(transactions.id) as entries_count'),
            ])
            ->groupBy('accounts.id', 'accounts.account_code', 'accounts.full_name', 'accounts.type', 'currencies.id', 'currencies.code', 'branches.name')
            ->orderBy('accounts.full_name')->orderBy('currencies.code');

        $rows = $query->paginate(40)->withQueryString();
        $totals = collect($rows->items())->groupBy('currency')->map(fn ($items) => [
            'debit' => $items->sum('debit'), 'credit' => $items->sum('credit'), 'balance' => $items->sum('balance'),
        ]);

        return view('journal.transactions.account-ledgers', [
            'rows' => $rows, 'totals' => $totals, 'from' => $from, 'to' => $to,
            'branchId' => $branchId, 'accountType' => $accountType,
            'branches' => $userBranchId ? Branch::whereKey($userBranchId)->get() : Branch::orderBy('name')->get(),
            'accounts' => Account::whereIn('type', ['customer', 'bank'])->where('is_active', true)
                ->when($userBranchId, fn ($q) => $q->where('branch_id', $userBranchId))->orderBy('full_name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'chartOfAccounts' => ChartOfAccount::where('is_active', true)->orderBy('code')->get(),
            'defaultCoaIds' => ChartOfAccount::whereIn('code', ['1000', '1001', '1002'])->pluck('id', 'code'),
            'reversalHistory' => DB::table('journal_reversals')
                ->join('transactions as original', 'original.linked_id', '=', 'journal_reversals.original_linked_id')
                ->select('journal_reversals.*', 'original.branch_id')
                ->distinct()
                ->when($userBranchId, fn ($q) => $q->where('original.branch_id', $userBranchId))
                ->orderByDesc('journal_reversals.created_at')->limit(15)->get(),
            'reversibleJournals' => Transaction::query()->where('module_name', 'account_ledger')
                ->where('approval_status', 'approved')->where('is_approved', true)
                ->when($userBranchId, fn ($q) => $q->where('branch_id', $userBranchId))
                ->whereNotIn('linked_id', DB::table('journal_reversals')->select('original_linked_id'))
                ->select('linked_id', DB::raw('MAX(date) as date'))
                ->groupBy('linked_id')->orderByDesc('date')->limit(30)->get(),
        ]);
    }

    public function storeAccountLedgerEntry(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['Super Admin', 'Admin', 'Branch']), 403);
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'date' => ['required', 'date'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'exchange_rate_afn' => ['required', 'numeric', 'gt:0'],
            'direction' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $account = Account::whereIn('type', ['customer', 'bank'])->findOrFail($data['account_id']);
        $branchId = auth()->user()->branch_id ?: ($data['branch_id'] ?? $account->branch_id);
        abort_unless($branchId, 422, 'A branch is required.');
        abort_if(auth()->user()->branch_id && (int) auth()->user()->branch_id !== (int) $account->branch_id, 403);
        abort_if((int) $branchId !== (int) $account->branch_id, 422, 'Journal branch must match the selected account branch.');

        $safeCoaId = ChartOfAccount::where('code', '1000')->value('id');
        $accountCoaId = ChartOfAccount::where('code', $account->type === 'bank' ? '1001' : '1002')->value('id');
        abort_unless($safeCoaId && $accountCoaId, 422, 'Required Safe, Bank, or Accounts Receivable chart accounts are missing.');

        // Customer credit receives cash; customer debit pays cash out.
        $safeDirection = $data['direction'] === 'debit' ? 'credit' : 'debit';
        $linkedId = (string) Str::uuid();
        $common = [
            'branch_id' => $branchId, 'date' => $data['date'], 'currency_id' => $data['currency_id'],
            'amount' => $data['amount'], 'exchange_rate_afn' => $data['exchange_rate_afn'],
            'amount_afn' => round($data['amount'] * $data['exchange_rate_afn'], 2),
            'description' => ($data['description'] ?? null) ?: 'Manual '.$account->type.' ledger entry: '.$account->full_name,
            'module_name' => 'account_ledger', 'created_by' => auth()->id(), 'linked_id' => $linkedId,
            'is_approved' => true, 'approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(),
        ];

        \App\Services\TransactionService::postMany([
            $common + [
                'chart_of_account_id' => $accountCoaId, 'direction' => $data['direction'],
                'account_id' => $account->id, 'accountable_type' => Account::class, 'accountable_id' => $account->id,
            ],
            $common + [
                'chart_of_account_id' => $safeCoaId, 'direction' => $safeDirection, 'is_safe' => true,
                'account_id' => null, 'accountable_type' => null, 'accountable_id' => null,
            ],
        ], $linkedId);

        return redirect()->route('journal.transactions.account-ledgers', [
            'from' => $data['date'], 'to' => $data['date'], 'account_id' => $account->id,
        ])->with('success', __('Ledger transaction stored successfully. Reference: :reference', [
            'reference' => $linkedId,
        ]));
    }

    public function summary(Request $request)
    {
        $summary = [];
        foreach ($this->journalQuery($request)->setEagerLoads([])->with('currency')->selectRaw('currency_id, direction, SUM(amount) as amount')->groupBy('currency_id', 'direction')->get() as $entry) {
            $currency = $entry->currency?->code ?? '—';
            $summary[$currency] ??= ['credit' => 0.0, 'debit' => 0.0, 'balance' => 0.0, 'current_balance' => 0.0];
            $summary[$currency][$entry->direction] += (float) $entry->amount;
            $summary[$currency]['balance'] += $entry->direction === 'debit' ? (float) $entry->amount : -(float) $entry->amount;
        }
        foreach ($this->journalQuery($request, false)->setEagerLoads([])->with('currency')->where('is_approved', true)->selectRaw('currency_id, direction, SUM(amount) as amount')->groupBy('currency_id', 'direction')->get() as $entry) {
            $currency = $entry->currency?->code ?? '—';
            $summary[$currency] ??= ['credit' => 0.0, 'debit' => 0.0, 'balance' => 0.0, 'current_balance' => 0.0];
            $summary[$currency]['current_balance'] += $entry->direction === 'debit' ? (float) $entry->amount : -(float) $entry->amount;
        }

        return view('journal.transactions.summary', [
            'summary' => $summary,
            'isTreasury' => $request->input('entry_scope', 'cash') === 'cash',
        ]);
    }

    public function export(Request $request)
    {
        return Pdf::loadView('journal.transactions.export', [
            'transactions' => $this->journalQuery($request)->orderByDesc('date')->get(),
            'request' => $request,
        ])->setPaper('a4', 'landscape')->download('journal-transactions.pdf');
    }

    private function journalQuery(Request $request, bool $withDates = true)
    {
        $query = Transaction::with(['account', 'branch', 'currency', 'transactionType', 'chartOfAccount', 'linkedEntries.account'])
            ->when($request->currency_id, fn ($q) => $q->where('transactions.currency_id', $request->currency_id))
            ->when($request->type_id, fn ($q) => $q->where('transactions.transaction_type_id', $request->type_id))
            ->when(auth()->user()->branch_id ?: $request->branch_id, fn ($q, $branch) => $q->where('transactions.branch_id', $branch));

        if ($request->account_id) {
            $query->where(function ($q) use ($request) {
                $q->where('transactions.account_id', $request->account_id)
                    ->orWhereExists(function ($linked) use ($request) {
                        $linked->selectRaw('1')->from('transactions as linked')
                            ->whereColumn('linked.linked_id', 'transactions.linked_id')
                            ->whereColumn('linked.branch_id', 'transactions.branch_id')
                            ->whereColumn('linked.currency_id', 'transactions.currency_id')
                            ->whereColumn('linked.id', '!=', 'transactions.id')
                            ->where('linked.linked_id', '!=', '')
                            ->whereNull('linked.deleted_at')
                            ->where('linked.account_id', $request->account_id);
                    });
            });
        }
        JournalEntryFilter::apply($query, (string) $request->input('entry_scope', 'cash'));
        if ($withDates) {
            $this->applyDateRange($query, $request->date_range ?: now()->toDateString().' - '.now()->toDateString(), 'transactions.date');
        }

        return $query;
    }

    private function applyDateRange($query, ?string $dateRange, string $column = 'date'): void
    {
        if (! $dateRange || ! str_contains($dateRange, ' - ')) {
            return;
        }

        [$start, $end] = array_map('trim', explode(' - ', $dateRange, 2));
        $query->whereDate($column, '>=', $start)->whereDate($column, '<=', $end);
    }
}
