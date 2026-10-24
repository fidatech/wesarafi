<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreBackup extends Command
{
    protected $signature = 'backup:restore {archive : Absolute path to WeSarafi ZIP} {--database= : Existing, empty, isolated destination database}';
    protected $description = 'Restore a WeSarafi backup into an explicitly selected empty MySQL database (never the active database).';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Only MySQL restores are supported.');
            return self::FAILURE;
        }
        $cfg = config('database.connections.mysql');
        $target = (string) $this->option('database');
        $active = (string) $cfg['database'];
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $target) || strcasecmp($target, $active) === 0) {
            $this->error('Specify a separate database using --database; the active database is forbidden.');
            return self::FAILURE;
        }
        if (!empty($cfg['url']) || !empty(config('database.url'))) {
            $this->error('Database URLs are not supported by this command.');
            return self::FAILURE;
        }
        $archive = realpath((string) $this->argument('archive'));
        if (!$archive || !is_file($archive)) {
            $this->error('Backup archive not found.');
            return self::FAILURE;
        }
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            $this->error('Invalid ZIP archive.');
            return self::FAILURE;
        }
        $work = storage_path('app/backups/restore-'.bin2hex(random_bytes(12)));
        try {
            if ($zip->numFiles !== 1 || $zip->getNameIndex(0) !== 'database.sql') {
                $this->error('Expected exactly one database.sql entry.');
                return self::FAILURE;
            }
            $stat = $zip->statIndex(0);
            if (!$stat || $stat['size'] < 1 || $stat['size'] > 512 * 1024 * 1024) {
                $this->error('Invalid SQL size.');
                return self::FAILURE;
            }
            File::ensureDirectoryExists($work, 0700, true);
            $input = $zip->getStream('database.sql');
            $output = fopen($work.'/database.sql', 'wb');
            if (!$input || !$output) {
                $this->error('Cannot extract SQL.');
                return self::FAILURE;
            }
            $copied = stream_copy_to_stream($input, $output, 512 * 1024 * 1024 + 1);
            fclose($input);
            fclose($output);
            if ($copied !== $stat['size']) {
                $this->error('SQL extraction incomplete.');
                return self::FAILURE;
            }
            $sql = file_get_contents($work.'/database.sql');
            if (!str_contains(substr($sql, 0, 8192), 'MySQL dump') || preg_match('/(?:^|[;\n])\s*(?:USE\s+|CREATE\s+DATABASE\b|DROP\s+DATABASE\b|ALTER\s+DATABASE\b)/im', $sql)) {
                $this->error('Unrecognized or database-switching SQL dump.');
                return self::FAILURE;
            }
            // Query metadata using the configured server connection, never a user-supplied host.
            $exists = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$target]);
            if ((int) $exists->n !== 1) {
                $this->error('Target database must already exist.');
                return self::FAILURE;
            }
            $tables = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$target]);
            if ((int) $tables->n !== 0) {
                $this->error('Target database is not empty; refusing to overwrite data.');
                return self::FAILURE;
            }
            if (!$this->confirm("Import into EMPTY database {$target}?", false)) {
                $this->warn('Cancelled.');
                return self::FAILURE;
            }
            $binary = is_executable('/usr/local/opt/mysql-client/bin/mysql') ? '/usr/local/opt/mysql-client/bin/mysql' : 'mysql';
            $args = [$binary, '--host='.(string) $cfg['host'], '--port='.(string) $cfg['port'], '--user='.(string) $cfg['username'], '--database='.$target];
            $handle = fopen($work.'/database.sql', 'rb');
            if (!$handle) {
                $this->error('Cannot open extracted SQL.');
                return self::FAILURE;
            }
            try {
                $process = new Process($args, null, ['MYSQL_PWD' => (string) $cfg['password']], $handle, 600);
                $process->run();
            } finally {
                fclose($handle);
            }
            if (!$process->isSuccessful()) {
                $this->error('Import failed. Destination may be partially restored; inspect it before retrying. '.substr($process->getErrorOutput(), 0, 1500));
                return self::FAILURE;
            }
            $this->info('Import finished into '.$target.'. Verify record counts and application behavior before any cutover.');
            return self::SUCCESS;
        } finally {
            $zip->close();
            File::deleteDirectory($work);
        }
    }
}
