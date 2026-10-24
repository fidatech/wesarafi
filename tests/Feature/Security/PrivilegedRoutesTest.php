<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivilegedRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_manage_roles_or_backups(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/configuration/roles')->assertForbidden();
        $this->get('/configuration/backups')->assertForbidden();
        $this->post('/profile/refresh-system')->assertForbidden();
    }

    public function test_super_admin_can_view_privileged_pages(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $this->get('/configuration/roles')->assertOk();
    }

    public function test_guest_cannot_access_privileged_pages(): void
    {
        $this->get('/configuration/roles')->assertRedirect();
        $this->get('/configuration/backups')->assertRedirect();
    }
}
