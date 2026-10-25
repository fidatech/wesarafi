<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialStatementBranchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_branchless_non_super_user_cannot_view_financial_statements(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get(route('financial_reports.balance-sheet.index'))->assertForbidden();
        $this->actingAs($user)->get(route('financial_reports.profit-loss.index'))->assertForbidden();
    }

    public function test_non_super_user_cannot_request_another_branch_financial_statements(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $other = Branch::create(['name' => 'Statement Other', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $this->actingAs($user)->get(route('financial_reports.balance-sheet.index', ['branch_id' => $other->id]))->assertForbidden();
        $this->actingAs($user)->get(route('financial_reports.profit-loss.index', ['branch_id' => $other->id]))->assertForbidden();
    }
}
