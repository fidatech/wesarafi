<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class BackupUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $user = User::firstOrFail();
        $user->assignRole('Super Admin');
        $this->actingAs($user)->withoutMiddleware();
    }

    private function archive(string $entry, string $sql): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ws-zip-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($entry, $sql);
        $zip->close();
        return new UploadedFile($path, 'wesarafi.zip', 'application/zip', null, true);
    }

    public function test_valid_archive_is_checked_without_changing_database(): void
    {
        $before = User::count();
        $this->post(route('configuration.backups.restore'), [
            'backup' => $this->archive('database.sql', "-- MySQL dump\nCREATE TABLE `sample` (`id` int);\n"),
            'confirmation' => 'VALIDATE',
        ])->assertRedirect()->assertSessionHas('backup_validation');
        $this->assertSame($before, User::count());
    }

    public function test_invalid_archive_is_rejected(): void
    {
        $this->post(route('configuration.backups.restore'), [
            'backup' => $this->archive('../database.sql', '-- MySQL dump'),
            'confirmation' => 'VALIDATE',
        ])->assertRedirect()->assertSessionHasErrors('backup');
    }

    public function test_confirmation_is_required(): void
    {
        $this->post(route('configuration.backups.restore'), [
            'backup' => $this->archive('database.sql', '-- MySQL dump'),
            'confirmation' => 'RESTORE',
        ])->assertSessionHasErrors('confirmation');
    }
    public function test_apply_requires_staged_backup(): void
    {
        $this->post(route('configuration.backups.restore.apply'), [
            'database' => 'wesarafi_restore_test3',
            'confirmation' => 'RESTORE TO TEST DATABASE',
        ])->assertRedirect()->assertSessionHasErrors('backup');
    }

    public function test_apply_rejects_bad_confirmation(): void
    {
        $this->post(route('configuration.backups.restore.apply'), [
            'database' => 'wesarafi_restore_test3',
            'confirmation' => 'RESTORE',
        ])->assertSessionHasErrors('confirmation');
    }

}
