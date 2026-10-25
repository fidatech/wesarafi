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

    public function test_super_admin_role_cannot_be_renamed_or_deleted(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $protected = Role::findByName('Super Admin', 'web');

        $this->actingAs($admin)->put(route('configuration.roles.update', $protected->id), [
            'name' => 'Renamed Admin', 'permissions' => [],
        ])->assertForbidden();
        $this->delete(route('configuration.roles.destroy', $protected->id))->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $protected->id, 'name' => 'Super Admin']);
        $this->assertTrue($admin->fresh()->hasRole('Super Admin'));
    }

    public function test_super_admin_role_name_cannot_be_recreated_after_missing_role(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $protected = Role::findByName('Super Admin', 'web');
        $protected->delete();

        $this->actingAs($admin)->post(route('configuration.roles.store'), [
            'name' => 'Super Admin', 'permissions' => [],
        ])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Super Admin']);
    }

    public function test_other_role_cannot_be_renamed_to_super_admin_when_reserved_role_is_missing(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Role::findByName('Super Admin', 'web')->delete();
        $ordinary = Role::create(['name' => 'Operations Manager', 'guard_name' => 'web']);

        $this->actingAs($admin)->put(route('configuration.roles.update', $ordinary->id), [
            'name' => 'Super Admin', 'permissions' => [],
        ])->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $ordinary->id, 'name' => 'Operations Manager']);
        $this->assertDatabaseMissing('roles', ['name' => 'Super Admin']);
    }

    public function test_role_mutations_reject_unknown_permission_ids_without_partial_changes(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $role = Role::create(['name' => 'Cashier Assistant', 'guard_name' => 'web']);
        $unknownPermission = (int) Permission::max('id') + 100;

        $this->actingAs($admin)->postJson(route('configuration.roles.store'), [
            'name' => 'Invalid Permissions Role', 'permissions' => [$unknownPermission],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
        $this->putJson(route('configuration.roles.update', $role->id), [
            'name' => 'Renamed Cashier', 'permissions' => [$unknownPermission],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
        $this->assertDatabaseMissing('roles', ['name' => 'Invalid Permissions Role']);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Cashier Assistant']);
    }

    public function test_role_create_and_update_persist_selected_permissions(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $permissions = Permission::query()->limit(2)->get();
        $this->assertCount(2, $permissions);

        $this->actingAs($admin)->postJson(route('configuration.roles.store'), [
            'name' => 'Treasury Reviewer', 'permissions' => [$permissions[0]->id],
        ])->assertOk();
        $role = Role::findByName('Treasury Reviewer', 'web');
        $this->assertTrue($role->hasPermissionTo($permissions[0]));

        $this->putJson(route('configuration.roles.update', $role->id), [
            'name' => 'Treasury Approver', 'permissions' => [$permissions[1]->id],
        ])->assertOk();
        $role->refresh();
        $this->assertSame('Treasury Approver', $role->name);
        $this->assertTrue($role->hasPermissionTo($permissions[1]));
        $this->assertFalse($role->hasPermissionTo($permissions[0]));
    }

    public function test_deleting_ordinary_role_removes_assignment_but_keeps_permission(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $employee = User::factory()->create();
        $role = Role::create(['name' => 'Temporary Auditor', 'guard_name' => 'web']);
        $permission = Permission::firstOrFail();
        $role->givePermissionTo($permission);
        $employee->assignRole($role);

        $this->actingAs($admin)->delete(route('configuration.roles.destroy', $role->id))->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
        $this->assertFalse($employee->fresh()->hasRole('Temporary Auditor'));
    }

    public function test_deleting_missing_permission_returns_not_found(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $missingId = (int) Permission::max('id') + 100;

        $this->actingAs($admin)->deleteJson(route('configuration.permissions.destroy', $missingId))
            ->assertNotFound();
    }

    public function test_deleting_permission_removes_role_grant_but_preserves_role(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $role = Role::create(['name' => 'Temporary Reviewer', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'temporary review permission', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->actingAs($admin)->deleteJson(route('configuration.permissions.destroy', $permission->id))
            ->assertOk();
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Temporary Reviewer']);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
    }

    public function test_permissions_page_loads_with_sqlite_and_orders_by_last_word(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Permission::create(['name' => 'audit alpha', 'guard_name' => 'web']);
        Permission::create(['name' => 'audit zulu', 'guard_name' => 'web']);

        $response = $this->actingAs($admin)->get(route('configuration.permissions.index'));
        $response->assertOk();
        $response->assertViewHas('permissions', function ($permissions) {
            $names = $permissions->pluck('name')->all();
            return array_search('audit alpha', $names, true) < array_search('audit zulu', $names, true);
        });
    }

    public function test_permission_names_over_database_limit_are_rejected_without_mutation(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $permission = Permission::create(['name' => 'limited permission', 'guard_name' => 'web']);
        $oversizedName = str_repeat('a', 256);

        $this->actingAs($admin)->postJson(route('configuration.permissions.store'), [
            'name' => $oversizedName,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson(route('configuration.permissions.permissions.update', $permission->id), [
            'name' => $oversizedName,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'limited permission']);
        $this->assertDatabaseMissing('permissions', ['name' => $oversizedName]);
    }

    public function test_roles_table_escapes_permission_group_names(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $role = Role::create(['name' => 'Safe Reviewer', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view <script>alert(1)</script>', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $response = $this->actingAs($admin)->getJson(route('configuration.roles.data'));
        $response->assertOk();
        $rows = collect($response->json('data'));
        $html = $rows->firstWhere('name', 'Safe Reviewer')['permissions'];
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;SCRIPT&gt;', $html);
    }

    public function test_oversized_role_names_are_rejected_before_database_writes(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $role = Role::create(['name' => 'Branch Reviewer', 'guard_name' => 'web']);
        $oversizedName = str_repeat('x', 256);

        $this->actingAs($admin)->postJson(route('configuration.roles.store'), [
            'name' => $oversizedName, 'permissions' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson(route('configuration.roles.update', $role->id), [
            'name' => $oversizedName, 'permissions' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Branch Reviewer']);
        $this->assertDatabaseMissing('roles', ['name' => $oversizedName]);
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
