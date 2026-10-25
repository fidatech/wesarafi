<?php

namespace Tests\Feature;

use App\Services\InterbranchSettlementAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterbranchSettlementAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_remittance_ledger_has_no_issues(): void
    {
        $this->seed();
        $result = app(InterbranchSettlementAudit::class)->inspect();
        $this->assertSame(0, $result['legs_checked']);
        $this->assertSame(0, $result['issue_count']);
    }
}
