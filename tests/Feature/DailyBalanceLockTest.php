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

    public function test_financial_posts_require_open_day_when_enforcement_is_enabled(): void
    {
        $this->seed();
        config()->set('business_day.require_open_for_posting', true);
        $user = User::firstOrFail();
        $this->actingAs($user)->postJson(route('remittances.store'), [])
            ->assertStatus(423)->assertJsonPath('business_day_closed', true);
    }

    public function test_opening_and_closing_rate_endpoints_require_valid_day(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $branch = (int) ($user->branch_id ?: 1);
        $currency = Currency::firstOrFail();
        $date = now('Asia/Kabul')->toDateString();
        $payload = ['date' => $date, 'branch_id' => $branch, 'rates' => [$currency->id => 1]];
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertOk();
        $this->assertSame('open', DailyBalanceState::forBranch($branch)['status']);
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertStatus(409);
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertOk();
        $this->assertTrue(DailyBalanceState::forBranch($branch)['closed']);
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertStatus(409);
    }

    public function test_unauthorized_user_cannot_open_or_close_a_business_day(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $branch = (int) ($user->branch_id ?: 1);
        $currency = Currency::firstOrFail();
        $payload = ['date' => now('Asia/Kabul')->toDateString(), 'branch_id' => $branch, 'rates' => [$currency->id => 1]];
        $this->actingAs($user)->getJson(route('fx.rates.meta'))->assertForbidden();
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertForbidden();
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertForbidden();
        $this->assertTrue(DailyBalanceState::forBranch($branch)['can_open']);
    }

    public function test_business_day_opening_and_closing_records_actor_and_snapshot(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $branch = (int) ($user->branch_id ?: 1);
        $currency = Currency::firstOrFail();
        $payload = ['date' => now('Asia/Kabul')->toDateString(), 'branch_id' => $branch, 'rates' => [$currency->id => 1]];
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertOk();
        $day = \App\Models\BusinessDay::where('branch_id', $branch)->firstOrFail();
        $this->assertSame($user->id, (int) $day->opened_by);
        $this->assertSame($user->id, (int) $day->closed_by);
        $this->assertSame('closed', $day->status);
        $this->assertNotNull($day->closing_snapshot);
        $this->assertNotNull($day->closed_at);
    }

    public function test_branch_manager_cannot_read_or_change_other_branch_business_day(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->givePermissionTo('manage business day');
        $home = (int) ($user->branch_id ?: 1);
        $other = \App\Models\Branch::create(['name' => 'Secondary Branch', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $currency = Currency::firstOrFail();
        $payload = ['date' => now('Asia/Kabul')->toDateString(), 'branch_id' => $other->id, 'rates' => [$currency->id => 1]];
        $this->actingAs($user)->getJson(route('fx.rates.meta', ['branch_id' => $other->id]))->assertForbidden();
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertForbidden();
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertForbidden();
        $this->assertTrue(DailyBalanceState::forBranch($other->id)['can_open']);
        $this->actingAs($user)->getJson(route('fx.rates.meta', ['branch_id' => $home]))->assertOk()->assertJsonPath('branch_id', $home);
    }

    public function test_super_admin_can_select_a_different_branch_for_business_day(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $other = \App\Models\Branch::create(['name' => 'Secondary Branch', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $currency = Currency::firstOrFail();
        $payload = ['date' => now('Asia/Kabul')->toDateString(), 'branch_id' => $other->id, 'rates' => [$currency->id => 1]];
        $this->actingAs($user)->getJson(route('fx.rates.meta', ['branch_id' => $other->id]))->assertOk()->assertJsonPath('branch_id', $other->id);
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertOk();
        $this->assertSame('open', DailyBalanceState::forBranch($other->id)['status']);
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertOk();
        $this->assertSame('closed', DailyBalanceState::forBranch($other->id)['status']);
    }

    public function test_day_transitions_and_post_close_mutation_lock(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
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
