<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OperationalLedgerService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TransactionPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Currency $afn;
    private ChartOfAccount $cash;
    private ChartOfAccount $capital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'type' => 'independent',
        ]);

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Branch', 'guard_name' => 'web']));
        $this->actingAs($this->user);

        $this->afn = Currency::create([
            'name' => 'Afghani',
            'code' => 'AFN',
            'symbol' => '؋',
            'exchange_rate' => 1,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->cash = ChartOfAccount::create([
            'code' => '1000',
            'category' => 'Assets',
            'subcategory' => 'Current',
            'account_name' => 'Cash on Hand',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->capital = ChartOfAccount::create([
            'code' => '3000',
            'category' => 'Equity',
            'subcategory' => 'Capital',
            'account_name' => 'Share Capital',
            'type' => 'equity',
            'is_active' => true,
        ]);
    }

    public function test_balanced_group_posts_atomically_under_one_linked_id(): void
    {
        $rows = TransactionService::postMany([
            [
                'branch_id' => $this->branch->id,
                'chart_of_account_id' => $this->cash->id,
                'currency_id' => $this->afn->id,
                'amount' => 30_000_000,
                'direction' => 'debit',
                'module_name' => 'capital_test',
                'module_id' => 1,
            ],
            [
                'branch_id' => $this->branch->id,
                'chart_of_account_id' => $this->capital->id,
                'currency_id' => $this->afn->id,
                'amount' => 30_000_000,
                'direction' => 'credit',
                'module_name' => 'capital_test',
                'module_id' => 1,
            ],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame($rows[0]->linked_id, $rows[1]->linked_id);
        $this->assertSame('approved', $rows[0]->approval_status);
        $this->assertTrue($rows[0]->is_approved);
        $this->assertEquals(30_000_000, Transaction::where('direction', 'debit')->sum('amount_afn'));
        $this->assertEquals(30_000_000, Transaction::where('direction', 'credit')->sum('amount_afn'));
    }

    public function test_unbalanced_group_is_rejected_without_partial_rows(): void
    {
        try {
            TransactionService::postMany([
                [
                    'branch_id' => $this->branch->id,
                    'chart_of_account_id' => $this->cash->id,
                    'currency_id' => $this->afn->id,
                    'amount' => 1000,
                    'direction' => 'debit',
                ],
                [
                    'branch_id' => $this->branch->id,
                    'chart_of_account_id' => $this->capital->id,
                    'currency_id' => $this->afn->id,
                    'amount' => 999,
                    'direction' => 'credit',
                ],
            ]);

            $this->fail('Expected an unbalanced journal exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unbalanced journal group', $exception->getMessage());
        }

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_legacy_operational_ledger_uses_same_balanced_posting_engine(): void
    {
        $ledger = app(OperationalLedgerService::class);
        $linkedId = $ledger->post([
            'date' => now()->toDateString(),
            'branch_id' => $this->branch->id,
            'currency_id' => $this->afn->id,
            'exchange_rate_afn' => 1,
            'module_name' => 'legacy_test',
            'module_id' => 9,
            'created_by' => $this->user->id,
        ], [
            [
                'chart_of_account_id' => $this->cash->id,
                'amount' => 500,
                'amount_afn' => 500,
                'direction' => 'debit',
                'is_safe' => true,
            ],
            [
                'chart_of_account_id' => $this->capital->id,
                'amount' => 500,
                'amount_afn' => 500,
                'direction' => 'credit',
            ],
        ]);

        $rows = Transaction::where('linked_id', $linkedId)->get();
        $this->assertCount(2, $rows);
        $this->assertEquals(500, $rows->where('direction', 'debit')->sum('amount_afn'));
        $this->assertEquals(500, $rows->where('direction', 'credit')->sum('amount_afn'));
    }

    public function test_customer_deposit_and_withdrawal_drive_treasury_and_linked_account_filter(): void
    {
        $this->withoutMiddleware();
        ChartOfAccount::create([
            'code' => '1002', 'category' => 'Assets', 'subcategory' => 'Current',
            'account_name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true,
        ]);
        $customer = \App\Models\Account::create([
            'account_code' => 'C-1', 'full_name' => 'Treasury Test Customer',
            'type' => 'customer', 'branch_id' => $this->branch->id,
        ]);
        foreach (['credit' => 1000, 'debit' => 250] as $direction => $amount) {
            $this->post('/journal/transactions/account-ledgers', [
                'account_id' => $customer->id, 'currency_id' => $this->afn->id,
                'date' => now()->toDateString(), 'amount' => $amount,
                'exchange_rate_afn' => 1, 'direction' => $direction,
            ])->assertRedirect();
            $accountEntry = Transaction::where('account_id', $customer->id)->where('direction', $direction)->firstOrFail();
            $safeEntry = Transaction::where('linked_id', $accountEntry->linked_id)->where('is_safe', true)->firstOrFail();
            $this->assertSame($direction === 'credit' ? 'debit' : 'credit', $safeEntry->direction);
            $this->assertSame('Treasury Test Customer', $safeEntry->journalLinkedAccounts());
        }
        $response = $this->getJson('/journal/transactions/data?account_id='.$customer->id);
        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(['Treasury Test Customer'], array_values(array_unique(array_column($response->json('data'), 'linked_account'))));
        $this->get('/journal/transactions/summary?account_id='.$customer->id)
            ->assertOk()->assertSee('750.00')->assertSee('Money in:')->assertSee('Money out:');
        $this->getJson('/journal/transactions/data?entry_scope=all')->assertOk()->assertJsonCount(4, 'data');
        $html = view('journal.transactions.export', [
            'transactions' => Transaction::with(['account', 'branch', 'currency', 'chartOfAccount', 'transactionType', 'linkedEntries.account'])->where('is_safe', true)->get(),
            'request' => request(),
        ])->render();
        $this->assertStringContainsString('Linked Account', $html);
        $this->assertStringContainsString('Treasury Test Customer', $html);
    }
}
