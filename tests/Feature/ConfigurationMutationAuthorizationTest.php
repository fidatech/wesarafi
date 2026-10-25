<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConfigurationMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_configuration_records(): void
    {
        foreach (['configuration.roles.store', 'configuration.users.store', 'configuration.permissions.store'] as $name) {
            $this->post(route($name), ['name' => 'Guest Attempt'])->assertRedirect(route('login'));
        }
        $this->assertDatabaseMissing('roles', ['name' => 'Guest Attempt']);
    }

    public function test_non_admin_cannot_change_roles_or_permissions(): void
    {
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $target = Role::create(['name' => 'Protected Role', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'protected permission', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create());

        $this->get(route('configuration.roles.edit', $target->id))->assertForbidden();
        $this->put(route('configuration.roles.update', $target->id), ['name' => 'Changed Role'])->assertForbidden();
        $this->delete(route('configuration.roles.destroy', $target->id))->assertForbidden();
        $this->put(route('configuration.permissions.permissions.update', $permission->id), ['name' => 'changed permission'])->assertForbidden();
        $this->delete(route('configuration.permissions.destroy', $permission->id))->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $target->id, 'name' => 'Protected Role']);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'protected permission']);
    }
}
