<?php

namespace Tests\Feature;

use App\Models\{Account, AccountSubCategory, Branch, Currency, CustomerExchange, Transaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::first());
        Storage::fake('local');
    }

    public function test_quote_uses_buy_sell_midpoint_and_custom_exchange_is_recorded(): void
    {
        $branch = Branch::firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $afn = Currency::where('code', 'AFN')->firstOrFail();
        \App\Models\FxRateUpdate::create([
            'branch_id' => $branch->id, 'currency_id' => $usd->id,
            'rate_date' => '2026-09-27', 'rate_afn_per_unit' => 68.75,
            'buy_rate_afn' => 68.5, 'sell_rate_afn' => 69,
            'source' => 'manual', 'recorded_at' => now(),
        ]);
        $this->getJson(route('exchange.customers.quote', [
            'branch_id' => $branch->id, 'date' => '2026-09-27',
            'from_currency_id' => $usd->id, 'to_currency_id' => $afn->id,
        ]))->assertOk()->assertJsonPath('multiply_rate', 68.75);

        $payload = $this->payload();
        $payload['rate'] = 68.9;
        $this->post(route('exchange.customers.store'), $payload)->assertSessionHasNoErrors();
        $exchange = CustomerExchange::latest('id')->firstOrFail();
        $this->assertDatabaseHas('fx_rate_updates', [
            'branch_id' => $branch->id, 'currency_id' => $usd->id,
            'to_currency_id' => $afn->id, 'source' => 'customer_exchange',
            'customer_exchange_id' => $exchange->id, 'rate_operator' => 'multiply',
        ]);
        $this->assertEqualsWithDelta(68.9, (float) $exchange->rate, 0.000001);
        $this->assertEqualsWithDelta(68.75, (float) $exchange->system_rate, 0.000001);
        $this->assertEqualsWithDelta(68.75, (float) \App\Models\FxRateUpdate::where('source', 'manual')->firstOrFail()->rate_afn_per_unit, 0.000001);
    }

    public function test_cross_currency_quote_uses_both_midpoints_and_records_pair_direction(): void
    {
        $branch = Branch::firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $eur = Currency::where('code', 'EUR')->first();
        if (! $eur) $eur = Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true]);
        foreach ([[$usd, 68.5, 69.5], [$eur, 78.5, 79.5]] as [$currency, $buy, $sell]) {
            \App\Models\FxRateUpdate::create([
                'branch_id' => $branch->id, 'currency_id' => $currency->id,
                'rate_date' => '2026-09-27', 'rate_afn_per_unit' => ($buy + $sell) / 2,
                'buy_rate_afn' => $buy, 'sell_rate_afn' => $sell,
                'source' => 'manual', 'recorded_at' => now(),
            ]);
        }
        $this->getJson(route('exchange.customers.quote', [
            'branch_id' => $branch->id, 'date' => '2026-09-27',
            'from_currency_id' => $usd->id, 'to_currency_id' => $eur->id,
        ]))->assertOk()->assertJsonPath('multiply_rate', 69 / 79);
        $data = $this->payload();
        $data['to_currency_id'] = $eur->id;
        $data['rate_operator'] = 'divide';
        $data['rate'] = 1.2;
        $this->post(route('exchange.customers.store'), $data)->assertSessionHasNoErrors();
        $exchange = CustomerExchange::latest('id')->firstOrFail();
        $observation = \App\Models\FxRateUpdate::where('customer_exchange_id', $exchange->id)->firstOrFail();
        $this->assertSame('divide', $observation->rate_operator);
        $this->assertSame((int) $eur->id, (int) $observation->to_currency_id);
        $this->assertEqualsWithDelta(1.2, (float) $observation->quoted_rate, 0.00000001);
        $this->assertEqualsWithDelta(79 / 1.2, (float) $observation->rate_afn_per_unit, 0.00000001);
        $admin = User::firstOrFail();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        $this->post(route('exchange.customers.reverse', $exchange), ['reason' => 'Incorrect custom cross rate'])->assertRedirect();
        $this->assertSame('reversed', $exchange->fresh()->status);
        $this->assertSame(1, \App\Models\FxRateUpdate::where('customer_exchange_id', $exchange->id)->count());
    }

    private function payload(): array
    {
        return [
            'branch_id' => Branch::first()->id, 'date' => '2026-09-27',
            'customer_id' => '', 'from_currency_id' => Currency::where('code', 'USD')->value('id'),
            'to_currency_id' => Currency::where('code', 'AFN')->value('id'),
            'from_amount' => 100, 'rate' => 72.5, 'rate_operator' => 'multiply',
            'full_name' => 'Walk In Test', 'father_name' => 'Father Test', 'phone' => '0700000000',
            'email' => 'walkin@example.com', 'address' => 'Kabul',
            'id_card_type' => 'etazkira', 'id_card_number' => 'ID-123',
            'is_sanction_list_applied' => '1',
        ];
    }

    public function test_exchange_reversal_preserves_original_and_offsets_all_postings(): void
    {
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $this->post(route('exchange.customers.store'), $this->payload())->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $original = Transaction::where('linked_id', $exchange->linked_id)->get();
        $this->post(route('exchange.customers.reverse', $exchange), ['reason' => 'Correct customer quote'])->assertRedirect();
        $audit = \Illuminate\Support\Facades\DB::table('journal_reversals')->where('original_linked_id', $exchange->linked_id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('reversed', $exchange->fresh()->status);
        $this->get(route('exchange.customers.index'))->assertOk()
            ->assertSee('Reversal audit history')->assertSee('Correct customer quote')
            ->assertSee($audit->reversal_linked_id)->assertSee('reversed');
        $reversal = Transaction::where('linked_id', $audit->reversal_linked_id)->get();
        $this->assertCount($original->count(), $reversal);
        foreach ($original as $line) {
            $opposite = $reversal->first(fn ($entry) => $entry->currency_id === $line->currency_id
                && $entry->chart_of_account_id === $line->chart_of_account_id && $entry->direction !== $line->direction);
            $this->assertNotNull($opposite);
            $this->assertEqualsWithDelta($line->amount_afn, $opposite->amount_afn, 0.001);
        }
        $this->post(route('exchange.customers.reverse', $exchange), ['reason' => 'Second reversal attempt'])->assertStatus(422);
        $this->assertSame($original->count(), Transaction::where('linked_id', $exchange->linked_id)->count());
    }

    public function test_exchange_reversal_nets_cash_customer_and_profit_loss_balances_to_zero(): void
    {
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $this->post(route('exchange.customers.store'), $this->payload())->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $this->post(route('exchange.customers.reverse', $exchange), ['reason' => 'Reverse wrong exchange'])->assertRedirect();
        $audit = \Illuminate\Support\Facades\DB::table('journal_reversals')->where('original_linked_id', $exchange->linked_id)->firstOrFail();
        $lines = Transaction::whereIn('linked_id', [$exchange->linked_id, $audit->reversal_linked_id])->get();
        $this->assertGreaterThanOrEqual(4, $lines->count());
        $this->assertEqualsWithDelta(0, $lines->where('direction', 'debit')->sum('amount_afn') - $lines->where('direction', 'credit')->sum('amount_afn'), 0.02);
        foreach ($lines->groupBy(fn ($line) => implode(':', [$line->branch_id, $line->currency_id, $line->chart_of_account_id, $line->account_id, $line->accountable_type, $line->accountable_id, $line->is_safe])) as $accountLines) {
            $this->assertEqualsWithDelta(0, $accountLines->sum(fn ($line) => $line->direction === 'debit' ? (float) $line->amount_afn : -(float) $line->amount_afn), 0.02);
            $this->assertEqualsWithDelta(0, $accountLines->sum(fn ($line) => $line->direction === 'debit' ? (float) $line->amount : -(float) $line->amount), 0.0001);
        }
    }

    public function test_fx_valuation_reflects_exchange_reversal_without_erasing_original_journal(): void
    {
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $payload = $this->payload();
        $payload['date'] = now()->toDateString();
        $this->post(route('exchange.customers.store'), $payload)->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $branchId = (int) $exchange->branch_id;
        $usdId = (int) Currency::where('code', 'USD')->value('id');
        $asOf = now()->toDateString();
        $this->post(route('exchange.customers.reverse', $exchange), ['reason' => 'Reverse incorrect rate'])->assertRedirect()->assertSessionHasNoErrors();
        $beforeId = \App\Services\DailyValuationService::setClosingAndValue($asOf, $branchId, [$usdId => 72.5]);
        $afterId = \App\Services\DailyValuationService::setClosingAndValue($asOf, $branchId, [$usdId => 72.5]);
        $after = \App\Models\FxDailyValuation::findOrFail($afterId);
        $this->assertSame($beforeId, $afterId);
        $this->assertEqualsWithDelta(0, (float) $after->realized_pl_afn, 0.02);
        $this->assertEqualsWithDelta(0, (float) $after->unrealized_pl_afn, 0.02);
        $this->assertEqualsWithDelta(0, (float) $after->temp_pnl_afn, 0.02);
        $this->assertSame('reversed', $exchange->fresh()->status);
        $this->assertTrue(Transaction::where('linked_id', $exchange->linked_id)->exists());
    }

    public function test_business_day_reconciliation_excludes_unapproved_cash_and_ledger_entries(): void
    {
        $payload = $this->payload();
        $payload['date'] = now('Asia/Kabul')->toDateString();
        $this->post(route('exchange.customers.store'), $payload)->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $branch = (int) $exchange->branch_id;
        $date = now('Asia/Kabul')->toDateString();
        $approved = \App\Services\BusinessDayReconciliation::report($branch, $date);
        $cashLine = Transaction::where('linked_id', $exchange->linked_id)->where('is_safe', true)->firstOrFail();
        $currency = $cashLine->currency_id;
        $cashLine->update(['is_approved' => false, 'approval_status' => 'pending']);
        $pending = \App\Services\BusinessDayReconciliation::report($branch, $date);
        $this->assertSame(1, $pending['pending_approvals']);
        $this->assertFalse($pending['reconciled']);
        $this->assertNotEquals($approved['cash_book_by_currency'][$currency] ?? 0, $pending['cash_book_by_currency'][$currency] ?? 0);
        $this->assertNotEquals($approved['ledger_totals_by_currency_and_direction'][$currency] ?? [], $pending['ledger_totals_by_currency_and_direction'][$currency] ?? []);
        $cashLine->update(['approval_status' => 'rejected']);
        $rejected = \App\Services\BusinessDayReconciliation::report($branch, $date);
        $this->assertSame(0, $rejected['pending_approvals']);
        $this->assertEquals($pending['cash_book_by_currency'], $rejected['cash_book_by_currency']);
    }

    public function test_business_day_reconciliation_detects_partial_and_unbalanced_approved_journals(): void
    {
        $payload = $this->payload();
        $payload['date'] = now('Asia/Kabul')->toDateString();
        $this->post(route('exchange.customers.store'), $payload)->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $branch = (int) $exchange->branch_id;
        $date = now('Asia/Kabul')->toDateString();
        $original = \App\Services\BusinessDayReconciliation::report($branch, $date);
        $this->assertSame(0, $original['invalid_journal_count']);
        $line = Transaction::where('linked_id', $exchange->linked_id)->firstOrFail();
        $line->update(['is_approved' => false, 'approval_status' => 'pending']);
        $partial = \App\Services\BusinessDayReconciliation::report($branch, $date);
        $this->assertFalse($partial['reconciled']);
        $this->assertContains($exchange->linked_id, $partial['invalid_journal_references']);
        $line->update(['is_approved' => true, 'approval_status' => 'approved', 'amount_afn' => (float) $line->amount_afn + 100]);
        $unbalanced = \App\Services\BusinessDayReconciliation::report($branch, $date);
        $this->assertSame(0, $unbalanced['pending_approvals']);
        $this->assertFalse($unbalanced['reconciled']);
        $this->assertContains($exchange->linked_id, $unbalanced['invalid_journal_references']);
    }

    public function test_historical_exchange_reversal_posts_on_current_day_without_rewriting_original_date(): void
    {
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $this->post(route('exchange.customers.store'), $this->payload())->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $originalDates = Transaction::where('linked_id', $exchange->linked_id)->pluck('date')->map(fn ($date) => substr((string) $date, 0, 10))->unique()->values()->all();
        $this->post(route('exchange.customers.reverse', $exchange), ['reason' => 'Reverse historical exchange'])->assertRedirect()->assertSessionHasNoErrors();
        $reference = \Illuminate\Support\Facades\DB::table('journal_reversals')->where('original_linked_id', $exchange->linked_id)->value('reversal_linked_id');
        $this->assertNotNull($reference);
        $this->assertSame($originalDates, Transaction::where('linked_id', $exchange->linked_id)->pluck('date')->map(fn ($date) => substr((string) $date, 0, 10))->unique()->values()->all());
        $this->assertSame([now('Asia/Kabul')->toDateString()], Transaction::where('linked_id', $reference)->pluck('date')->map(fn ($date) => substr((string) $date, 0, 10))->unique()->values()->all());
    }

    public function test_closed_exchange_branch_prevents_reversal_without_changing_historical_postings(): void
    {
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $this->post(route('exchange.customers.store'), $this->payload())->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $originalCount = Transaction::where('linked_id', $exchange->linked_id)->count();
        \App\Models\FxRate::create([
            'as_of' => now('Asia/Kabul')->toDateString(), 'type' => 'close',
            'branch_id' => $exchange->branch_id, 'currency_id' => Currency::firstOrFail()->id,
            'rate_afn_per_unit' => 1,
        ]);
        $this->postJson(route('exchange.customers.reverse', $exchange), ['reason' => 'Incorrect exchange quote'])->assertStatus(423);
        $this->assertSame($originalCount, Transaction::where('linked_id', $exchange->linked_id)->count());
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('journal_reversals')->where('original_linked_id', $exchange->linked_id)->exists());
        $this->assertNotSame('reversed', $exchange->fresh()->status);
    }

    public function test_branch_scoped_financial_report_ignores_requested_branch_override(): void
    {
        $branch = Branch::firstOrFail();
        $user = User::firstOrFail();
        $user->forceFill(['branch_id' => $branch->id])->save();
        $this->actingAs($user);
        $this->get(route('financial_reports.commissions.index', ['branch_id' => 0]))->assertOk()
            ->assertSee('value="'.$branch->id.'" selected', false);
    }

    public function test_posted_exchange_cannot_be_deleted_individually_or_in_bulk(): void
    {
        $this->post(route('exchange.customers.store'), $this->payload())->assertRedirect();
        $exchange = CustomerExchange::firstOrFail();
        $before = Transaction::where('linked_id', $exchange->linked_id)->count();
        $this->delete(route('exchange.customers.destroy', $exchange))->assertStatus(422);
        $this->delete(route('exchange.customers.bulk-destroy'), ['ids' => [$exchange->id]])->assertStatus(422);
        $this->assertNotNull($exchange->fresh());
        $this->assertSame($before, Transaction::where('linked_id', $exchange->linked_id)->count());
    }

    public function test_exchange_page_renders_collapsed_editor(): void
    {
        $this->get(route('exchange.customers.index'))->assertOk()
            ->assertSee('New exchange')->assertSee('Customer details')
            ->assertSee('id="exchangeEditor" class="card exchange-card wes-reference-form mb-4"  hidden', false);
    }

    public function test_walk_in_requires_sanction_confirmation(): void
    {
        $data = $this->payload();
        $data['is_sanction_list_applied'] = '0';
        $this->postJson(route('exchange.customers.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('is_sanction_list_applied');
        $this->assertDatabaseMissing('accounts', ['full_name' => 'Walk In Test']);
        $this->assertDatabaseCount('customer_exchanges', 0);
    }

    public function test_walk_in_is_saved_as_temporary_account_with_private_id_and_custom_rate(): void
    {
        $data = $this->payload();
        $data['id_card_file'] = UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf');
        $this->post(route('exchange.customers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $account = Account::where('full_name', 'Walk In Test')->firstOrFail();
        $this->assertSame('Temporary Customer', $account->subCategory->name_en);
        $this->assertSame('TEMP-'.Account::where('account_sub_category_id', $account->account_sub_category_id)->where('type', 'customer')->count(), $account->account_code);
        foreach (['father_name', 'phone', 'email', 'address', 'id_card_type', 'id_card_number'] as $field) $this->assertSame($data[$field], $account->$field);
        $this->assertTrue((bool) $account->is_sanction_list_applied);
        Storage::disk('local')->assertExists($account->id_card_file);
        $exchange = CustomerExchange::firstOrFail();
        $this->assertEquals($account->id, $exchange->customer_id);
        $this->assertEquals(7250, $exchange->to_amount);
        $lines = Transaction::where('linked_id', $exchange->linked_id)->get();
        $this->assertGreaterThanOrEqual(2, $lines->count());
        $this->assertTrue($lines->every(fn ($line) => $line->module_id == $exchange->id));
        $this->assertTrue($lines->where('is_safe', 1)->every(fn ($line) => $line->account_id == Branch::find($exchange->branch_id)->ensureTreasuryAccount()->id));
        $this->assertEqualsWithDelta($lines->where('direction', 'debit')->sum('amount_afn'), $lines->where('direction', 'credit')->sum('amount_afn'), .01);
        $this->get(route('exchange.customers.id-card', $account))->assertOk();
        $otherBranch = Branch::where('id', '!=', $account->branch_id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $otherBranch->id]);
        $this->actingAs($user)->get(route('exchange.customers.id-card', $account))->assertForbidden();
    }

    public function test_existing_customer_is_reused_without_walk_in_fields(): void
    {
        $account = Account::where('type', 'customer')->firstOrFail();
        $before = Account::count();
        $data = array_diff_key($this->payload(), array_flip(['full_name', 'father_name', 'phone', 'email', 'address', 'id_card_type', 'id_card_number', 'is_sanction_list_applied']));
        $data['customer_id'] = $account->id;
        $data['rate_operator'] = 'divide';
        $data['rate'] = 2;
        $this->post(route('exchange.customers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($before, Account::count());
        $this->assertDatabaseHas('customer_exchanges', ['customer_id' => $account->id, 'to_amount' => 50]);
    }

    public function test_custom_system_rate_is_saved_and_used_for_both_operators(): void
    {
        foreach (['multiply' => 7000, 'divide' => 100 / 70] as $operator => $expected) {
            $data = array_merge($this->payload(), ['rate_operator' => $operator, 'system_rate' => 70]);
            $this->post(route('exchange.customers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $exchange = CustomerExchange::latest('id')->firstOrFail();
            $this->assertEquals(70, $exchange->system_rate);
            $this->assertEqualsWithDelta($expected, $exchange->system_to_amount, .0001);
            $this->assertEqualsWithDelta((72.5 - 70) / 70 * 100, $exchange->rate_variance_percent, .001);
        }
        $this->postJson(route('exchange.customers.store'), array_merge($this->payload(), ['system_rate' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('system_rate');
    }

    public function test_customer_details_include_saved_fields_and_private_download(): void
    {
        $account = Account::where('type', 'customer')->where('branch_id', auth()->user()->branch_id)->firstOrFail();
        $path = 'exchange-customer-ids/customer.pdf';
        Storage::disk('local')->put($path, 'private identity');
        $account->update(['father_name' => 'Saved Father', 'id_card_file' => $path, 'id_card_number' => 'SAVED-ID']);
        $this->getJson(route('exchange.customers.details', $account))->assertOk()
            ->assertJsonPath('full_name', $account->full_name)
            ->assertJsonPath('father_name', 'Saved Father')
            ->assertJsonPath('account_code', $account->account_code)
            ->assertJsonPath('id_card_number', 'SAVED-ID')
            ->assertJsonPath('id_card_url', route('exchange.customers.id-card', $account));
        Storage::disk('local')->delete($path);
        $this->getJson(route('exchange.customers.details', $account))->assertOk()->assertJsonPath('id_card_url', null);
        $this->get(route('exchange.customers.id-card', $account))->assertNotFound();
        $otherBranch = Branch::where('id', '!=', $account->branch_id)->firstOrFail();
        $this->actingAs(User::factory()->create(['branch_id' => $otherBranch->id]))
            ->getJson(route('exchange.customers.details', $account))->assertForbidden();
    }

    public function test_existing_account_upload_is_downloadable_but_paths_cannot_escape_id_directory(): void
    {
        $account = Account::where('type', 'customer')->where('branch_id', auth()->user()->branch_id)->firstOrFail();
        $relative = 'uploads/id_cards/exchange-test-'.uniqid().'.pdf';
        if (!is_dir(dirname(public_path($relative)))) mkdir(dirname(public_path($relative)), 0755, true);
        file_put_contents(public_path($relative), 'legacy identity');
        try {
            $account->update(['id_card_file' => $relative]);
            $this->getJson(route('exchange.customers.details', $account))->assertOk()
                ->assertJsonPath('id_card_url', route('exchange.customers.id-card', $account));
            $this->get(route('exchange.customers.id-card', $account))->assertOk()->assertDownload(basename($relative));
            $account->update(['id_card_file' => 'uploads/id_cards/../../index.php']);
            $this->get(route('exchange.customers.id-card', $account))->assertNotFound();
        } finally {
            unlink(public_path($relative));
        }
    }

    public function test_identical_currencies_are_rejected_for_quote_and_post(): void
    {
        foreach (Currency::whereIn('code', ['AFN', 'USD'])->get() as $currency) {
            $data = array_merge($this->payload(), ['from_currency_id' => $currency->id, 'to_currency_id' => $currency->id]);
            $this->postJson(route('exchange.customers.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('from_currency_id');
            $this->getJson(route('exchange.customers.quote', $data))->assertUnprocessable()->assertJsonValidationErrors('from_currency_id');
        }
    }

    public function test_six_decimal_input_is_preserved_in_exchange_and_ledger(): void
    {
        $data = array_merge($this->payload(), ['from_amount' => '100.123456', 'rate' => '72.123456', 'system_rate' => '70.123456', 'commission_amount' => '1.123456']);
        $this->post(route('exchange.customers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $exchange = CustomerExchange::latest('id')->firstOrFail();
        foreach (['from_amount', 'rate', 'system_rate', 'commission_amount'] as $field) {
            $this->assertEqualsWithDelta((float) $data[$field], (float) $exchange->$field, .00000001);
        }
        $line = Transaction::where('linked_id', $exchange->linked_id)->where('currency_id', $data['from_currency_id'])->where('direction', 'debit')->firstOrFail();
        $this->assertEqualsWithDelta(100.123456, (float) $line->amount, .00000001);
    }

    public function test_failed_post_removes_new_customer_and_uploaded_file(): void
    {
        $this->mock(\App\Services\OperationalLedgerService::class, function ($mock) {
            $mock->shouldReceive('coa')->andReturn(1);
            $mock->shouldReceive('post')->andThrow(new \RuntimeException('Posting failed'));
        });
        $data = $this->payload();
        $data['id_card_file'] = UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf');
        $this->post(route('exchange.customers.store'), $data)->assertStatus(500);
        $this->assertDatabaseMissing('accounts', ['full_name' => 'Walk In Test']);
        $this->assertDatabaseCount('customer_exchanges', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
