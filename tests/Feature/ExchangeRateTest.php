<?php

namespace Tests\Feature;

use App\Models\{Currency, FxRate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

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
