<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreCurrentBackup extends Command
{
    protected $signature = 'backup:restore-current {archive} {--dry-run}';
    protected $description = 'Replace the configured database with a backup, with a safety snapshot and rollback.';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql' || !empty(config('database.connections.mysql.url'))) {
            $this->error('Standard MySQL configuration required.'); return self::FAILURE;
        }
        $cfg = config('database.connections.mysql');
        $db = (string) $cfg['database'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $db)) { $this->error('Invalid configured database name.'); return self::FAILURE; }
        $archive = realpath((string) $this->argument('archive'));
        if (!$archive || !is_file($archive)) { $this->error('Backup not found.'); return self::FAILURE; }
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) { $this->error('Invalid ZIP.'); return self::FAILURE; }
        $work = storage_path('app/backups/restore-live-'.bin2hex(random_bytes(8)));
        try {
            if ($zip->numFiles !== 1 || $zip->getNameIndex(0) !== 'database.sql') { $this->error('Expected only database.sql.'); return self::FAILURE; }
            $stat = $zip->statIndex(0);
            if (!$stat || $stat['size'] < 1 || $stat['size'] > 512 * 1024 * 1024) { $this->error('Invalid SQL size.'); return self::FAILURE; }
            File::ensureDirectoryExists($work, 0700, true);
            $source = $work.'/database.sql';
            $input = $zip->getStream('database.sql'); $output = fopen($source, 'wb');
            if (!$input || !$output) { $this->error('Cannot extract SQL.'); return self::FAILURE; }
            $copied = stream_copy_to_stream($input, $output, 512 * 1024 * 1024 + 1);
            fclose($input); fclose($output);
            if ($copied !== $stat['size']) { $this->error('Incomplete SQL.'); return self::FAILURE; }
            $sql = file_get_contents($source);
            if (!str_contains(substr($sql, 0, 8192), 'MySQL dump') || preg_match('/(?:^|[;\n])\s*(?:USE\s+|CREATE\s+DATABASE\b|DROP\s+DATABASE\b|ALTER\s+DATABASE\b)/im', $sql)) {
                $this->error('Unsupported database-switching SQL.'); return self::FAILURE;
            }
            $this->info('Target: current configured database '.$db);
            if ($this->option('dry-run')) return self::SUCCESS;
            if (!app()->isDownForMaintenance()) { $this->error('Run php artisan down before restoring.'); return self::FAILURE; }
            if (!$this->confirm('Replace the CURRENT database '.$db.'? This is destructive.', false) || $this->ask('Type database name') !== $db) {
                $this->error('Confirmation refused.'); return self::FAILURE;
            }
            $mysql = is_executable('/usr/local/opt/mysql-client/bin/mysql') ? '/usr/local/opt/mysql-client/bin/mysql' : 'mysql';
            $dump = is_executable('/usr/local/opt/mysql-client/bin/mysqldump') ? '/usr/local/opt/mysql-client/bin/mysqldump' : 'mysqldump';
            $common = ['--host='.(string)$cfg['host'], '--port='.(string)$cfg['port'], '--user='.(string)$cfg['username']];
            $env = ['MYSQL_PWD'=>(string)$cfg['password']];
            $safety = storage_path('app/backups/safety-before-restore-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql');
            $backup = new Process(array_merge([$dump, '--single-transaction', '--routines', '--triggers', '--result-file='.$safety], $common, ['--', $db]), null, $env, null, 600);
            $backup->run();
            if (!$backup->isSuccessful() || !is_file($safety) || filesize($safety) < 100) {
                File::delete($safety); $this->error('Safety backup failed. No changes made.'); return self::FAILURE;
            }
            chmod($safety, 0600);
            $this->warn('Safety backup retained: '.$safety);
            // Drop existing tables, views and routines before importing; the original snapshot is preserved.
            try {
                $this->clearSchema($db);
                $this->importSql($mysql, $common, $env, $db, $source);
                $tables = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$db]);
                if ((int)$tables->n === 0) throw new \RuntimeException('No tables imported.');
                $this->info('Restored '.$tables->n.' tables into '.$db.'. Safety backup: '.$safety);
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('Restore failed: '.$e->getMessage().'. Attempting rollback.');
                try {
                    $this->clearSchema($db);
                    $this->importSql($mysql, $common, $env, $db, $safety);
                    $this->warn('Rollback imported. Verify data before bringing application online. Safety file: '.$safety);
                } catch (\Throwable $rollback) {
                    $this->error('ROLLBACK FAILED: '.$rollback->getMessage().'. Keep maintenance mode enabled. Safety file: '.$safety);
                }
                return self::FAILURE;
            }
        } finally {
            $zip->close(); File::deleteDirectory($work);
        }
    }

    private function clearSchema(string $database): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (DB::select('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]) as $item) {
                $name = '`'.str_replace('`', '``', $item->TABLE_NAME).'`';
                DB::statement(($item->TABLE_TYPE === 'VIEW' ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ').$name);
            }
            foreach (DB::select('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?', [$database]) as $item) {
                DB::statement('DROP '.$item->ROUTINE_TYPE.' IF EXISTS `'.str_replace('`', '``', $item->ROUTINE_NAME).'`');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function importSql(string $binary, array $common, array $env, string $db, string $path): void
    {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new \RuntimeException('Cannot open SQL file.');
        try {
            $process = new Process(array_merge([$binary], $common, ['--binary-mode', '--skip-reconnect', '--database='.$db]), null, $env, $handle, 600);
            $process->run();
            if (!$process->isSuccessful()) throw new \RuntimeException(substr($process->getErrorOutput(), 0, 1200));
        } finally { fclose($handle); }
    }
}
