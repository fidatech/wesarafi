<?php

namespace Tests\Feature;

use App\Models\{Account, AccountSubCategory, Branch, ChartOfAccount, Currency, FxRate, Transaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Currency $afn;
    private Currency $usd;
    private ChartOfAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware();
        $this->branch = Branch::first();
        $this->actingAs(User::first());
        $this->afn = Currency::where('code', 'AFN')->firstOrFail();
        $this->usd = Currency::where('code', 'USD')->firstOrFail();
        $this->cash = ChartOfAccount::where('code', '1000')->firstOrFail();
    }

    private function customer(string $code, bool $active = true, ?int $branch = null): Account
    {
        return Account::create(['account_code' => $code, 'full_name' => 'Filter '.$code, 'type' => 'customer',
            'branch_id' => $branch ?? $this->branch->id, 'is_active' => $active,
            'account_sub_category_id' => AccountSubCategory::where('name_en', 'Regular Customer')->value('id')]);
    }

    private function entry(Account $account, Currency $currency, string $direction, float $amount, string $date = '2026-09-13', bool $approved = true, bool $safe = false): Transaction
    {
        return Transaction::create(['account_id' => $account->id, 'branch_id' => $account->branch_id,
            'currency_id' => $currency->id, 'direction' => $direction, 'amount' => $amount,
            'chart_of_account_id' => $safe ? $this->cash->id : ChartOfAccount::where('code', '1002')->value('id'),
            'amount_afn' => $amount * ($currency->code === 'USD' ? 70 : 1), 'exchange_rate_afn' => $currency->code === 'USD' ? 70 : 1,
            'date' => $date, 'created_by' => auth()->id(), 'is_approved' => $approved, 'is_safe' => $safe]);
    }

    public function test_customer_filters_balances_and_exports_share_scope(): void
    {
        $credit = $this->customer('A');
        $debit = $this->customer('B', false);
        $zero = $this->customer('C');
        $other = $this->customer('OTHER', true, Branch::whereKeyNot($this->branch->id)->value('id'));
        $this->entry($credit, $this->afn, 'credit', 1000);
        $this->entry($credit, $this->usd, 'debit', 20);
        $this->entry($debit, $this->afn, 'debit', 500);
        $this->entry($zero, $this->afn, 'credit', 900, approved: false);
        $this->entry($other, $this->afn, 'credit', 999999);
        $this->entry($zero, $this->afn, 'credit', 800)->delete();
        $base = '/accounts/all-customers/data?q=Filter';
        $all = $this->getJson($base)->assertOk()->assertJsonCount(3, 'data');
        $this->assertStringContainsString('AFN <strong>1,000.00</strong>', $all->json('data.0.balance'));
        $this->assertStringContainsString('USD <strong>-20.00</strong>', $all->json('data.0.balance'));
        foreach (['credit' => 1, 'debit' => 2, 'nonzero' => 2, 'zero' => 1] as $balance => $count) {
            $this->getJson($base.'&balance='.$balance)->assertOk()->assertJsonCount($count, 'data');
        }
        $this->getJson($base.'&status=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $debit->id);
        $this->getJson($base.'&balance=debit&currency_id='.$this->afn->id)->assertJsonCount(1, 'data');
        $this->getJson($base.'&customer_type='.AccountSubCategory::where('name_en', 'Corporate Customer')->value('id'))->assertJsonCount(0, 'data');
        $this->getJson($base.'&branch_id='.$other->branch_id)->assertJsonCount(3, 'data');
        $this->app->forgetInstance('datatables.request');
        $this->getJson($base.'&draw=1&start=1&length=1')->assertJsonCount(1, 'data')->assertJsonPath('recordsFiltered', 3);
        $xlsx = $this->get('/accounts/all-customers/export?q=Filter&format=xlsx&length=1')->assertOk();
        $book = IOFactory::load($xlsx->baseResponse->getFile()->getPathname());
        $this->assertSame(4, $book->getActiveSheet()->getHighestRow());
        $this->assertStringContainsString('AFN 1,000.00 Cr', $book->getActiveSheet()->getCell('F2')->getValue());
        unlink($xlsx->baseResponse->getFile()->getPathname());
        $pdf = $this->get('/accounts/all-customers/export?q=Filter&balance=credit&format=pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        if (!is_dir(base_path('tmp/pdfs'))) mkdir(base_path('tmp/pdfs'), 0777, true);
        file_put_contents(base_path('tmp/pdfs/all-customers-test.pdf'), $pdf->getContent());
    }

    public function test_dashboard_cash_is_per_currency_branch_and_cutoff(): void
    {
        $customer = $this->customer('CASH');
        $other = $this->customer('FOREIGN', true, Branch::whereKeyNot($this->branch->id)->value('id'));
        $this->entry($customer, $this->afn, 'debit', 1000, safe: true);
        $this->entry($customer, $this->afn, 'credit', 250, safe: true);
        $this->entry($customer, $this->afn, 'credit', 1000); // Linked customer side is not cash.
        $this->entry($customer, $this->usd, 'debit', 20, safe: true);
        $this->entry($customer, $this->afn, 'debit', 5000, '2026-09-14', safe: true);
        $this->entry($customer, $this->afn, 'debit', 6000, approved: false, safe: true);
        $this->entry($other, $this->afn, 'debit', 999999, safe: true);
        FxRate::create(['currency_id' => $this->usd->id, 'branch_id' => $this->branch->id, 'as_of' => '2026-09-13', 'type' => 'close', 'rate_afn_per_unit' => 70]);
        $dashboard = $this->get('/dashboard?date=2026-09-13&branch_id='.$other->branch_id)->assertOk();
        $cash = $dashboard->viewData('cashBalances')->keyBy('code');
        $this->assertEquals(750, $cash['AFN']['balance']);
        $this->assertEquals(20, $cash['USD']['balance']);
        $this->assertEquals(2150, $dashboard->viewData('currentCashAfn'));
        $this->assertEquals(3, $dashboard->viewData('summary')['today_transactions']);
        $this->assertSame($this->branch->id, $dashboard->viewData('branchId'));
        $filtered = $this->get('/dashboard?date=2026-09-13&currency_id='.$this->usd->id)->assertOk();
        $this->assertCount(1, $filtered->viewData('cashBalances'));
        $this->assertEquals(1400, $filtered->viewData('currentCashAfn'));
        $early = $this->get('/dashboard?date=2026-09-12')->assertOk();
        $this->assertCount(2, $early->viewData('cashBalances'));
        $this->assertEquals(0, $early->viewData('currentCashAfn'));
    }

    public function test_foreign_cash_without_a_dated_rate_does_not_invent_an_afn_total(): void
    {
        $customer = $this->customer('NO-RATE');
        $this->entry($customer, $this->usd, 'debit', 20, safe: true);
        $dashboard = $this->get('/dashboard?date=2026-09-13')->assertOk();
        $this->assertEquals(20, $dashboard->viewData('cashBalances')->keyBy('code')['USD']['balance']);
        $this->assertNull($dashboard->viewData('currentCashAfn'));
        $dashboard->assertSee('Set missing closing rates');
    }

    public function test_each_account_listing_shows_all_currency_balances(): void
    {
        foreach ([
            'regular-customers' => ['Regular Customer', 'customer'],
            'corporate-customers' => ['Corporate Customer', 'customer'],
            'temporary-customers' => ['Temporary Customer', 'customer'],
            'street-vendors' => ['Street Vendor', 'customer'],
            'banks' => ['Domestic Bank', 'bank'],
            'branches-accounts' => ['Branch Account', 'branch'],
        ] as $page => [$category, $type]) {
            $account = Account::create(['account_code' => 'BAL-'.$page, 'full_name' => 'Balance '.$page,
                'type' => $type, 'branch_id' => $this->branch->id,
                'account_sub_category_id' => AccountSubCategory::where('name_en', $category)->value('id')]);
            $this->entry($account, $this->afn, 'credit', 1000);
            $this->entry($account, $this->usd, 'debit', 20);
            $this->entry($account, $this->afn, 'credit', 900, approved: false);
            $this->entry($account, $this->usd, 'debit', 500)->delete();
            $this->app->forgetInstance('datatables.request');
            $response = $this->getJson('/accounts/'.$page.'/data')->assertOk();
            $row = collect($response->json('data'))->firstWhere('id', $account->id);
            $this->assertNotNull($row, $page);
            $this->assertStringContainsString($type === 'bank' ? 'text-danger">AFN <strong>-1,000.00</strong>' : 'text-success">AFN <strong>1,000.00</strong>', $row['balance'], $page);
            $this->assertStringContainsString($type === 'bank' ? 'text-success">USD <strong>20.00</strong>' : 'text-danger">USD <strong>-20.00</strong>', $row['balance'], $page);
            $this->assertSame(2, substr_count($row['balance'], '<div '));
            $this->assertSame([$this->branch->id], collect($response->json('data'))->pluck('branch_id')->unique()->values()->all());
        }
        $this->app->forgetInstance('datatables.request');
        $zero = $this->getJson('/accounts/all-customers/data?balance=zero')->assertOk();
        foreach ($zero->json('data') as $row) $this->assertSame('<div class="text-nowrap text-body">AFN <strong>0.00</strong></div><div class="text-nowrap text-body">USD <strong>0.00</strong></div>', $row['balance']);
    }
}
