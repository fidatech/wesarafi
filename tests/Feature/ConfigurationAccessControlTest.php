<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfigurationAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_sensitive_configuration_endpoints(): void
    {
        foreach (['configuration.users.index', 'configuration.users.data', 'configuration.roles.index', 'configuration.roles.data', 'configuration.permissions.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_regular_user_cannot_read_or_mutate_roles_users_and_permissions(): void
    {
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['configuration.users.index', 'configuration.users.data', 'configuration.roles.index', 'configuration.roles.data', 'configuration.permissions.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->post(route('configuration.roles.store'), ['name' => 'Unauthorized Role'])->assertForbidden();
        $this->post(route('configuration.users.store'), ['name' => 'Unauthorized User'])->assertForbidden();
        $this->post(route('configuration.permissions.store'), ['name' => 'unauthorized permission'])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Unauthorized Role']);
    }
}
