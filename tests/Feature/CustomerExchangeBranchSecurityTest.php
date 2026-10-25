<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerExchangeBranchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_branchless_user_cannot_list_customer_exchanges(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $user->forceFill(['branch_id' => null])->save();
        $this->actingAs($user)->get(route('exchange.customers.index'))->assertForbidden();
    }

    public function test_other_branch_exchange_quote_is_forbidden(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->syncRoles([]);
        $other = Branch::create(['name' => 'Exchange Other', 'province_id' => 1, 'phone' => 0, 'type' => 'independent']);
        $currencies = Currency::limit(2)->pluck('id');
        $this->actingAs($user)->get(route('exchange.customers.quote', [
            'date' => now()->toDateString(), 'branch_id' => $other->id,
            'from_currency_id' => $currencies[0], 'to_currency_id' => $currencies[1],
        ]))->assertForbidden();
    }
}
