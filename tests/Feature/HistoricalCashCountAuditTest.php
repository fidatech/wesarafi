<?php

namespace Tests\Feature;

use App\Models\BusinessDay;
use App\Services\BusinessDayReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalCashCountAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorded_cash_count_is_compared_against_closing_snapshot(): void
    {
        $this->seed();
        $date = now('Asia/Kabul')->subDays(2)->toDateString();
        $snapshot = BusinessDayReconciliation::report(1, $date);
        $day = BusinessDay::create([
            'branch_id' => 1, 'business_date' => $date, 'status' => 'closed',
            'closed_at' => now(), 'cash_counts' => [1 => 100],
            'closing_snapshot' => ['reconciliation' => $snapshot],
        ]);
        $result = BusinessDayReconciliation::historicalIntegrity(1, $date);
        $this->assertTrue($result['cash_count_recorded']);
        $this->assertFalse($result['cash_count_matches_closing_book']);
        $this->assertFalse($result['consistent']);
    }

    public function test_missing_physical_count_is_not_reported_as_verified(): void
    {
        $this->seed();
        $date = now('Asia/Kabul')->subDays(2)->toDateString();
        BusinessDay::create([
            'branch_id' => 1, 'business_date' => $date, 'status' => 'closed',
            'closed_at' => now(), 'cash_counts' => [],
            'closing_snapshot' => ['reconciliation' => BusinessDayReconciliation::report(1, $date)],
        ]);
        $result = BusinessDayReconciliation::historicalIntegrity(1, $date);
        $this->assertFalse($result['cash_count_recorded']);
        $this->assertNull($result['cash_count_matches_closing_book']);
    }
}
