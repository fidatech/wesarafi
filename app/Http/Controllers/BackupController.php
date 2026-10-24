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

        $request->validate([
            'backup' => ['required', 'file', 'max:102400'],
            'confirmation' => ['required', 'in:VALIDATE'],
        ]);

        $file = $request->file('backup');
        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return back()->withErrors(['backup' => 'Please upload a WeSarafi ZIP backup.']);
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            return back()->withErrors(['backup' => 'The ZIP archive cannot be opened.']);
        }

        try {
            if ($zip->numFiles !== 1 || $zip->getNameIndex(0) !== 'database.sql') {
                return back()->withErrors(['backup' => 'The backup must contain only database.sql.']);
            }

            $stat = $zip->statIndex(0);
            if (!$stat || $stat['size'] < 1 || $stat['size'] > 512 * 1024 * 1024) {
                return back()->withErrors(['backup' => 'The database SQL file has an invalid size.']);
            }

            $stream = $zip->getStream('database.sql');
            if (!$stream) {
                return back()->withErrors(['backup' => 'Cannot read database.sql.']);
            }
            $prefix = fread($stream, 8192);
            fclose($stream);
            if ($prefix === false || !str_contains($prefix, 'MySQL dump')) {
                return back()->withErrors(['backup' => 'The archive does not look like a supported MySQL dump.']);
            }
        } finally {
            $zip->close();
        }

        // Deliberately no SQL execution here. A verified backup must be restored
        // by an isolated maintenance process with a tested rollback procedure.
        return back()->with('backup_validation', 'Backup ZIP passed preliminary format checks. Its SQL has not been executed or fully verified. No database was modified.');
    }
}
