<?php

namespace Tests\Feature;

use App\Models\{Currency, FxRate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_intraday_buy_sell_pair_sets_midpoint_without_required_manual_rate(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $this->actingAs($user);
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $this->post(route('exchange.rates.intraday.store'), [
            'branch_id' => $user->branch_id, 'date' => '2026-09-27',
            'currency_id' => $usd->id, 'buy_rate' => '68.50', 'sell_rate' => '69.00',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('fx_rate_updates', [
            'branch_id' => $user->branch_id, 'currency_id' => $usd->id,
            'rate_afn_per_unit' => 68.75, 'source' => 'manual',
        ]);
    }

    public function test_non_admin_cannot_read_or_change_other_branch_rates(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $this->actingAs($user);
        $other = \App\Models\Branch::create(['name' => 'FX Restricted Branch', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $this->get(route('exchange.rates.index', ['branch_id' => $other->id]))->assertForbidden();
        $this->post(route('exchange.rates.intraday.store'), [
            'branch_id' => $other->id, 'date' => '2026-09-27', 'currency_id' => $usd->id, 'rate' => 68.75,
        ])->assertForbidden();
        $this->post(route('exchange.rates.store'), [
            'branch_id' => $other->id, 'date' => '2026-09-27',
            'rates' => [$usd->id => ['open' => 68.5, 'close' => 69]],
        ])->assertForbidden();
    }

    public function test_afn_is_fixed_at_one_and_custom_precision_is_preserved(): void
    {
        $this->seed();
        $user = User::first();
        $this->actingAs($user);
        $afn = Currency::where('code', 'AFN')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $this->post(route('exchange.rates.store'), [
            'branch_id' => $user->branch_id, 'date' => '2026-09-27',
            'rates' => [$afn->id => ['open' => 50, 'close' => 0], $usd->id => ['open' => '72.123456', 'close' => '73']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        foreach (['open', 'close'] as $type) {
            $this->assertDatabaseHas('fx_rates', ['currency_id' => $afn->id, 'branch_id' => $user->branch_id, 'as_of' => '2026-09-27 00:00:00', 'type' => $type, 'rate_afn_per_unit' => 1]);
        }
        $this->assertEquals('72.12345600', FxRate::where('currency_id', $usd->id)->whereDate('as_of', '2026-09-27')->where('type', 'open')->firstOrFail()->rate_afn_per_unit);
        $this->get(route('exchange.rates.index', ['branch_id' => $user->branch_id, 'date' => '2026-09-27']))->assertOk()
            ->assertSee('value="1" readonly', false)->assertSee('value="73.00"', false)->assertSee('value="72.123456"', false);
    }
}
