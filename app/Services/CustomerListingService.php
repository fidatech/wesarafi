<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\LedgerBalance;
use Illuminate\Http\Request;

class CustomerListingService
{
    public function query(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:0,1'], 'balance' => ['nullable', 'in:credit,debit,zero,nonzero'],
            'customer_type' => ['nullable', 'integer', 'exists:account_sub_categories,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
        ]);
        $branch = $request->user()->branch_id;
        $query = $this->withBalances(Account::with(['subCategory', 'branch']), $request)->where('type', 'customer')->whereNull('accounts.deleted_at')
            ->when($branch, fn ($q) => $q->where('accounts.branch_id', $branch))
            ->when($request->customer_type, fn ($q) => $q->where('account_sub_category_id', $request->customer_type))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('full_name', 'like', $search)->orWhere('account_code', 'like', $search)->orWhere('phone', 'like', $search));
            });
        if ($request->balance) {
            $operator = match ($request->balance) { 'credit' => '<', 'debit' => '>', default => '<>' };
            $balances = Transaction::query()->select('account_id')->where('is_approved', true)
                ->when($branch, fn ($q) => $q->where('branch_id', $branch))
                ->when($request->currency_id, fn ($q) => $q->where('currency_id', $request->currency_id))
                ->whereNotNull('account_id')->groupBy('account_id', 'currency_id')
                ->havingRaw("ROUND(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 2) {$operator} 0");
            $request->balance === 'zero' ? $query->whereNotIn('accounts.id', $balances) : $query->whereIn('accounts.id', $balances);
        }

        return $query;
    }

    public function withBalances($query, Request $request)
    {
        $branch = $request->user()->branch_id ?: ($request->integer('branch_id') ?: null);
        return $query->whereNull('accounts.deleted_at')
            ->when($branch, fn ($q) => $q->where('accounts.branch_id', $branch))
            ->with(['transactions' => function ($q) use ($request, $branch) {
                $q->where('is_approved', true)
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch))
                    ->when($request->currency_id, fn ($q) => $q->where('currency_id', $request->currency_id))
                    ->selectRaw("account_id, currency_id, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance")
                    ->groupBy('account_id', 'currency_id')->with('currency');
            }]);
    }

    private ?\Illuminate\Support\Collection $currencies = null;

    public function balanceHtml(Account $account, ?int $currencyId = null): string
    {
        $this->currencies ??= \App\Models\Currency::where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get();
        $balances = $account->transactions->keyBy('currency_id');
        $currencies = $this->currencies->merge($account->transactions->pluck('currency')->filter())->unique('id');
        if ($currencyId) $currencies = $currencies->where('id', $currencyId);

        return $currencies->map(function ($currency) use ($account, $balances) {
            $net = (float) ($balances->get($currency->id)?->balance ?? 0);
            // Customer/branch funds held are credit-positive; bank/cash assets are debit-positive.
            $amount = round(in_array($account->type, ['bank', 'safe'], true) ? $net : -$net, 2);
            $color = $amount > 0 ? 'text-success' : ($amount < 0 ? 'text-danger' : 'text-body');
            return '<div class="text-nowrap '.$color.'">'.e($currency->code).
                ' <strong>'.e(number_format($amount == 0 ? 0 : $amount, 2)).'</strong></div>';
        })->implode('') ?: '<strong>0.00</strong>';
    }

    public function balanceText(Account $account, ?int $currencyId = null): string
    {
        $this->currencies ??= \App\Models\Currency::where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get();
        $balances = $account->transactions->keyBy('currency_id');
        $currencies = $this->currencies->merge($account->transactions->pluck('currency')->filter())->unique('id');
        if ($currencyId) $currencies = $currencies->where('id', $currencyId);

        return $currencies->map(fn ($currency) => $currency->code.' '.LedgerBalance::format($balances->get($currency->id)?->balance ?? 0))
            ->implode(' | ') ?: '0.00';
    }
}
