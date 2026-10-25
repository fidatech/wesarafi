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

    public function test_finalized_closing_snapshot_cannot_be_edited_or_deleted(): void
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
        $original = $day->closing_snapshot;
        try {
            $day->update(['closing_snapshot' => ['tampered' => true]]);
            $this->fail('Finalized snapshot was editable.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('business_day', $e->errors());
        }
        try {
            $day->delete();
            $this->fail('Finalized snapshot was deletable.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('business_day', $e->errors());
        }
        $this->assertSame('closed', $day->fresh()->status);
        $this->assertSame($original, $day->fresh()->closing_snapshot);
    }

    public function test_historical_reconciliation_detects_changes_without_mutating_finalized_snapshot(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $branch = (int) ($user->branch_id ?: 1);
        $currency = Currency::firstOrFail();
        $date = now('Asia/Kabul')->toDateString();
        $payload = ['date' => $date, 'branch_id' => $branch, 'rates' => [$currency->id => 1]];
        $this->actingAs($user)->postJson(route('fx.rates.open'), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('fx.rates.close'), $payload)->assertOk();
        $day = \App\Models\BusinessDay::where('branch_id', $branch)->firstOrFail();
        $snapshot = $day->closing_snapshot;
        $this->assertTrue(\App\Services\BusinessDayReconciliation::historicalIntegrity($branch, $date)['consistent']);
        // Simulate a database-level historical alteration that bypasses model guards.
        \Illuminate\Support\Facades\DB::table('transactions')->insert([
            'linked_id' => 'integrity-test', 'branch_id' => $branch, 'date' => $date,
            'currency_id' => $currency->id, 'chart_of_account_id' => \App\Models\ChartOfAccount::firstOrFail()->id,
            'amount' => 10, 'amount_afn' => 10,
            'direction' => 'debit', 'created_by' => $user->id, 'is_safe' => true, 'is_approved' => true,
            'approval_status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $check = \App\Services\BusinessDayReconciliation::historicalIntegrity($branch, $date);
        $this->assertTrue($check['verifiable']);
        $this->assertFalse($check['consistent']);
        $this->assertNotEmpty($check['differences']);
        $this->assertSame($snapshot, $day->fresh()->closing_snapshot);
    }

    public function test_historical_integrity_report_requires_permission_and_respects_branch_scope(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.index'))->assertForbidden();
        $user->assignRole('Super Admin');
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.index'))
            ->assertOk()->assertSee('Historical Closing Integrity');
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.index', ['branch_id' => 999999]))
            ->assertSessionHasErrors('branch_id');
    }

    public function test_historical_integrity_csv_export_is_permission_protected(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.export'))->assertForbidden();
        $user->assignRole('Super Admin');
        $response = $this->actingAs($user)->get(route('financial_reports.historical-integrity.export'));
        $response->assertOk()->assertDownload();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financial-audit',
            'causer_id' => $user->id,
            'description' => 'Historical closing integrity report export requested',
        ]);
        $log = \Spatie\Activitylog\Models\Activity::where('log_name', 'financial-audit')->latest('id')->firstOrFail();
        $this->assertSame('historical_integrity_csv_export', $log->properties->get('action'));
        $this->assertNotNull($log->properties->get('requested_at'));
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.export', ['branch_id' => 999999]))
            ->assertSessionHasErrors('branch_id');
    }

    public function test_financial_audit_export_history_is_authorized_filterable_and_branch_scoped(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $url = route('financial_reports.audit-export-history.index');
        $this->actingAs($user)->get($url)->assertForbidden();
        $user->assignRole('Super Admin');
        $branchId = (int) ($user->branch_id ?: 1);
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.export', ['branch_id' => $branchId]))->assertOk();
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Financial Audit Export History')->assertSee('CSV requested');
        $this->actingAs($user)->get(route('financial_reports.audit-export-history.index', ['user_id' => $user->id]))->assertOk()->assertSee('CSV requested');
        $this->actingAs($user)->get(route('financial_reports.audit-export-history.index', ['user_id' => 999999]))->assertSessionHasErrors('user_id');
    }

    public function test_audit_history_and_export_deny_cross_branch_and_unassigned_manager(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->givePermissionTo('manage business day');
        $home = (int) ($user->branch_id ?: 1);
        $other = \App\Models\Branch::create(['name' => 'Audit Isolation Branch', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $history = route('financial_reports.audit-export-history.index');
        $export = route('financial_reports.historical-integrity.export');
        $this->actingAs($user)->get($history.'?branch_id='.$other->id)->assertForbidden();
        $this->actingAs($user)->get($export.'?branch_id='.$other->id)->assertForbidden();
        $this->actingAs($user)->get($history.'?branch_id='.$home)->assertOk();
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get($history)->assertForbidden();
        $this->actingAs($user)->get($export)->assertForbidden();
        $this->actingAs($user)->get(route('financial_reports.historical-integrity.index'))->assertForbidden();
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
