<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalanceBranchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_non_super_admin_cannot_read_global_trial_balance(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get(route('financial_reports.trial-balance.index'))->assertForbidden();
    }

    public function test_branch_user_cannot_request_other_branch_trial_balance(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $other = Branch::create(['name' => 'Trial Balance Other', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $this->actingAs($user)->get(route('financial_reports.trial-balance.index', ['branch_id' => $other->id]))->assertForbidden();
        $this->actingAs($user)->get(route('financial_reports.trial-balance.index'))->assertOk();
    }
}
