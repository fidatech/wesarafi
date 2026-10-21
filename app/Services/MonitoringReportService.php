<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonitoringReportService
{
    public const TITLES = [
        'cumulative' => 'Cumulative Threshold Review', 'inactive' => 'Inactive Accounts',
        'cash-flow' => 'Cash Flow', 'large' => 'Large Individual Transactions',
        'rapid' => 'Rapid Money In / Out', 'kyc' => 'KYC Review', 'exceptions' => 'Transaction Exceptions',
    ];

    public array $warnings = [];

    public function context(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id', 'currency_id' => 'nullable|integer|exists:currencies,id',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'threshold' => 'nullable|numeric|min:1', 'days' => 'nullable|integer|min:1|max:3650',
            'window_days' => 'nullable|integer|min:1|max:30',
        ]);

        return ['from' => $request->date('from')?->toDateString() ?? Carbon::parse($request->input('to') ?: now()->toDateString())->startOfMonth()->toDateString(),
            'to' => $request->date('to')?->toDateString() ?? now()->toDateString(),
            'branch_id' => $request->user()->branch_id ?: ($request->integer('branch_id') ?: null),
            'currency_id' => $request->integer('currency_id') ?: null, 'account_id' => $request->integer('account_id') ?: null,
            'threshold' => (float) ($request->input('threshold') ?: 500000), 'days' => $request->integer('days') ?: 60,
            'window_days' => $request->integer('window_days') ?: 1];
    }

    private function transactions(array $c)
    {
        return Transaction::with(['account', 'currency', 'branch', 'chartOfAccount'])
            ->when($c['branch_id'], fn ($q) => $q->where('branch_id', $c['branch_id']))
            ->when($c['currency_id'], fn ($q) => $q->where('currency_id', $c['currency_id']));
    }

    private function accounts(array $c)
    {
        return Account::with('branch')->whereNull('deleted_at')->whereIn('type', ['customer', 'bank'])
            ->when($c['branch_id'], fn ($q) => $q->where('branch_id', $c['branch_id']))
            ->when($c['account_id'], fn ($q) => $q->whereKey($c['account_id']));
    }

    public function afn(Transaction $t): ?float
    {
        if ($t->amount_afn !== null && (float) $t->amount_afn > 0) {
            return (float) $t->amount_afn;
        }
        if ($t->currency?->code === 'AFN') {
            return (float) $t->amount;
        }
        if ((float) $t->exchange_rate_afn > 0) {
            return round((float) $t->amount * (float) $t->exchange_rate_afn, 2);
        }
        $this->warnings[] = "Transaction #{$t->id} has no recorded AFN amount or valid rate; its equivalent was not estimated.";

        return null;
    }

    private function detail(Transaction $t): array
    {
        return ['ID' => $t->id, 'Date' => $t->date?->toDateString(), 'Account' => $t->account?->full_name ?? '—',
            'Direction' => ucfirst($t->direction), 'Amount' => number_format((float) $t->amount, 2).' '.$t->currency?->code,
            'AFN' => $this->afn($t), 'Reference' => $t->linked_id, 'Description' => $t->description];
    }

    private function row(string $key, int $branch, array $cells, Collection $details): array
    {
        return ['key' => $key, 'branch_id' => $branch, 'cells' => $cells,
            'details' => $details->map(fn ($t) => $this->detail($t))->values()->all()];
    }

    public function build(string $report, array $c): Collection
    {
        abort_unless(isset(self::TITLES[$report]), 404);
        $this->warnings = [];
        if ($report === 'inactive' || $report === 'kyc') {
            return $this->accountChecks($report, $c);
        }
        if ($report === 'cash-flow') {
            return $this->cashFlow($c);
        }
        $query = $this->transactions($c)->whereDate('date', '>=', $c['from'])->whereDate('date', '<=', $c['to']);
        if ($report !== 'exceptions') {
            $query->where('is_approved', true)->whereIn('account_id', $this->accounts($c)->select('accounts.id'));
        }
        $entries = $query->orderBy('date')->orderBy('id')->get();
        if ($report === 'exceptions') {
            return $this->exceptions($entries, $c);
        }
        $rows = collect();
        if ($report === 'large') {
            foreach ($entries as $t) {
                if (($this->afn($t) ?? 0) >= $c['threshold']) {
                    $rows->push($this->row('transaction-'.$t->id, $t->branch_id,
                        ['Account' => $t->account->full_name, 'Branch' => $t->branch?->name, 'Date' => $t->date->toDateString(),
                            'Direction' => ucfirst($t->direction), 'AFN equivalent' => $this->afn($t), 'Result' => 'Requires review'], collect([$t])));
                }
            }

            return $rows;
        }
        if ($report === 'rapid') {
            return $this->rapid($entries, $c);
        }
        foreach ($entries->groupBy(fn ($t) => $t->branch_id.':'.$t->account_id.':'.$t->direction) as $key => $group) {
            $total = $group->sum(fn ($t) => $this->afn($t) ?? 0);
            if ($group->count() >= 2 && $total >= $c['threshold']) {
                $first = $group->first();
                $rows->push($this->row($key, $first->branch_id, ['Account' => $first->account->full_name,
                    'Branch' => $first->branch?->name, 'Direction' => ucfirst($first->direction),
                    'Transactions' => $group->count(), 'Total AFN' => round($total, 2),
                    'Individually below threshold' => $group->filter(fn ($t) => ($this->afn($t) ?? INF) < $c['threshold'])->count(),
                    'Result' => 'Requires review'], $group));
            }
        }

        return $rows;
    }

    private function accountChecks(string $report, array $c): Collection
    {
        $asOf = Carbon::parse($c['to'])->endOfDay();
        $cutoff = Carbon::parse($c['to'])->subDays($c['days'])->startOfDay();
        $rows = collect();
        foreach ($this->accounts($c)->with(['transactions' => fn ($q) => $q->whereDate('date', '<=', $c['to'])->with('currency')->orderByDesc('date')])->get() as $account) {
            if ($account->created_at->gt($asOf)) {
                continue;
            }
            $account->setRelation('transactions', $account->transactions->filter(fn ($t) => $t->branch_id == $account->branch_id)->values());
            $issues = [];
            $last = $account->transactions->first(); // Any nondeleted debit/credit counts as activity, including pending.
            if ($report === 'inactive') {
                if ($last && $last->date->gte($cutoff)) {
                    continue;
                }
                if (! $last && $account->created_at->gte($cutoff)) {
                    continue;
                }
                $cells = ['Account' => $account->full_name, 'Branch' => $account->branch?->name,
                    'Last transaction' => $last?->date?->toDateString() ?? 'Never transacted',
                    'Days inactive' => (int) ($last?->date ?? $account->created_at)->diffInDays($asOf),
                    'Status' => $account->is_active ? 'Active' : 'Inactive',
                    'Balances' => $account->transactions->where('is_approved', true)->groupBy('currency_id')->map(function ($group) use ($account) {
                        $net = $group->sum(fn ($t) => $t->direction === 'debit' ? (float) $t->amount : -(float) $t->amount);

                        return $group->first()->currency?->code.' '.number_format($account->type === 'bank' ? $net : -$net, 2);
                    })->implode(' | ') ?: '0.00'];
            } else {
                if (! $account->id_card_number || ! $account->id_card_file) {
                    $issues[] = 'Missing ID number or document';
                }
                if (! $account->phone) {
                    $issues[] = 'Missing phone';
                }
                if (! $account->is_sanction_list_applied) {
                    $issues[] = 'Sanction check not confirmed';
                }
                if (! $account->kyc_date || Carbon::parse($account->kyc_date)->lt($asOf->copy()->startOfDay())) {
                    $issues[] = 'KYC review missing or overdue';
                }
                if ($account->license_expiry_date && Carbon::parse($account->license_expiry_date)->lt($asOf->copy()->startOfDay())) {
                    $issues[] = 'Business license expired';
                }
                if (! $issues) {
                    continue;
                }
                $cells = ['Account' => $account->full_name, 'Branch' => $account->branch?->name,
                    'Risk' => ucfirst($account->risk_level ?? 'Unknown'), 'Issues' => implode('; ', $issues)];
            }
            $rows->push($this->row('account-'.$account->id, $account->branch_id, $cells, collect()));
        }

        return $rows;
    }

    private function cashFlow(array $c): Collection
    {
        $query = $this->transactions($c)->where('is_approved', true)->whereDate('date', '<=', $c['to'])
            ->where(fn ($q) => $q->where('is_safe', true)->orWhereHas('chartOfAccount', fn ($coa) => $coa->where('code', '1000')));
        if ($c['account_id']) {
            $query->whereExists(function ($q) use ($c) {
                $q->selectRaw('1')->from('transactions as linked')->whereColumn('linked.linked_id', 'transactions.linked_id')
                    ->whereColumn('linked.branch_id', 'transactions.branch_id')->whereColumn('linked.currency_id', 'transactions.currency_id')
                    ->where('linked.account_id', $c['account_id'])->whereNull('linked.deleted_at');
            });
        }
        $entries = $query->orderBy('date')->orderBy('id')->get()->groupBy(fn ($t) => $t->branch_id.':'.$t->currency_id);
        $rows = collect();
        foreach (Branch::when($c['branch_id'], fn ($q) => $q->whereKey($c['branch_id']))->get() as $branch) {
            $currencyIds = $entries->flatten(1)->pluck('currency_id')->unique();
            $currencies = Currency::where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $currencyIds))
                ->when($c['currency_id'], fn ($q) => $q->whereKey($c['currency_id']))->orderBy('code')->get();
            foreach ($currencies as $currency) {
                $group = $entries->get($branch->id.':'.$currency->id, collect());
                $opening = $group->filter(fn ($t) => $t->date->toDateString() < $c['from'])->sum(fn ($t) => $t->direction === 'debit' ? (float) $t->amount : -(float) $t->amount);
                $period = $group->filter(fn ($t) => $t->date->toDateString() >= $c['from']);
                $in = (float) $period->where('direction', 'debit')->sum('amount');
                $out = (float) $period->where('direction', 'credit')->sum('amount');
                $rows->push($this->row($branch->id.':'.$currency->id, $branch->id,
                    ['Branch' => $branch->name, 'Currency' => $currency->code, 'Opening' => $opening,
                        'Money in' => $in, 'Money out' => $out, 'Closing' => $opening + $in - $out], $period));
            }
        }

        return $rows;
    }

    private function rapid(Collection $entries, array $c): Collection
    {
        $rows = collect();
        foreach ($entries->groupBy(fn ($t) => $t->branch_id.':'.$t->account_id.':'.$t->currency_id) as $group) {
            $incomingDirection = $group->first()->account?->type === 'bank' ? 'debit' : 'credit';
            foreach ($group->where('direction', $incomingDirection) as $incoming) {
                $end = $incoming->date->copy()->addDays($c['window_days']);
                $outgoing = $group->filter(fn ($t) => $t->direction !== $incomingDirection && $t->date->gte($incoming->date)
                    && ($t->date->gt($incoming->date) || $t->id > $incoming->id) && $t->date->lte($end));
                $received = $this->afn($incoming);
                $sent = $outgoing->sum(fn ($t) => $this->afn($t) ?? 0);
                if ($received === null || $received < $c['threshold'] || $sent < $received * .8) {
                    continue;
                }
                $rows->push($this->row('rapid-'.$incoming->id, $incoming->branch_id,
                    ['Account' => $incoming->account->full_name, 'Branch' => $incoming->branch?->name,
                        'Received on' => $incoming->date->toDateString(), 'Received AFN' => $received,
                        'Outgoing AFN in window' => $sent, 'Window days' => $c['window_days'], 'Result' => 'Requires review'], collect([$incoming])->concat($outgoing)));
            }
        }

        return $rows;
    }

    private function exceptions(Collection $entries, array $c): Collection
    {
        $rows = collect();
        foreach ($entries as $t) {
            if ($c['account_id'] && $t->account_id != $c['account_id']) {
                continue;
            }
            $issues = [];
            if (! $t->is_approved) {
                $issues[] = 'Not approved';
            }
            if ($this->afn($t) === null) {
                $issues[] = 'Missing AFN amount / rate';
            }
            if ($t->date && $t->created_at->copy()->startOfDay()->diffInDays($t->date, false) < -7) {
                $issues[] = 'Posted more than 7 days after transaction date';
            }
            if (! $t->linked_id) {
                $issues[] = 'Missing linked reference';
            }
            if ($issues) {
                $rows->push($this->row('exception-'.$t->id, $t->branch_id,
                    ['Reference' => '#'.$t->id, 'Branch' => $t->branch?->name, 'Issues' => implode('; ', $issues)], collect([$t])));
            }
        }
        // Inspect complete linked groups, not a currency/date slice that could appear unbalanced.
        foreach ($entries->pluck('linked_id')->filter()->unique() as $linked) {
            $group = Transaction::with(['account', 'currency', 'branch', 'chartOfAccount'])->where('linked_id', $linked)->get();
            if ($c['branch_id'] && $group->contains(fn ($t) => $t->branch_id != $c['branch_id'])) {
                continue;
            }
            if ($c['account_id'] && ! $group->contains('account_id', $c['account_id'])) {
                continue;
            }
            if ($group->contains(fn ($t) => $this->afn($t) === null)) {
                continue;
            }
            $net = $group->sum(fn ($t) => $t->direction === 'debit' ? $this->afn($t) : -$this->afn($t));
            if (abs($net) > .02) {
                $rows->push($this->row('group-'.$linked, $group->first()->branch_id,
                    ['Reference' => $linked, 'Branch' => $group->first()->branch?->name, 'Issues' => 'Unbalanced linked group; AFN difference '.number_format($net, 2)], $group));
            }
        }

        return $rows;
    }
}
