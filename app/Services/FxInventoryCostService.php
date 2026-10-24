<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class FxInventoryCostService
{
    /**
     * Return the moving-average carrying position for cash held in one currency.
     * Debits add units/cost; credits remove units/cost using the historical AFN
     * values stored on each journal line.
     *
     * @return array{units: float, carrying_afn: float, average_rate_afn: float}
     */
    public static function position(
        int $branchId,
        int $currencyId,
        string $asOfDate,
        ?string $excludeModule = null,
        ?int $excludeModuleId = null
    ): array {
        $transactions = Transaction::query()
            ->where('branch_id', $branchId)
            ->where('currency_id', $currencyId)
            ->where('is_approved', true)
            ->whereDate('date', '<=', $asOfDate)
            ->whereHas('chartOfAccount', fn (Builder $query) => $query->where('code', '1000'))
            ->when(
                $excludeModule && $excludeModuleId,
                fn (Builder $query) => $query->where(function (Builder $nested) use ($excludeModule, $excludeModuleId) {
                    $nested->where('module_name', '!=', $excludeModule)
                        ->orWhere('module_id', '!=', $excludeModuleId)
                        ->orWhereNull('module_name')
                        ->orWhereNull('module_id');
                })
            )
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $units = 0.0;
        $carryingAfn = 0.0;

        foreach ($transactions as $transaction) {
            $amountAfn = self::historicalAfn($transaction);
            $sign = $transaction->direction === 'debit' ? 1 : -1;
            $units += $sign * (float) $transaction->amount;
            $carryingAfn += $sign * $amountAfn;
        }

        if (abs($units) < 0.00005) {
            $units = 0.0;
        }
        if (abs($carryingAfn) < 0.005) {
            $carryingAfn = 0.0;
        }

        return [
            'units' => round($units, 4),
            'carrying_afn' => round($carryingAfn, 2),
            'average_rate_afn' => $units > 0 ? round($carryingAfn / $units, 8) : 0.0,
        ];
    }

    private static function historicalAfn(Transaction $transaction): float
    {
        if ($transaction->amount_afn !== null) {
            return (float) $transaction->amount_afn;
        }

        if ($transaction->exchange_rate_afn !== null && (float) $transaction->exchange_rate_afn > 0) {
            return round((float) $transaction->amount * (float) $transaction->exchange_rate_afn, 2);
        }

        $currency = Currency::find($transaction->currency_id);
        if (! $currency) {
            throw new \RuntimeException("Currency ID {$transaction->currency_id} was not found while calculating FX inventory cost.");
        }

        $date = $transaction->date?->toDateString() ?: (string) $transaction->date;
        $rate = $currency->code === config('dab_reports.base_currency', 'AFN')
            ? 1.0
            : DailyValuationService::effectiveRate($date, 'close', (int) $currency->id, (int) $transaction->branch_id);

        return round((float) $transaction->amount * $rate, 2);
    }
}
