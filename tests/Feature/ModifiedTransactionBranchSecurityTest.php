<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifiedTransactionBranchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_non_super_admin_cannot_view_transaction_change_history(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get(route('monitoring.modified-transactions'))->assertForbidden();
    }
}
