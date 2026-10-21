<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchCapitalTransfer;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DabStatementService;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCapitalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::first())->withoutMiddleware();
    }

    private function payload(): array
    {
        return ['source_branch_id' => auth()->user()->branch_id, 'destination_branch_id' => Branch::where('id', '!=', auth()->user()->branch_id)->firstOrFail()->id,
            'currency_id' => Currency::where('code', 'AFN')->firstOrFail()->id, 'amount' => 100000, 'date' => '2026-09-14', 'description' => 'Branch funding test'];
    }

    private function statement(int $branch, string $date = '2026-09-14'): array
    {
        return app(DabStatementService::class)->balanceSheet(app(FinancialReportService::class)->balanceSheet($date, $branch));
    }

    public function test_advance_posts_balanced_branch_ledgers_and_receivable_then_repayment_reduces_debt(): void
    {
        $p = $this->payload();
        $this->post('/accounting/branch-capital', $p)->assertRedirect();
        $advance = BranchCapitalTransfer::firstOrFail();
        $rows = Transaction::where('module_name', 'branch_capital')->with('chartOfAccount')->get();
        $this->assertCount(4, $rows);
        $this->assertCount(1, $rows->pluck('linked_id')->unique());
        foreach ([$p['source_branch_id'], $p['destination_branch_id']] as $branch) {
            $group = $rows->where('branch_id', $branch);
            $this->assertEquals($group->where('direction', 'debit')->sum('amount_afn'), $group->where('direction', 'credit')->sum('amount_afn'));
            $safe = $group->where('is_safe', true)->first();
            $this->assertEquals(Branch::find($branch)->treasuryAccount->id, $safe->account_id);
            $this->assertEquals($branch, $group->where('is_safe', false)->first()->account->branch_id);
        }
        $source = $this->statement($p['source_branch_id']);
        $destination = $this->statement($p['destination_branch_id']);
        $this->assertEquals(100000, $source['current_assets']['receivables']);
        $url = '/financial-reports/balance-sheet?date=2026-09-14&branch_id='.$p['source_branch_id'];
        $this->get($url)->assertOk()->assertSee('حسابات قابل حصول');
        $pdf = $this->get($url.'&format=pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $xlsx = $this->get($url.'&format=xlsx')->assertOk();
        $file = $xlsx->baseResponse->getFile()->getPathname();
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        $this->assertEquals('Receivables', $sheet->getCell('B4')->getValue());
        $this->assertEquals(100000, $sheet->getCell('C4')->getValue());
        unlink($file);
        $this->get('/accounting/capital')->assertOk()->assertDontSee('— Interbranch');

        $this->assertEquals(0, $source['current_assets']['other_current']);
        $this->assertEquals(-100000, $source['current_assets']['cash']);
        $this->assertEquals(100000, $destination['current_assets']['cash']);
        $this->assertEquals(100000, $destination['liabilities']['accounts_payable']);
        $this->assertEquals(0, $source['difference']);
        $this->assertEquals(0, $destination['difference']);
        $this->assertEquals(0, $this->statement($p['source_branch_id'], '2026-09-13')['current_assets']['receivables']);
        $this->post('/accounting/branch-capital/'.$advance->id.'/repay', ['amount' => 25000, 'date' => '2026-09-14'])->assertRedirect();
        $this->assertEquals(75000, $advance->fresh()->outstanding);
        $this->assertEquals(75000, $this->statement($p['source_branch_id'])['current_assets']['receivables']);
        $this->assertEquals(75000, $this->statement($p['destination_branch_id'])['liabilities']['accounts_payable']);
        $this->postJson('/accounting/branch-capital/'.$advance->id.'/repay', ['amount' => 75001, 'date' => '2026-09-14'])->assertUnprocessable();
        $this->postJson('/accounting/branch-capital/'.$advance->id.'/repay', ['amount' => 1, 'date' => '2026-09-13'])->assertUnprocessable();
        $this->assertDatabaseCount('branch_capital_transfers', 2);
        $this->assertEquals(8, Transaction::where('module_name', 'branch_capital')->count());
        $this->get('/accounting/branch-capital')->assertOk()->assertSee('75,000.00');
        $this->get('/accounting/branch-capital/create')->assertOk()->assertSee('Receiving branch');
        $this->get('/accounting/branch-capital/'.$advance->id)->assertOk()->assertSee('Repayment history');
        $this->get('/accounting/branch-capital?status=repaid')->assertOk()->assertDontSee('100,000.00');
        $this->post('/accounting/branch-capital/'.$advance->id.'/repay', ['amount' => 75000, 'date' => '2026-09-14'])->assertRedirect();
        $this->assertEquals(0, $advance->fresh()->outstanding);
        $this->get('/accounting/branch-capital?status=repaid')->assertOk()->assertSee('100,000.00');
    }

    public function test_validation_scope_foreign_currency_and_atomic_failure(): void
    {
        $p = $this->payload();
        $this->postJson('/accounting/branch-capital', array_replace($p, ['destination_branch_id' => $p['source_branch_id']]))->assertUnprocessable();
        $this->postJson('/accounting/branch-capital', array_replace($p, ['source_branch_id' => $p['destination_branch_id'], 'destination_branch_id' => $p['source_branch_id']]))->assertUnprocessable();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $this->postJson('/accounting/branch-capital', array_replace($p, ['currency_id' => $usd->id]))->assertUnprocessable();
        $this->post('/accounting/branch-capital', array_replace($p, ['currency_id' => $usd->id, 'amount' => 1000, 'exchange_rate_afn' => 70]))->assertRedirect();
        $this->assertEquals(70000, Transaction::where('module_name', 'branch_capital')->first()->amount_afn);
        $advance = BranchCapitalTransfer::firstOrFail();
        $third = Branch::create(['name' => 'Unrelated Branch', 'type' => 'branch']);
        auth()->user()->update(['branch_id' => $third->id]);
        $this->get('/accounting/branch-capital/'.$advance->id)->assertNotFound();
        $this->postJson('/accounting/branch-capital/'.$advance->id.'/repay', ['amount' => 10, 'date' => '2026-09-14', 'exchange_rate_afn' => 70])->assertNotFound();
        $this->get('/accounting/branch-capital')->assertOk()->assertDontSee('1,000.00');
        auth()->user()->update(['branch_id' => $p['source_branch_id']]);
        \App\Models\ChartOfAccount::where('code', '1006')->update(['type' => 'expense']);
        $this->postJson('/accounting/branch-capital', $p)->assertNotFound();
        $this->assertDatabaseCount('branch_capital_transfers', 1);
        $this->assertEquals(4, Transaction::where('module_name', 'branch_capital')->count());
    }
}
