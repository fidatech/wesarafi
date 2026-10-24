<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\FxRate;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialReportService
{
    private array $rateCache = [];
    private array $warnings = [];
    private int $legacyFallbackCount = 0;

    public function balanceSheet(string $date, ?int $branchId = null): array
    {
        $this->resetState();

        $transactions = $this->transactionQuery($branchId)
            ->whereDate('date', '<=', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $position = $this->buildBalanceSheetPosition($transactions, $date, $branchId);
        $yearStart = Carbon::parse($date)->startOfYear()->toDateString();
        $profitLoss = $this->profitLossInternal($yearStart, $date, $branchId, false);

        $equityRows = $position['equity']->reject(
            fn (array $row) => $row['code'] === config('dab_reports.current_profit_loss_code', '3002')
        )->values();

        $postedCurrentPnl = $position['equity']->firstWhere(
            'code',
            config('dab_reports.current_profit_loss_code', '3002')
        );

        if ($postedCurrentPnl && abs((float) $postedCurrentPnl['columns']['total']) > 0.005) {
            $this->warnings[] = 'COA 3002 contains posted entries. The Balance Sheet excludes that ledger balance and uses the calculated current-year Profit/Loss once to prevent double counting.';
        }

        $calculatedPnlRow = [
            'code' => '3002-CALC',
            'name' => 'Current Year Profit / Loss',
            'subcategory' => "Owner's Equity",
            'type' => 'equity',
            'columns' => $profitLoss['net_profit'],
            'calculated' => true,
        ];
        $equityRows->push($calculatedPnlRow);

        $assets = $this->sectionPayload($position['assets']);
        $liabilities = $this->sectionPayload($position['liabilities']);
        $equity = $this->sectionPayload($equityRows);
        $liabilitiesEquity = $this->addColumns($liabilities['total'], $equity['total']);
        $difference = $this->subtractColumns($assets['total'], $liabilitiesEquity);

        if (abs($difference['total']) > 0.50) {
            $this->warnings[] = 'The report is not balanced. This normally means one or more historical journal groups were posted without equal AFN debit and credit values.';
        }

        $this->appendLegacyWarning();

        return [
            'as_of' => $date,
            'branch_id' => $branchId,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'liabilities_equity' => $liabilitiesEquity,
            'difference' => $difference,
            'warnings' => array_values(array_unique($this->warnings)),
            'currency_headers' => $this->currencyHeaders(),
        ];
    }

    public function profitLoss(string $from, string $to, ?int $branchId = null): array
    {
        $this->resetState();
        $report = $this->profitLossInternal($from, $to, $branchId, true);
        $this->appendLegacyWarning();
        $report['warnings'] = array_values(array_unique($this->warnings));

        return $report;
    }

    public function trialBalance(string $date, ?int $branchId = null): array
    {
        $this->resetState();

        $transactions = $this->transactionQuery($branchId)
            ->whereDate('date', '<=', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($transactions->groupBy('chart_of_account_id') as $group) {
            $account = $group->first()->chartOfAccount;
            if (! $account) {
                continue;
            }

            $debit = 0.0;
            $credit = 0.0;

            foreach ($group as $transaction) {
                $amountAfn = $this->historicalAfn($transaction, $branchId);
                if ($transaction->direction === 'debit') {
                    $debit += $amountAfn;
                } else {
                    $credit += $amountAfn;
                }
            }

            $net = $debit - $credit;
            $rows[] = [
                'code' => $account->code,
                'name' => $account->account_name,
                'type' => $account->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance_debit' => round(max($net, 0), 2),
                'balance_credit' => round(max(-$net, 0), 2),
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        usort($rows, fn (array $a, array $b) => strnatcmp($a['code'], $b['code']));
        $this->appendLegacyWarning();

        return [
            'as_of' => $date,
            'rows' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'difference' => round($totalDebit - $totalCredit, 2),
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    public function openFxPosition(string $date, ?int $branchId = null): array
    {
        $this->resetState();

        $transactions = $this->transactionQuery($branchId)
            ->whereDate('date', '<=', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $baseCode = config('dab_reports.base_currency', 'AFN');
        $positions = [];

        foreach ($transactions as $transaction) {
            $account = $transaction->chartOfAccount;
            $currency = $transaction->currency;

            if (! $account || ! $currency || $currency->code === $baseCode || ! $this->isMonetary($account->code)) {
                continue;
            }

            $key = (string) $currency->id;
            $positions[$key] ??= [
                'currency_id' => $currency->id,
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'asset_units' => 0.0,
                'liability_units' => 0.0,
            ];

            if ($account->type === 'asset') {
                $positions[$key]['asset_units'] += $transaction->direction === 'debit'
                    ? (float) $transaction->amount
                    : -(float) $transaction->amount;
            } elseif ($account->type === 'liability') {
                $positions[$key]['liability_units'] += $transaction->direction === 'credit'
                    ? (float) $transaction->amount
                    : -(float) $transaction->amount;
            }
        }

        $rows = [];
        $totalAssetsAfn = 0.0;
        $totalLiabilitiesAfn = 0.0;

        foreach ($positions as $position) {
            $rate = $this->rate($date, 'close', (int) $position['currency_id'], $branchId);
            $assetsAfn = $position['asset_units'] * $rate;
            $liabilitiesAfn = $position['liability_units'] * $rate;

            $rows[] = [
                ...$position,
                'rate' => round($rate, 8),
                'asset_afn' => round($assetsAfn, 2),
                'liability_afn' => round($liabilitiesAfn, 2),
                'net_units' => round($position['asset_units'] - $position['liability_units'], 4),
                'net_afn' => round($assetsAfn - $liabilitiesAfn, 2),
            ];

            $totalAssetsAfn += $assetsAfn;
            $totalLiabilitiesAfn += $liabilitiesAfn;
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['currency_code'], $b['currency_code']));

        return [
            'as_of' => $date,
            'rows' => $rows,
            'total_assets_afn' => round($totalAssetsAfn, 2),
            'total_liabilities_afn' => round($totalLiabilitiesAfn, 2),
            'net_position_afn' => round($totalAssetsAfn - $totalLiabilitiesAfn, 2),
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    private function profitLossInternal(string $from, string $to, ?int $branchId, bool $includeMetadata): array
    {
        $transactions = $this->transactionQuery($branchId)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereHas('chartOfAccount', fn (Builder $query) => $query->whereIn('type', ['income', 'expense']))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($transactions->groupBy('chart_of_account_id') as $group) {
            $account = $group->first()->chartOfAccount;
            if (! $account) {
                continue;
            }

            $columns = $this->emptyColumns();
            foreach ($group as $transaction) {
                $normalAmount = $this->normalSignedAmount(
                    $account->type,
                    $transaction->direction,
                    $this->historicalAfn($transaction, $branchId)
                );
                $this->addToCurrencyColumn($columns, $transaction->currency?->code, $normalAmount);
            }

            $columns = $this->roundColumns($columns);
            if (abs($columns['total']) < 0.005) {
                continue;
            }

            $rows[] = [
                'code' => $account->code,
                'name' => $account->account_name,
                'subcategory' => $account->subcategory ?: ($account->type === 'income' ? 'Income' : 'Expenses'),
                'type' => $account->type,
                'columns' => $columns,
            ];
        }

        $openingDate = Carbon::parse($from)->subDay()->toDateString();
        $revaluationAtStart = $this->revaluationAsOf($openingDate, $branchId);
        $revaluationAtEnd = $this->revaluationAsOf($to, $branchId);
        $periodRevaluation = $this->subtractColumns($revaluationAtEnd, $revaluationAtStart);

        if (abs($periodRevaluation['total']) >= 0.005) {
            $isGain = $periodRevaluation['total'] >= 0;
            $rows[] = [
                'code' => $isGain ? 'FX-UNREALIZED-GAIN' : 'FX-UNREALIZED-LOSS',
                'name' => $isGain ? 'Unrealized Foreign Exchange Gain' : 'Unrealized Foreign Exchange Loss',
                'subcategory' => 'Foreign Exchange Revaluation',
                'type' => $isGain ? 'income' : 'expense',
                'columns' => $isGain
                    ? $periodRevaluation
                    : $this->multiplyColumns($periodRevaluation, -1),
                'calculated' => true,
            ];
        }

        usort($rows, function (array $a, array $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'income' ? -1 : 1;
            }

            return strnatcmp($a['code'], $b['code']);
        });

        $incomeRows = collect($rows)->where('type', 'income')->values();
        $expenseRows = collect($rows)->where('type', 'expense')->values();
        $incomeTotal = $this->sumRows($incomeRows);
        $expenseTotal = $this->sumRows($expenseRows);
        $netProfit = $this->subtractColumns($incomeTotal, $expenseTotal);

        $report = [
            'from' => $from,
            'to' => $to,
            'income' => $this->sectionPayload($incomeRows),
            'expenses' => $this->sectionPayload($expenseRows),
            'net_profit' => $netProfit,
            'revaluation' => $periodRevaluation,
            'currency_headers' => $this->currencyHeaders(),
        ];

        if ($includeMetadata) {
            $report['branch_id'] = $branchId;
        }

        return $report;
    }

    private function buildBalanceSheetPosition(Collection $transactions, string $date, ?int $branchId): array
    {
        $rows = [
            'assets' => collect(),
            'liabilities' => collect(),
            'equity' => collect(),
        ];

        foreach ($transactions->groupBy('chart_of_account_id') as $accountTransactions) {
            $account = $accountTransactions->first()->chartOfAccount;
            if (! $account || ! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
                continue;
            }

            $columns = $this->emptyColumns();

            foreach ($accountTransactions->groupBy('currency_id') as $currencyTransactions) {
                $currencyCode = $currencyTransactions->first()->currency?->code;
                $currencyId = (int) $currencyTransactions->first()->currency_id;
                $normalUnits = 0.0;
                $historicalAfn = 0.0;

                foreach ($currencyTransactions as $transaction) {
                    $normalUnits += $this->normalSignedAmount(
                        $account->type,
                        $transaction->direction,
                        (float) $transaction->amount
                    );
                    $historicalAfn += $this->normalSignedAmount(
                        $account->type,
                        $transaction->direction,
                        $this->historicalAfn($transaction, $branchId)
                    );
                }

                $valueAfn = $historicalAfn;
                if ($this->isMonetary($account->code) && in_array($account->type, ['asset', 'liability'], true)) {
                    $valueAfn = $normalUnits * $this->rate($date, 'close', $currencyId, $branchId);
                }

                $this->addToCurrencyColumn($columns, $currencyCode, $valueAfn);
            }

            $columns = $this->roundColumns($columns);
            if (abs($columns['total']) < 0.005) {
                continue;
            }

            $key = match ($account->type) {
                'asset' => 'assets',
                'liability' => 'liabilities',
                default => 'equity',
            };

            $rows[$key]->push([
                'code' => $account->code,
                'name' => $account->account_name,
                'subcategory' => $account->subcategory ?: $account->category,
                'type' => $account->type,
                'columns' => $columns,
                'monetary' => $this->isMonetary($account->code),
            ]);
        }

        foreach ($rows as $key => $collection) {
            $rows[$key] = $collection->sortBy(
                fn (array $row) => str_pad($row['code'], 16, '0', STR_PAD_LEFT)
            )->values();
        }

        return $rows;
    }

    private function revaluationAsOf(string $date, ?int $branchId): array
    {
        $transactions = $this->transactionQuery($branchId)
            ->whereDate('date', '<=', $date)
            ->whereHas('chartOfAccount', function (Builder $query) {
                $query->whereIn('type', ['asset', 'liability'])
                    ->whereIn('code', config('dab_reports.monetary_account_codes', []));
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $columns = $this->emptyColumns();
        $baseCode = config('dab_reports.base_currency', 'AFN');

        foreach ($transactions->groupBy(fn (Transaction $transaction) => $transaction->chart_of_account_id . ':' . $transaction->currency_id) as $group) {
            $first = $group->first();
            $account = $first->chartOfAccount;
            $currency = $first->currency;

            if (! $account || ! $currency || $currency->code === $baseCode) {
                continue;
            }

            $normalUnits = 0.0;
            $historicalAfn = 0.0;

            foreach ($group as $transaction) {
                $normalUnits += $this->normalSignedAmount(
                    $account->type,
                    $transaction->direction,
                    (float) $transaction->amount
                );
                $historicalAfn += $this->normalSignedAmount(
                    $account->type,
                    $transaction->direction,
                    $this->historicalAfn($transaction, $branchId)
                );
            }

            $closingAfn = $normalUnits * $this->rate($date, 'close', (int) $currency->id, $branchId);
            $changeInCarryingValue = $closingAfn - $historicalAfn;
            $profitLossEffect = $account->type === 'asset'
                ? $changeInCarryingValue
                : -$changeInCarryingValue;

            $this->addToCurrencyColumn($columns, $currency->code, $profitLossEffect);
        }

        return $this->roundColumns($columns);
    }

    private function transactionQuery(?int $branchId): Builder
    {
        return Transaction::query()
            ->with(['chartOfAccount', 'currency', 'account', 'transactionType', 'branch'])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('is_approved', true);
    }

    private function historicalAfn(Transaction $transaction, ?int $branchId): float
    {
        if ($transaction->amount_afn !== null && (float) $transaction->amount_afn >= 0) {
            return (float) $transaction->amount_afn;
        }

        $this->legacyFallbackCount++;
        $date = $transaction->date?->toDateString() ?: (string) $transaction->date;
        $rate = $transaction->exchange_rate_afn !== null && (float) $transaction->exchange_rate_afn > 0
            ? (float) $transaction->exchange_rate_afn
            : $this->rate($date, 'close', (int) $transaction->currency_id, $branchId ?? $transaction->branch_id);

        return round((float) $transaction->amount * $rate, 2);
    }

    private function rate(string $date, string $type, int $currencyId, ?int $branchId): float
    {
        $key = implode(':', [$date, $type, $currencyId, $branchId ?? 'all']);
        if (isset($this->rateCache[$key])) {
            return $this->rateCache[$key];
        }

        $currency = Currency::find($currencyId);
        if (! $currency) {
            $this->warnings[] = "Currency ID {$currencyId} was not found; AFN rate 1 was used.";
            return $this->rateCache[$key] = 1.0;
        }

        if ($currency->code === config('dab_reports.base_currency', 'AFN')) {
            return $this->rateCache[$key] = 1.0;
        }

        $hasRate = FxRate::query()
            ->where('currency_id', $currencyId)
            ->whereDate('as_of', '<=', $date)
            ->where(function ($query) use ($branchId) {
                if ($branchId) {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                } else {
                    $query->whereNull('branch_id');
                }
            })
            ->exists();

        if (! $hasRate) {
            $this->warnings[] = "No AFN rate was found for {$currency->code} on or before {$date}; rate 1 was used for legacy compatibility.";
        }

        return $this->rateCache[$key] = DailyValuationService::effectiveRate($date, $type, $currencyId, $branchId);
    }

    private function normalSignedAmount(string $accountType, string $direction, float $amount): float
    {
        $debitNormal = in_array($accountType, ['asset', 'expense'], true);
        $isPositive = $debitNormal ? $direction === 'debit' : $direction === 'credit';

        return $isPositive ? $amount : -$amount;
    }

    private function isMonetary(string $accountCode): bool
    {
        return in_array($accountCode, config('dab_reports.monetary_account_codes', []), true);
    }

    private function sectionPayload(Collection $rows): array
    {
        return [
            'groups' => $rows->groupBy('subcategory')->map(function (Collection $group, string $name) {
                return [
                    'name' => $name,
                    'rows' => $group->values(),
                    'total' => $this->sumRows($group),
                ];
            })->values(),
            'rows' => $rows->values(),
            'total' => $this->sumRows($rows),
        ];
    }

    private function sumRows(Collection $rows): array
    {
        $total = $this->emptyColumns();
        foreach ($rows as $row) {
            $total = $this->addColumns($total, $row['columns']);
        }

        return $this->roundColumns($total);
    }

    private function emptyColumns(): array
    {
        return ['total' => 0.0, 'afn' => 0.0, 'usd' => 0.0, 'other' => 0.0];
    }

    private function addToCurrencyColumn(array &$columns, ?string $currencyCode, float $amount): void
    {
        $baseCode = config('dab_reports.base_currency', 'AFN');
        $usdCode = config('dab_reports.usd_currency', 'USD');
        $bucket = $currencyCode === $baseCode ? 'afn' : ($currencyCode === $usdCode ? 'usd' : 'other');

        $columns[$bucket] += $amount;
        $columns['total'] += $amount;
    }

    private function addColumns(array $left, array $right): array
    {
        $result = $this->emptyColumns();
        foreach (array_keys($result) as $key) {
            $result[$key] = (float) ($left[$key] ?? 0) + (float) ($right[$key] ?? 0);
        }

        return $this->roundColumns($result);
    }

    private function subtractColumns(array $left, array $right): array
    {
        return $this->addColumns($left, $this->multiplyColumns($right, -1));
    }

    private function multiplyColumns(array $columns, float $factor): array
    {
        foreach ($columns as $key => $value) {
            $columns[$key] = (float) $value * $factor;
        }

        return $this->roundColumns($columns);
    }

    private function roundColumns(array $columns): array
    {
        foreach ($columns as $key => $value) {
            $columns[$key] = round((float) $value, 2);
        }

        return $columns;
    }

    private function currencyHeaders(): array
    {
        return [
            'total' => 'Total AFN',
            'afn' => 'AFN',
            'usd' => 'USD (AFN equivalent)',
            'other' => 'Other FX (AFN equivalent)',
        ];
    }

    private function appendLegacyWarning(): void
    {
        if ($this->legacyFallbackCount > 0) {
            $this->warnings[] = number_format($this->legacyFallbackCount) . ' legacy transaction line(s) do not have amount_afn. Their transaction-date effective closing rate was used as a fallback.';
        }
    }

    private function resetState(): void
    {
        $this->rateCache = [];
        $this->warnings = [];
        $this->legacyFallbackCount = 0;
    }
}
