<?php

namespace Tests\Feature\Security;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalReversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function journal(int $branchId): string
    {
        $common = ['branch_id' => $branchId, 'currency_id' => Currency::where('code', 'AFN')->value('id'),
            'amount' => 100, 'exchange_rate_afn' => 1, 'module_name' => 'account_ledger'];
        $entries = TransactionService::postMany([
            $common + ['chart_of_account_id' => ChartOfAccount::where('code', '1000')->value('id'), 'direction' => 'debit'],
            $common + ['chart_of_account_id' => ChartOfAccount::where('code', '1002')->value('id'), 'direction' => 'credit'],
        ]);
        return $entries[0]->linked_id;
    }

    public function test_authorized_reversal_preserves_original_and_prevents_second_reversal(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $this->actingAs($user);
        $linked = $this->journal($branch->id);
        $url = '/journal/transactions/'.$linked.'/reverse';
        $this->post($url, ['reason' => 'Correction of wrong entry'])->assertRedirect();
        $this->assertSame(2, Transaction::where('linked_id', $linked)->count());
        $reversal = \Illuminate\Support\Facades\DB::table('journal_reversals')->where('original_linked_id', $linked)->first();
        $this->assertNotNull($reversal);
        $offsets = Transaction::where('linked_id', $reversal->reversal_linked_id)->get();
        $this->assertCount(2, $offsets);
        $originals = Transaction::where('linked_id', $linked)->get();
        foreach ($originals as $original) {
            $offset = $offsets->first(fn ($entry) => $entry->chart_of_account_id === $original->chart_of_account_id);
            $this->assertNotNull($offset);
            $this->assertSame($original->direction === 'debit' ? 'credit' : 'debit', $offset->direction);
            $this->assertEqualsWithDelta($original->amount_afn, $offset->amount_afn, 0.001);
        }
        $this->assertEqualsWithDelta(0, $originals->sum(fn ($entry) => $entry->direction === 'debit' ? $entry->amount_afn : -$entry->amount_afn)
            + $offsets->sum(fn ($entry) => $entry->direction === 'debit' ? $entry->amount_afn : -$entry->amount_afn), 0.001);
        $this->post($url, ['reason' => 'Second reversal'])->assertStatus(422);
        $this->assertSame(4, Transaction::count());
    }

    public function test_branch_admin_cannot_reverse_other_branch_journal(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $this->actingAs($user);
        $linked = $this->journal($other->id);
        $this->post('/journal/transactions/'.$linked.'/reverse', ['reason' => 'Wrong branch reversal'])->assertForbidden();
        $this->assertDatabaseMissing('journal_reversals', ['original_linked_id' => $linked]);
        $this->assertSame(2, Transaction::count());
    }

    public function test_unprivileged_user_cannot_reverse_a_journal(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $this->actingAs($user);
        $linked = $this->journal($branch->id);
        $this->post('/journal/transactions/'.$linked.'/reverse', ['reason' => 'Not authorized'])->assertForbidden();
        $this->assertSame(2, Transaction::count());
    }
}
