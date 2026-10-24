<?php

namespace Tests\Feature\Security;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_regular_user_cannot_view_attendance_or_payroll(): void
    {
        $user = User::factory()->create(['branch_id' => Branch::firstOrFail()->id]);
        $this->actingAs($user);
        $this->get('/hr/attendance')->assertForbidden();
        $this->get('/hr/payroll')->assertForbidden();
        $this->post('/hr/payroll/generate', [])->assertForbidden();
    }

    public function test_branch_manager_cannot_request_other_branch_attendance(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Branch');
        $this->actingAs($user);
        $this->get('/hr/attendance?branch_id='.$other->id)->assertForbidden();
    }

    public function test_branch_manager_cannot_generate_payroll_for_other_branch(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Branch');
        $this->actingAs($user);
        $this->post('/hr/payroll/generate', [
            'month' => 10, 'year' => 2026, 'branch_id' => $other->id, 'currency_id' => 1,
        ])->assertForbidden();
    }

    public function test_admin_cannot_generate_payroll_for_other_branch(): void
    {
        $branch = Branch::firstOrFail();
        $other = Branch::whereKeyNot($branch->id)->firstOrFail();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Admin');
        $this->actingAs($user);
        $this->post('/hr/payroll/generate', [
            'month' => 10, 'year' => 2026, 'branch_id' => $other->id, 'currency_id' => 1,
        ])->assertForbidden();
    }
}
