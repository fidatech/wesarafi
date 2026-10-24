<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\FxDailyValuation;
use App\Models\FxRate;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Stores daily opening/closing rates and a branch-level FX valuation snapshot.
 *
 * Realized FX P/L comes from the posted FX gain/loss accounts. Unrealized FX
 * P/L is the movement in the revaluation of foreign-currency monetary assets
 * and liabilities between the previous close and the current close.
 */
class DailyValuationService
{
    /** @var array<string,float> Request-local rate memoization. */
    private static array $effectiveRateCache = [];

    /** @var array<int,string|null> Request-local currency code memoization. */
    private static array $currencyCodeCache = [];

    /** Save opening rates for a date/branch. $rates = [currency_id => AFN per unit]. */
    public static function setOpeningRates(string $date, int $branchId, array $rates): void
    {
        DB::transaction(function () use ($date, $branchId, $rates) {
            foreach ($rates as $currencyId => $rate) {
                self::storeRate($date, 'open', (int) $currencyId, $branchId, (float) $rate);
            }
        });
    }

    /** Save closing rates and rebuild the daily FX valuation snapshot. */
    public static function setClosingAndValue(string $date, int $branchId, array $rates): int
    {
        return DB::transaction(function () use ($date, $branchId, $rates) {
            foreach ($rates as $currencyId => $rate) {
                self::storeRate($date, 'close', (int) $currencyId, $branchId, (float) $rate);
            }

            $previousDate = Carbon::parse($date)->subDay()->toDateString();
            $current = self::monetaryRevaluationAsOf($date, $branchId);
            $previous = self::monetaryRevaluationAsOf($previousDate, $branchId);
            $realized = self::realizedFxForDate($date, $branchId);
            $unrealizedMovement = round($current['total_revaluation_afn'] - $previous['total_revaluation_afn'], 2);

            $valuation = FxDailyValuation::query()->whereDate('as_of', $date)
                ->where('branch_id', $branchId)->first()
                ?? new FxDailyValuation(['as_of' => $date, 'branch_id' => $branchId]);
            $valuation->realized_pl_afn = $realized;
            $valuation->unrealized_pl_afn = $unrealizedMovement;
            $valuation->temp_pnl_afn = round($realized + $unrealizedMovement, 2);
            $valuation->save();

            $valuation->lines()->delete();
            foreach ($current['currencies'] as $currencyId => $position) {
                $valuation->lines()->create([
                    'currency_id' => (int) $currencyId,
                    'qty_close' => round($position['net_units'], 8),
                    'avg_cost_rate_afn' => round($position['average_historical_rate_afn'], 8),
                    'closing_rate_afn' => round($position['closing_rate_afn'], 8),
                    'unrealized_pl_afn' => round($position['revaluation_afn'], 2),
                ]);
            }

            return (int) $valuation->id;
        });
    }

    /**
     * Holiday-safe effective rate resolver.
     * AFN is always 1; branch rates are preferred over global rates.
     */
    public static function effectiveRate(string $date, string $type, int $currencyId, ?int $branchId): float
    {
        $cacheKey = implode('|', [$date, $type, $currencyId, $branchId ?? 'global']);
        if (array_key_exists($cacheKey, self::$effectiveRateCache)) {
            return self::$effectiveRateCache[$cacheKey];
        }

        if (! array_key_exists($currencyId, self::$currencyCodeCache)) {
            self::$currencyCodeCache[$currencyId] = Currency::whereKey($currencyId)->value('code');
        }

        $currencyCode = self::$currencyCodeCache[$currencyId];
        if ($currencyCode === null) {
            throw new \RuntimeException("Currency ID {$currencyId} was not found.");
        }

        if ($currencyCode === config('dab_reports.base_currency', 'AFN')) {
            return self::$effectiveRateCache[$cacheKey] = 1.0;
        }

        $base = FxRate::query()
            ->where('currency_id', $currencyId)
            ->where(function (Builder $query) use ($branchId) {
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                } else {
                    $query->whereNull('branch_id');
                }
            });

        $preferBranch = fn (Builder $query) => $query->orderByRaw('branch_id is null');

        $rate = (clone $base)
            ->whereDate('as_of', $date)
            ->where('type', $type)
            ->tap($preferBranch)
            ->value('rate_afn_per_unit');

        if ($rate === null && $type === 'open') {
            $rate = (clone $base)
                ->where('type', 'close')
                ->whereDate('as_of', '<', $date)
                ->orderByDesc('as_of')
                ->tap($preferBranch)
                ->value('rate_afn_per_unit');
        } elseif ($rate === null) {
            $rate = (clone $base)
                ->whereDate('as_of', $date)
                ->where('type', 'open')
                ->tap($preferBranch)
                ->value('rate_afn_per_unit');

            if ($rate === null) {
                $rate = (clone $base)
                    ->where('type', 'close')
                    ->whereDate('as_of', '<', $date)
                    ->orderByDesc('as_of')
                    ->tap($preferBranch)
                    ->value('rate_afn_per_unit');
            }
        }

        if ($rate === null) {
            $rate = (clone $base)
                ->whereDate('as_of', '<', $date)
                ->orderByDesc('as_of')
                ->tap($preferBranch)
                ->value('rate_afn_per_unit');
        }

        // Legacy fallback remains 1.0; reporting surfaces the missing-rate warning.
        return self::$effectiveRateCache[$cacheKey] = ($rate !== null ? (float) $rate : 1.0);
    }

    private static function storeRate(string $date, string $type, int $currencyId, int $branchId, float $rate): void
    {
        $currency = Currency::findOrFail($currencyId);
        self::$currencyCodeCache[$currencyId] = $currency->code;
        self::$effectiveRateCache = [];
        $storedRate = $currency->code === config('dab_reports.base_currency', 'AFN') ? 1.0 : $rate;

        if ($storedRate <= 0) {
            throw new \InvalidArgumentException('FX rates must be greater than zero.');
        }

        $existing = FxRate::query()->whereDate('as_of', $date)
            ->where('type', $type)->where('currency_id', $currencyId)
            ->where('branch_id', $branchId)->first();
        if ($existing) {
            $existing->update(['rate_afn_per_unit' => $storedRate]);
        } else {
            FxRate::create(['as_of' => $date, 'type' => $type,
                'currency_id' => $currencyId, 'branch_id' => $branchId,
                'rate_afn_per_unit' => $storedRate]);
        }
    }

    /**
     * @return array{total_revaluation_afn: float, currencies: array<int, array<string, float>>}
     */
    private static function monetaryRevaluationAsOf(string $date, int $branchId): array
    {
        $monetaryCodes = config('dab_reports.monetary_account_codes', []);
        $baseCode = config('dab_reports.base_currency', 'AFN');

        $transactions = Transaction::query()
            ->with(['chartOfAccount', 'currency'])
            ->where('branch_id', $branchId)
            ->where('is_approved', true)
            ->whereDate('date', '<=', $date)
            ->whereHas('chartOfAccount', fn (Builder $query) => $query
                ->whereIn('type', ['asset', 'liability'])
                ->whereIn('code', $monetaryCodes))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $positions = [];

        foreach ($transactions as $transaction) {
            $account = $transaction->chartOfAccount;
            $currency = $transaction->currency;
            if (! $account || ! $currency || $currency->code === $baseCode) {
                continue;
            }

            $currencyId = (int) $currency->id;
            $positions[$currencyId] ??= [
                'net_units' => 0.0,
                'net_historical_afn' => 0.0,
            ];

            $normalPositive = $account->type === 'asset'
                ? $transaction->direction === 'debit'
                : $transaction->direction === 'credit';
            $sign = $normalPositive ? 1 : -1;

            // A liability produces the opposite P/L exposure to an asset.
            $exposureSign = $account->type === 'asset' ? $sign : -$sign;
            $positions[$currencyId]['net_units'] += $exposureSign * (float) $transaction->amount;
            $positions[$currencyId]['net_historical_afn'] += $exposureSign * self::historicalAfn($transaction);
        }

        $total = 0.0;
        foreach ($positions as $currencyId => &$position) {
            $closingRate = self::effectiveRate($date, 'close', (int) $currencyId, $branchId);
            $closingValue = $position['net_units'] * $closingRate;
            $revaluation = $closingValue - $position['net_historical_afn'];
            $averageRate = abs($position['net_units']) > 0.00000001
                ? $position['net_historical_afn'] / $position['net_units']
                : 0.0;

            $position['closing_rate_afn'] = $closingRate;
            $position['average_historical_rate_afn'] = $averageRate;
            $position['revaluation_afn'] = $revaluation;
            $total += $revaluation;
        }
        unset($position);

        return [
            'total_revaluation_afn' => round($total, 2),
            'currencies' => $positions,
        ];
    }

    private static function realizedFxForDate(string $date, int $branchId): float
    {
        $transactions = Transaction::query()
            ->with('chartOfAccount')
            ->where('branch_id', $branchId)
            ->where('is_approved', true)
            ->whereDate('date', $date)
            ->whereHas('chartOfAccount', fn (Builder $query) => $query->whereIn('code', ['4005', '5016']))
            ->get();

        $realized = 0.0;
        foreach ($transactions as $transaction) {
            $amount = self::historicalAfn($transaction);
            if ($transaction->chartOfAccount?->code === '4005') {
                $realized += $transaction->direction === 'credit' ? $amount : -$amount;
            } else {
                $realized -= $transaction->direction === 'debit' ? $amount : -$amount;
            }
        }

        return round($realized, 2);
    }

    private static function historicalAfn(Transaction $transaction): float
    {
        if ($transaction->amount_afn !== null) {
            return (float) $transaction->amount_afn;
        }

        if ($transaction->exchange_rate_afn !== null && (float) $transaction->exchange_rate_afn > 0) {
            return round((float) $transaction->amount * (float) $transaction->exchange_rate_afn, 2);
        }

        $date = $transaction->date?->toDateString() ?: (string) $transaction->date;
        return round((float) $transaction->amount * self::effectiveRate(
            $date,
            'close',
            (int) $transaction->currency_id,
            (int) $transaction->branch_id
        ), 2);
    }
}
