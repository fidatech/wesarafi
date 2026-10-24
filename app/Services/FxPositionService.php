<?php

// app/Services/FxPositionService.php
namespace App\Services;

use App\Models\FxPosition;

class FxPositionService
{
    public static function buy(int $branchId, int $currencyId, float $qtyGet, float $rateAfnPerUnit): void
    {
        // qtyGet in FX (e.g., 10,000 USD)
        $pos = FxPosition::firstOrCreate(['branch_id'=>$branchId,'currency_id'=>$currencyId]);
        $oldQty  = (float) $pos->qty;
        $oldRate = (float) $pos->avg_cost_rate_afn;

        $newQty  = $oldQty + $qtyGet;
        if ($newQty <= 0) { // guard
            $pos->qty = 0; $pos->avg_cost_rate_afn = 0; $pos->save(); return;
        }

        // Weighted average
        $newRate = (($oldQty * $oldRate) + ($qtyGet * $rateAfnPerUnit)) / $newQty;

        $pos->qty = $newQty;
        $pos->avg_cost_rate_afn = $newRate;
        $pos->save();
    }

    public static function sell(int $branchId, int $currencyId, float $qtySell): array
    {
        $pos = FxPosition::firstOrCreate(['branch_id'=>$branchId,'currency_id'=>$currencyId]);
        $avg = (float) $pos->avg_cost_rate_afn;
        $qty = (float) $pos->qty;

        if ($qtySell > $qty + 1e-8) {
            // If you never carry to next day, this shouldn’t happen; otherwise allow negative or block.
            throw new \RuntimeException("Selling more than position. Qty available: {$qty}");
        }

        $costAfn = $qtySell * $avg; // cost basis in AFN
        $pos->qty = max(0, $qty - $qtySell);
        // avg rate stays the same on sell
        $pos->save();

        return ['avg_cost_rate_afn' => $avg, 'cost_afn' => $costAfn];
    }
}
