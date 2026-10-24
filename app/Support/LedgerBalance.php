<?php

namespace App\Support;

final class LedgerBalance
{
    /** Display a debit-minus-credit balance without treating credits as losses. */
    public static function format(float|int|string|null $balance): string
    {
        $balance = round((float) $balance, 2);
        $side = $balance < 0 ? ' Cr' : ($balance > 0 ? ' Dr' : '');

        return number_format(abs($balance), 2).$side;
    }
}
