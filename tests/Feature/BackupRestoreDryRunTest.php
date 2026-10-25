<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreDryRunTest extends TestCase
{
    private function archive(string $entry, string $sql): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ws-restore-check-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($entry, $sql);
        $zip->close();
        return $path;
    }

    public function test_dry_run_rejects_database_switching_sql_without_touching_database(): void
    {
        $archive = $this->archive('database.sql', "-- MySQL dump\nCREATE DATABASE forbidden_target;\nCREATE TABLE `sample` (`id` int);\n");
        try {
            config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'wesarafi_disposable_test']);
            $result = Artisan::call('backup:restore-current', ['archive' => $archive, '--dry-run' => true]);
            $this->assertSame(1, $result);
            $this->assertStringContainsString('Unsupported database-switching SQL', Artisan::output());
        } finally {
            unlink($archive);
        }
    }

    public function test_dry_run_accepts_structurally_valid_archive_without_importing(): void
    {
        $archive = $this->archive('database.sql', "-- MySQL dump\nCREATE TABLE `sample` (`id` int);\n");
        try {
            config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'wesarafi_disposable_test']);
            $result = Artisan::call('backup:restore-current', ['archive' => $archive, '--dry-run' => true]);
            $this->assertSame(0, $result);
            $this->assertStringContainsString('Target: current configured database wesarafi_disposable_test', Artisan::output());
        } finally {
            unlink($archive);
        }
    }

    public function test_dry_run_rejects_archive_with_unexpected_entry(): void
    {
        $archive = $this->archive('../database.sql', '-- MySQL dump');
        try {
            config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'wesarafi_disposable_test']);
            $this->assertSame(1, Artisan::call('backup:restore-current', ['archive' => $archive, '--dry-run' => true]));
        } finally {
            unlink($archive);
        }
    }
}
