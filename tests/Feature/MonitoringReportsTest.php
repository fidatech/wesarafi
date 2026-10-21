<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\MonitoringReview;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MonitoringReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringReportsTest extends TestCase
{
    use RefreshDatabase;

    private Account $customer;

    private Account $other;

    private Currency $afn;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::first())->withoutMiddleware();
        $this->customer = Account::where('type', 'customer')->where('branch_id', auth()->user()->branch_id)->first();
        $this->other = Account::where('type', 'customer')->where('branch_id', '!=', auth()->user()->branch_id)->first();
        $this->afn = Currency::where('code', 'AFN')->first();
        $this->usd = Currency::where('code', 'USD')->first();
    }

    private function entry(string $direction, float $amount, string $date = '2026-09-14', ?Account $account = null, bool $safe = false, bool $approved = true, ?Currency $currency = null): Transaction
    {
        $account ??= $this->customer;
        $currency ??= $this->afn;

        return Transaction::create(['branch_id' => $account->branch_id, 'account_id' => $account->id,
            'currency_id' => $currency->id, 'chart_of_account_id' => ChartOfAccount::where('code', $safe ? '1000' : '1002')->value('id'),
            'date' => $date, 'direction' => $direction, 'amount' => $amount, 'amount_afn' => $currency->code === 'USD' ? $amount * 70 : $amount,
            'exchange_rate_afn' => $currency->code === 'USD' ? 70 : 1, 'created_by' => auth()->id(), 'is_approved' => $approved, 'is_safe' => $safe]);
    }

    private function context(array $extra = []): array
    {
        return array_merge(['from' => '2026-09-01', 'to' => '2026-09-14', 'branch_id' => auth()->user()->branch_id,
            'currency_id' => null, 'account_id' => null, 'threshold' => 500000, 'days' => 60, 'window_days' => 1], $extra);
    }

    public function test_threshold_combines_currencies_without_counting_safe_or_other_branch(): void
    {
        $this->entry('credit', 220000);
        $this->entry('credit', 4000, currency: $this->usd);
        $this->entry('debit', 900000, safe: true);
        $this->entry('credit', 900000, account: $this->other);
        $this->entry('credit', 900000, approved: false);
        $service = app(MonitoringReportService::class);
        $rows = $service->build('cumulative', $this->context());
        $this->assertCount(1, $rows);
        $this->assertEquals(500000, $rows[0]['cells']['Total AFN']);
        $this->assertCount(2, $rows[0]['details']);
        $this->assertCount(0, $service->build('cumulative', $this->context(['currency_id' => $this->afn->id])));
        $this->assertCount(0, $service->build('large', $this->context()));
        $this->entry('debit', 500000);
        $this->assertCount(1, $service->build('large', $this->context()));
    }

    public function test_cash_flow_opening_in_out_closing_and_deleted_pending_exclusion(): void
    {
        $this->entry('debit', 1000, '2026-08-31', safe: true);
        $this->entry('debit', 500, safe: true);
        $this->entry('credit', 200, safe: true);
        $this->entry('credit', 500); // Customer side is not treasury.
        $this->entry('debit', 9999, safe: true, approved: false);
        $this->entry('debit', 8888, safe: true)->delete();
        $rows = app(MonitoringReportService::class)->build('cash-flow', $this->context());
        $afn = $rows->first(fn ($r) => $r['cells']['Currency'] === 'AFN');
        $this->assertEquals(1000, $afn['cells']['Opening']);
        $this->assertEquals(500, $afn['cells']['Money in']);
        $this->assertEquals(200, $afn['cells']['Money out']);
        $this->assertEquals(1300, $afn['cells']['Closing']);
        $this->assertCount(2, $afn['details']);
        $this->assertCount(2, $rows);
    }

    public function test_inactivity_uses_all_directions_and_currencies_and_never_used_age(): void
    {
        $this->customer->forceFill(['created_at' => '2026-01-01'])->save();
        $service = app(MonitoringReportService::class);
        $context = $this->context(['account_id' => $this->customer->id]);
        $rows = $service->build('inactive', $context);
        $this->assertCount(1, $rows);
        $this->assertSame('Never transacted', $rows[0]['cells']['Last transaction']);
        $this->entry('debit', 10, '2026-07-16', currency: $this->usd); // Exactly cutoff: active.
        $this->assertCount(0, $service->build('inactive', $context));
        $this->assertCount(1, $service->build('inactive', $this->context(['account_id' => $this->customer->id, 'to' => '2026-09-15'])));
    }

    public function test_rapid_kyc_exceptions_and_report_pages(): void
    {
        $this->entry('credit', 500000, '2026-09-13');
        $this->entry('debit', 400000, '2026-09-14');
        $service = app(MonitoringReportService::class);
        $this->assertCount(1, $service->build('rapid', $this->context()));
        $this->assertNotEmpty($service->build('kyc', $this->context()));
        $this->assertNotEmpty($service->build('exceptions', $this->context()));
        foreach (array_keys(MonitoringReportService::TITLES) as $report) {
            $this->get('/monitoring/reports/'.$report.'?from=2026-09-01&to=2026-09-14')->assertOk();
        }
    }

    public function test_review_saves_evidence_and_branch_scope_is_enforced(): void
    {
        $this->entry('credit', 250000);
        $this->entry('credit', 250000);
        $this->entry('credit', 999999, account: $this->other);
        $params = $this->context(['item_key' => $this->customer->branch_id.':'.$this->customer->id.':credit', 'status' => 'reviewing', 'notes' => 'Verify supporting documents.']);
        $this->post('/monitoring/reports/cumulative/review', $params)->assertRedirect(route('monitoring.reviews'));
        $review = MonitoringReview::sole();
        $this->assertEquals(500000, $review->snapshot['cells']['Total AFN']);
        $this->assertSame(auth()->id(), $review->reviewed_by);
        $this->post('/monitoring/reports/cumulative/review', array_merge($params, ['branch_id' => $this->other->branch_id, 'item_key' => 'foreign']))->assertNotFound();
        $this->get('/monitoring/reviews')->assertOk()->assertSee('Verify supporting documents.');
        $pdf = $this->get('/monitoring/reports/cumulative?from=2026-09-01&to=2026-09-14&format=pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        if (! is_dir(base_path('tmp/pdfs'))) {
            mkdir(base_path('tmp/pdfs'), 0777, true);
        }
        file_put_contents(base_path('tmp/pdfs/monitoring-test.pdf'), $pdf->getContent());
        $xlsx = $this->get('/monitoring/reports/cumulative?from=2026-09-01&to=2026-09-14&format=xlsx&length=1')->assertOk();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx->baseResponse->getFile()->getPathname());
        $this->assertEquals(500000,$book->getActiveSheet()->getCell('E2')->getValue());
        unlink($xlsx->baseResponse->getFile()->getPathname());
    }
}
