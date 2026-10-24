<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MonitoringAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::first())->withoutMiddleware();
    }

    private function entries(int $amount = 500, ?int $branchId = null): array
    {
        $header = ['branch_id' => $branchId ?? auth()->user()->branch_id,
            'currency_id' => Currency::where('code', 'AFN')->value('id'), 'amount' => $amount,
            'date' => now()->subMonths(2)->toDateString(), 'exchange_rate_afn' => 1,
            'module_name' => 'audit_test', 'module_id' => 1, 'created_by' => auth()->id()];

        return [
            $header + ['chart_of_account_id' => ChartOfAccount::where('code', '1000')->value('id'), 'direction' => 'debit'],
            $header + ['chart_of_account_id' => ChartOfAccount::where('code', '3003')->value('id'), 'direction' => 'credit'],
        ];
    }

    public function test_direct_edit_records_old_new_and_actor_without_logging_noops(): void
    {
        $rows = TransactionService::postMany($this->entries());
        $rows[0]->update(['description' => 'Corrected description']);
        $change = Activity::where('log_name', 'transaction_changes')->sole();
        $this->assertNull($change->properties['before'][0]['description']);
        $this->assertSame('Corrected description', $change->properties['after'][0]['description']);
        $this->assertSame(auth()->id(), $change->causer_id);
        $rows[0]->save();
        $this->assertSame(1, Activity::where('log_name', 'transaction_changes')->count());
        $this->get('/monitoring/modified-transactions')->assertOk()->assertSee('Corrected description')->assertSee('Before')->assertSee('After');
        $this->get('/monitoring/modified-transactions?from=2000-01-01&to=2000-01-02')->assertOk()->assertDontSee('Corrected description');
    }

    public function test_repost_is_audited_and_deletions_use_deletion_date(): void
    {
        TransactionService::postMany($this->entries());
        TransactionService::repost('audit_test', 1, fn () => $this->entries(750));
        $change = Activity::where('log_name', 'transaction_changes')->sole();
        $this->assertCount(2, $change->properties['before']);
        $this->assertCount(2, $change->properties['after']);
        $this->assertEquals(500, $change->properties['before'][0]['amount']);
        $this->assertEquals(750, $change->properties['after'][0]['amount']);
        $this->assertSame(2, Transaction::onlyTrashed()->count());
        $deleted = $this->get('/monitoring/deleted-transactions')->assertOk();
        $this->assertSame(2, $deleted->viewData('transactions')->total());
        $this->get('/financial-reports/deleted-transactions?from=2026-01-01')->assertRedirect(route('monitoring.deleted-transactions', ['from' => '2026-01-01']));
        try {
            TransactionService::repost('audit_test', 1, fn () => [$this->entries(900)[0]]);
            $this->fail('An unbalanced replacement should fail.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(1, Activity::where('log_name', 'transaction_changes')->count());
            $this->assertSame(2, Transaction::count());
        }
    }

    public function test_audit_is_scoped_to_user_branch_even_when_filter_is_tampered(): void
    {
        $other = Branch::whereKeyNot(auth()->user()->branch_id)->firstOrFail();
        $rows = TransactionService::postMany($this->entries(100, $other->id));
        $rows[0]->update(['description' => 'Other branch confidential change']);
        $this->get('/monitoring/modified-transactions?branch_id='.$other->id)->assertOk()->assertDontSee('Other branch confidential change');
        TransactionService::delete('audit_test', 1);
        $response = $this->get('/monitoring/deleted-transactions?branch_id='.$other->id)->assertOk();
        $this->assertSame(0, $response->viewData('transactions')->total());
    }
}
