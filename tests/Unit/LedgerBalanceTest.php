<?php

namespace Tests\Unit;

use App\Support\LedgerBalance;
use PHPUnit\Framework\TestCase;

class LedgerBalanceTest extends TestCase
{
    public function test_credit_balances_are_positive_and_identified_as_credit(): void
    {
        $this->assertSame('1,000.00 Cr', LedgerBalance::format(-1000));
        $this->assertSame('250.50 Cr', LedgerBalance::format('-250.50'));
    }

    public function test_debit_and_zero_balances_remain_distinguishable(): void
    {
        $this->assertSame('1,000.00 Dr', LedgerBalance::format(1000));
        $this->assertSame('0.00', LedgerBalance::format(0));
        $this->assertSame('0.00', LedgerBalance::format(-0.001));
        $this->assertSame('0.00', LedgerBalance::format(null));
    }
}
