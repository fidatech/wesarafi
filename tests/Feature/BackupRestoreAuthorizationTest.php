<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupRestoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_reach_restore_staging_or_application(): void
    {
        $this->post(route('configuration.backups.restore'))->assertRedirect(route('login'));
        $this->post(route('configuration.backups.restore.apply'))->assertRedirect(route('login'));
        $this->get(route('configuration.backups.download'))->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_reach_restore_staging_or_application(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        $this->actingAs(User::factory()->create());
        $this->post(route('configuration.backups.restore'))->assertForbidden();
        $this->post(route('configuration.backups.restore.apply'))->assertForbidden();
        $this->get(route('configuration.backups.download'))->assertForbidden();
    }

    public function test_super_admin_cannot_apply_restore_without_valid_confirmation(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        $this->post(route('configuration.backups.restore.apply'), [
            'confirmation' => 'RESTORE',
        ])->assertSessionHasErrors(['confirmation', 'password']);
    }
}
