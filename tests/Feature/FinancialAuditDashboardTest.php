<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAuditDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_permission_and_shows_counts_for_super_admin(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $url = route('financial_reports.audit-dashboard.index');
        $this->actingAs($user)->get($url)->assertForbidden();
        $user->assignRole('Super Admin');
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Financial Audit Dashboard')->assertSee('Recorded CSV export requests');
    }

    public function test_dashboard_denies_other_branch_and_unassigned_manager(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->givePermissionTo('manage business day');
        $other = Branch::create(['name' => 'Audit Dashboard Other', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $url = route('financial_reports.audit-dashboard.index');
        $this->actingAs($user)->get($url.'?branch_id='.$other->id)->assertForbidden();
        $this->actingAs($user)->get($url.'?branch_id='.$user->branch_id)->assertOk();
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get($url)->assertForbidden();
    }
}
