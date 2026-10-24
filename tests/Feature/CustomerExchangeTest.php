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

    public function test_exchange_page_renders_collapsed_editor(): void
    {
        $this->get(route('exchange.customers.index'))->assertOk()
            ->assertSee('New exchange')->assertSee('Customer details')
            ->assertSee('id="exchangeEditor" class="card exchange-card mb-4"  hidden', false);
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
