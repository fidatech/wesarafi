<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);
        return view('configuration.backups.index');
    }

    public function download()
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);

        $driver = config('database.default');
        abort_unless(in_array($driver, ['mysql', 'sqlite'], true), 422, 'Unsupported database driver.');

        $work = storage_path('app/backups/'.Str::uuid());
        File::ensureDirectoryExists($work, 0700, true);
        $archive = storage_path('app/backups/wesarafi-'.Str::uuid().'.zip');

        try {
            if ($driver === 'mysql') {
                $config = config('database.connections.mysql');
                abort_if(!empty($config['url']), 422, 'DB_URL connections require a separate backup configuration.');
                $args = [
                    'mysqldump', '--single-transaction', '--routines', '--triggers',
                    '--result-file='.$work.'/database.sql',
                    '--host='.(string) $config['host'],
                    '--port='.(string) $config['port'],
                    '--user='.(string) $config['username'],
                    '--', (string) $config['database'],
                ];
                $env = ['MYSQL_PWD' => (string) $config['password']];
                $process = new Process($args, null, $env, null, 300);
                $process->run();
                abort_unless($process->isSuccessful(), 500, 'Database backup failed.');
                $filename = 'database.sql';
            } else {
                $database = config('database.connections.sqlite.database');
                abort_unless(is_string($database) && is_file($database), 422, 'SQLite database not found.');
                // SQLite backup requires a consistent snapshot; do not copy a live WAL database.
                abort(503, 'SQLite online backup requires a snapshot-capable backup service.');
            }

            $zip = new ZipArchive();
            abort_unless($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Cannot create backup archive.');
            try {
                abort_unless($zip->addFile($work.'/'.$filename, $filename), 500, 'Cannot add database to archive.');
            } finally {
                $zip->close();
            }

            return response()->download($archive)->deleteFileAfterSend(true);
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function restore(Request $request)
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);
        // Restoring a database is a destructive administrative operation. It must
        // run through a separate, maintenance-mode CLI workflow with a verified
        // rollback snapshot, not from an HTTP upload.
        abort(503, 'Web-based database restoration is disabled. Use the controlled maintenance restore procedure.');
    }
}
