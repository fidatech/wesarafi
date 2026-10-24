<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConfigurationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_regular_user_cannot_access_customer_or_configuration_management(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/accounts/regular-customers')->assertForbidden();
        $this->get('/configuration/branches')->assertForbidden();
        $this->get('/configuration/currencies')->assertForbidden();
    }

    public function test_branch_user_cannot_view_another_branches_customer_details(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Branch');
        $account = Account::create([
            'account_code' => 'SECURITY-OTHER-BRANCH',
            'full_name' => 'Private customer', 'type' => 'customer',
            'branch_id' => $other->id, 'is_active' => true,
        ]);
        $this->actingAs($user);
        $this->get('/accounts/regular-customers/'.$account->id)->assertNotFound();
        $this->get('/accounts/regular-customers/'.$account->id.'/edit')->assertNotFound();
        $this->delete('/accounts/regular-customers/'.$account->id)->assertNotFound();
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_branch_user_cannot_change_global_currency_configuration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Branch');
        $this->actingAs($user);
        $this->post('/configuration/currencies', [])->assertForbidden();
    }
}
