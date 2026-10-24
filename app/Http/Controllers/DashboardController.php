<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shareholder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FxValuationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date'], 'branch_id' => ['nullable', 'integer'], 'currency_id' => ['nullable', 'integer', 'exists:currencies,id']]);
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $userBranchId = Auth::user()->branch_id;
        $branchId = $userBranchId ?: ($request->integer('branch_id') ?: null);
        $currencyId = $request->integer('currency_id') ?: null;
        $branches = Branch::query()->when($userBranchId, fn ($q) => $q->whereKey($userBranchId))->orderBy('name')->get();
        $currencies = \App\Models\Currency::where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get();
        $selectedBranch = $branchId ? $branches->firstWhere('id', $branchId) : null;
        $monthStart = Carbon::parse($date)->startOfMonth()->toDateString();

        $approvedTransactions = Transaction::query()
            ->where('is_approved', true)
            ->whereDate('date', '<=', $date)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));

        $treasuryQuery = (clone $approvedTransactions)->where(function ($q) {
            $q->where('is_safe', true)->orWhereHas('chartOfAccount', fn ($coa) => $coa->where('code', '1000'));
        });
        $positions = (clone $treasuryQuery)->select(['branch_id', 'currency_id'])
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) AS balance")
            ->groupBy('branch_id', 'currency_id')->get()->groupBy('currency_id');
        $cashCurrencies = \App\Models\Currency::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $positions->keys()))
            ->when($currencyId, fn ($q) => $q->whereKey($currencyId))->orderByDesc('is_default')->orderBy('code')->get();
        $cashBalances = $cashCurrencies->map(function ($currency) use ($positions, $date) {
            $rows = $positions->get($currency->id, collect());
            $equivalent = 0.0;
            $missingRate = false;
            foreach ($rows as $row) {
                if ((float) $row->balance == 0) continue;
                $hasRate = $currency->code === 'AFN' || \App\Models\FxRate::where('currency_id', $currency->id)
                    ->whereDate('as_of', '<=', $date)
                    ->where(fn ($q) => $q->where('branch_id', $row->branch_id)->orWhereNull('branch_id'))->exists();
                if (! $hasRate) { $missingRate = true; continue; }
                $equivalent += (float) $row->balance * FxValuationService::effectiveRate($date, 'close', $currency->id, $row->branch_id);
            }
            return ['currency_id' => $currency->id, 'code' => $currency->code, 'name' => $currency->name,
                'balance' => round((float) $rows->sum('balance'), 4),
                'afn_equivalent' => $missingRate ? null : round($equivalent, 2)];
        });
        $currentCashAfn = $cashBalances->contains(fn ($row) => $row['afn_equivalent'] === null)
            ? null : round((float) $cashBalances->sum('afn_equivalent'), 2);

        $temporaryCategoryId = AccountSubCategory::query()
            ->where('name_en', 'Temporary Customer')
            ->value('id');

        $summary = [
            'users' => User::query()
                ->whereNull('deleted_at')
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count(),
            'branches' => $branchId ? 1 : $branches->count(),
            'customers' => Account::query()
                ->whereNull('deleted_at')
                ->where('type', 'customer')
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count(),
            'temporary_customers' => Account::query()
                ->whereNull('deleted_at')
                ->where('type', 'customer')
                ->when($temporaryCategoryId, fn (Builder $query) => $query->where('account_sub_category_id', $temporaryCategoryId))
                ->when(! $temporaryCategoryId, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count(),
            'employees' => Employee::query()
                ->where('is_active', true)
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count(),
            'bank_accounts' => Account::query()
                ->whereNull('deleted_at')
                ->where('type', 'bank')
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count(),
            'shareholders' => Shareholder::query()->where('is_active', true)->count(),
            'today_transactions' => (clone $treasuryQuery)->whereDate('date', $date)->count(),
        ];

        $monthExpenses = (float) (clone $approvedTransactions)
            ->whereDate('date', '>=', $monthStart)
            ->whereHas('chartOfAccount', fn (Builder $query) => $query->where('type', 'expense'))
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN COALESCE(amount_afn, amount * COALESCE(exchange_rate_afn, 1)) ELSE -COALESCE(amount_afn, amount * COALESCE(exchange_rate_afn, 1)) END), 0) AS total")
            ->value('total');

        $bankTransactionVolume = (float) (clone $approvedTransactions)
            ->whereDate('date', '>=', $monthStart)
            ->where(function (Builder $query) {
                $query->whereHas('chartOfAccount', fn (Builder $coa) => $coa->where('code', '1001'))
                    ->orWhereHas('account', fn (Builder $account) => $account->where('type', 'bank'));
            })
            ->selectRaw("COALESCE(SUM(ABS(COALESCE(amount_afn, amount * COALESCE(exchange_rate_afn, 1)))), 0) AS total")
            ->value('total');

        $recentTransactions = (clone $treasuryQuery)
            ->with(['chartOfAccount:id,code,account_name', 'currency:id,code', 'branch:id,name', 'account:id,full_name', 'linkedEntries.account'])
            ->latest('date')
            ->latest('id')
            ->limit(12)
            ->get();

        // Chart values come from approved journal entries, not illustrative sample series.
        $chartStart = Carbon::parse($date)->subDays(6)->toDateString();
        $exchangeRows = (clone $approvedTransactions)
            ->whereDate('date', '>=', $chartStart)
            ->whereDate('date', '<=', $date)
            ->where(function (Builder $q) {
                $q->where('module_name', 'like', '%exchange%')
                  ->orWhere('module_name', 'like', '%remittance%');
            })
            ->selectRaw('date, module_name, direction, SUM(ABS(COALESCE(amount_afn, amount * COALESCE(exchange_rate_afn, 1)))) AS volume')
            ->groupBy('date', 'module_name', 'direction')->get();
        $exchangeChart = collect(range(6, 0))->map(function ($offset) use ($date, $exchangeRows) {
            $day = Carbon::parse($date)->subDays($offset)->toDateString();
            $rows = $exchangeRows->filter(fn ($row) => substr((string) $row->date, 0, 10) === $day);
            return [
                'date' => Carbon::parse($day)->format('M d'),
                'buy' => round((float) $rows->filter(fn ($r) => !str_contains(strtolower((string) $r->module_name), 'remittance') && $r->direction === 'debit')->sum('volume'), 2),
                'sell' => round((float) $rows->filter(fn ($r) => !str_contains(strtolower((string) $r->module_name), 'remittance') && $r->direction === 'credit')->sum('volume'), 2),
                'remittance' => round((float) $rows->filter(fn ($r) => str_contains(strtolower((string) $r->module_name), 'remittance'))->sum('volume'), 2),
            ];
        })->values();

        [$shareholders, $organizationTree] = $this->organizationTree($branchId);

        return view('dashboard', [
            'date' => $date,
            'currencyId' => $currencyId,
            'currencies' => $currencies,
            'monthStart' => $monthStart,
            'branchId' => $branchId,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'summary' => $summary,
            'cashBalances' => $cashBalances,
            'currentCashAfn' => $currentCashAfn,
            'monthExpenses' => round($monthExpenses, 2),
            'bankTransactionVolume' => round($bankTransactionVolume, 2),
            'recentTransactions' => $recentTransactions,
            'shareholders' => $shareholders,
            'organizationTree' => $organizationTree,
            'exchangeChart' => $exchangeChart,
        ]);
    }

    private function organizationTree(?int $branchId): array
    {
        $designations = Designation::query()->orderBy('id')->get();
        $employees = Employee::query()
            ->with(['branch:id,name', 'designation:id,name_en,name_fa,name_ps,line_manager'])
            ->where('is_active', true)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get()
            ->groupBy('designation_id');

        $shareholders = Shareholder::query()
            ->where('is_active', true)
            ->orderByDesc('share_percentage')
            ->orderBy('name')
            ->get();

        $shareholderDesignationIds = $designations
            ->filter(fn (Designation $designation) => strcasecmp(trim($designation->name_en), 'Shareholder') === 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $designationIds = $designations->pluck('id')->map(fn ($id) => (int) $id)->all();
        $roots = $designations->filter(function (Designation $designation) use ($shareholderDesignationIds, $designationIds) {
            if (in_array((int) $designation->id, $shareholderDesignationIds, true)) {
                return false;
            }

            $managerId = (int) ($designation->line_manager ?? 0);
            return ! $managerId
                || in_array($managerId, $shareholderDesignationIds, true)
                || ! in_array($managerId, $designationIds, true);
        });

        $visited = [];
        $buildNode = function (Designation $designation) use (&$buildNode, &$visited, $designations, $employees): array {
            if (isset($visited[$designation->id])) {
                return [];
            }
            $visited[$designation->id] = true;

            $children = $designations
                ->where('line_manager', $designation->id)
                ->map(fn (Designation $child) => $buildNode($child))
                ->filter()
                ->values()
                ->all();

            return [
                'id' => (int) $designation->id,
                'title' => $designation->name_en,
                'title_fa' => $designation->name_fa,
                'title_ps' => $designation->name_ps,
                'employees' => ($employees->get($designation->id) ?? collect())->values(),
                'children' => $children,
            ];
        };

        $tree = $roots->map(fn (Designation $root) => $buildNode($root))->filter()->values();

        $activeDesignationIds = $designations->pluck('id')->map(fn ($id) => (int) $id);
        $unassignedEmployees = $employees
            ->filter(fn ($group, $designationId) => ! $designationId || ! $activeDesignationIds->contains((int) $designationId))
            ->flatten(1)
            ->values();
        if ($unassignedEmployees->isNotEmpty()) {
            $tree->push([
                'id' => 0,
                'title' => 'Unassigned Employees',
                'title_fa' => 'کارمندان بدون بست',
                'title_ps' => 'بې دندې کارکوونکي',
                'employees' => $unassignedEmployees->values(),
                'children' => [],
            ]);
        }

        // Include any orphaned or cyclic designations once, instead of silently dropping their employees.
        $designations->each(function (Designation $designation) use (&$tree, &$visited, $shareholderDesignationIds, $buildNode) {
            if (! in_array((int) $designation->id, $shareholderDesignationIds, true) && ! isset($visited[$designation->id])) {
                $node = $buildNode($designation);
                if ($node) {
                    $tree->push($node);
                }
            }
        });

        return [$shareholders, $tree];
    }
}
