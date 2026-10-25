<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatementBranchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_branchless_non_super_user_cannot_read_account_statements(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get(route('financial_reports.account-statement.index'))->assertForbidden();
    }

    public function test_other_branch_account_and_branch_filters_are_forbidden(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $other = Branch::create(['name' => 'Other Statement Branch', 'type' => 'independent', 'province_id' => 1, 'phone' => 0]);
        $account = Account::create(['account_code' => 'OTHER-STATEMENT', 'full_name' => 'Private Other Branch Account', 'type' => 'customer', 'branch_id' => $other->id, 'is_active' => true]);
        $this->actingAs($user)->get(route('financial_reports.account-statement.index', ['branch_id' => $other->id]))->assertForbidden();
        $this->actingAs($user)->get(route('financial_reports.account-statement.index', ['account_id' => $account->id]))->assertForbidden();
        $this->actingAs($user)->get(route('financial_reports.account-statement.index'))->assertOk()->assertDontSee('Private Other Branch Account');
    }
}
