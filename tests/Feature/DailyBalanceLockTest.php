<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Models\User;
use App\Models\Currency;
use App\Services\DailyBalanceState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyBalanceLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_transitions_and_post_close_mutation_lock(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $branch = (int) ($user->branch_id ?: 1);
        $currency = Currency::firstOrFail();
        $date = now('Asia/Kabul')->toDateString();
        $this->assertTrue(DailyBalanceState::forBranch($branch)['can_open']);

        FxRate::create(['as_of' => $date, 'type' => 'open', 'branch_id' => $branch, 'currency_id' => $currency->id, 'rate_afn_per_unit' => 1]);
        $this->assertFalse(DailyBalanceState::forBranch($branch)['can_open']);
        $this->assertTrue(DailyBalanceState::forBranch($branch)['can_close']);

        FxRate::create(['as_of' => $date, 'type' => 'close', 'branch_id' => $branch, 'currency_id' => $currency->id, 'rate_afn_per_unit' => 1]);
        $this->assertTrue(DailyBalanceState::forBranch($branch)['closed']);
        $this->assertFalse(DailyBalanceState::forBranch($branch)['can_close']);
        $this->actingAs($user)->patchJson(route('profile.update'), [])->assertStatus(423)->assertJsonPath('business_day_closed', true);
        $this->assertTrue(DailyBalanceState::forBranch($branch, now('Asia/Kabul')->addDay()->toDateString())['can_open']);
    }
}
