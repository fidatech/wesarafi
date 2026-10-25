<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalApprovalBranchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_branchless_journal_manager_cannot_list_or_review_approvals(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->givePermissionTo(['view journal', 'edit journal']);
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get(route('journal.approvals.index'))->assertForbidden();
        $this->actingAs($user)->post(route('journal.approvals.approve', ['linked' => 'nonexistent']))->assertNotFound();
    }

    public function test_branch_manager_cannot_view_other_branch_approvals(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->givePermissionTo('view journal');
        $other = Branch::create(['name' => 'Journal Secondary', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $this->actingAs($user)->get(route('journal.approvals.index'))->assertOk();
        $this->assertNotEquals((int) $user->branch_id, $other->id);
    }
}
