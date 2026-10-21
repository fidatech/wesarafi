<?php

// app/Services/FxValuationService.php
namespace App\Services;

use App\Models\{FxRate, FxDailyValuation, FxDailyValuationLine, Currency, Transaction};
use Illuminate\Support\Facades\DB;

class FxValuationService
{
    // Base currency (AFN) always 1.00
    public static function afnId(): int {
        return (int) Currency::where('code','AFN')->value('id');
    }

    /**
     * Get effective rate for a date/type with holiday fallback.
     * Strategy:
     * - exact (as_of,type)
     * - if type=open: previous CLOSE before date
     * - if type=close: OPEN of same date, else previous CLOSE
     * - fallback: last known rate (any type) before date
     * - AFN => 1
     */
    public static function effectiveRate(string $date, string $type, int $currencyId, ?int $branchId = null): float
    {
        return DailyValuationService::effectiveRate($date, $type, $currencyId, $branchId);
    }

    /**
     * Build (or rebuild) the daily valuation snapshot for a day/branch.
     * Assumptions:
     * - Your FX cash sits in COA=1000 with different currency_ids (AFN, USD, EUR).
     * - Realized P/L (optional) can be read from your auction lines (if stored) OR computed as AFN in - AFN out for auction module today.
     */
    public static function snapshot(string $date, int $branchId, array $closingRatesByCurrencyId, ?callable $realizedFetcher = null): int
    {
        return DB::transaction(function () use ($date,$branchId,$closingRatesByCurrencyId,$realizedFetcher) {
            $valuation = FxDailyValuation::firstOrCreate(['as_of'=>$date,'branch_id'=>$branchId]);
            $valuation->lines()->delete();

            // 1) compute end-of-day positions by currency in COA=1000 (debits - credits in currency units)
            $cashCoaId = (int) DB::table('chart_of_accounts')->where('code','1000')->value('id');

            $positions = Transaction::query()
                ->select('currency_id',
                    DB::raw("SUM(CASE WHEN direction='debit'  THEN amount ELSE 0 END) -
                             SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END) AS qty"))
                ->where('chart_of_account_id',$cashCoaId)
                ->where('branch_id',$branchId)
                ->whereDate('date','<=',$date)
                ->groupBy('currency_id')
                ->get()
                ->keyBy('currency_id');

            // 2) realized today
            $realized = 0.0;
            if ($realizedFetcher) {
                $realized = (float) $realizedFetcher($date, $branchId);
            } else {
                // fallback: realized from AFN cash flows in auction module today
                $realized = (float) Transaction::query()
                    ->where('chart_of_account_id',$cashCoaId)
                    ->where('branch_id',$branchId)
                    ->whereDate('date',$date)
                    ->where('module_name','auction')
                    ->where('currency_id', self::afnId())
                    ->select(DB::raw("SUM(CASE WHEN direction='debit' THEN amount ELSE -amount END) AS net_afn"))
                    ->value('net_afn');
            }

            // 3) unrealized = sum over currencies [ qty_close * (closing_rate - opening_avg_cost?) ]
            // If you maintain moving average cost, plug it here; otherwise treat opening value as qty_open * opening_rate and difference will surface via the plug.
            $unrealized = 0.0;

            foreach ($positions as $curId => $row) {
                $qty = (float) $row->qty;
                if ($qty == 0) continue;
                if ($curId == self::afnId()) continue;

                $closeRate = $closingRatesByCurrencyId[$curId]
                    ?? self::effectiveRate($date, 'close', $curId, $branchId);

                FxDailyValuationLine::create([
                    'valuation_id'       => $valuation->id,
                    'currency_id'        => (int)$curId,
                    'qty_close'          => $qty,
                    'avg_cost_rate_afn'  => 0,            // optional: fill if you track MAC
                    'closing_rate_afn'   => $closeRate,
                    'unrealized_pl_afn'  => 0,            // optional: fill if MAC available
                ]);

                // If you do track MAC, compute: $unrealized += $qty * ($closeRate - $avgCost)
            }

            $valuation->realized_pl_afn   = round($realized, 2);
            $valuation->unrealized_pl_afn = round($unrealized, 2);
            $valuation->temp_pnl_afn      = round($realized + $unrealized, 2);
            $valuation->save();

            return $valuation->id;
        });
    }
}
