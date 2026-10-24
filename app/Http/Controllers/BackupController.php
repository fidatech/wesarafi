<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
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

        $id = (string) Str::uuid();
        $directory = storage_path('app/backups/pending');
        File::ensureDirectoryExists($directory, 0700, true);
        $destination = $directory.'/'.$id.'.zip';
        if (!copy($file->getRealPath(), $destination)) {
            return back()->withErrors(['backup' => 'Unable to stage backup for recovery.']);
        }
        chmod($destination, 0600);
        session(['backup_restore_pending' => ['id' => $id, 'sha256' => hash_file('sha256', $destination), 'time' => time()]]);
        return back()->with('backup_validation', 'Backup ZIP staged. Confirm below to restore into the current database.');
    }

    public function applyRestore(Request $request)
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);
        $request->validate([
            'confirmation' => ['required', 'in:RESTORE CURRENT DATABASE'],
            'password' => ['required', 'current_password'],
        ]);
        $pending = session('backup_restore_pending');
        if (!is_array($pending) || !isset($pending['id'], $pending['sha256'], $pending['time']) || time() - $pending['time'] > 1800) {
            return back()->withErrors(['backup' => 'Backup expired. Upload again.']);
        }
        $archive = storage_path('app/backups/pending/'.$pending['id'].'.zip');
        if (!is_file($archive) || !hash_equals($pending['sha256'], hash_file('sha256', $archive))) {
            return back()->withErrors(['backup' => 'Backup missing or changed.']);
        }
        if (config('database.default') !== 'mysql' || app()->isDownForMaintenance()) {
            return back()->withErrors(['backup' => 'Recovery unavailable in the current environment.']);
        }
        $lockPath = storage_path('app/backups/restore.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $lock = fopen($lockPath, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return back()->withErrors(['backup' => 'Another recovery is running.']);
        }
        $authorization = null;
        try {
            $dir = storage_path('app/backups/authorizations');
            File::ensureDirectoryExists($dir);
            $authorization = $dir.'/'.Str::uuid().'.json';
            file_put_contents($authorization, json_encode([
                'database' => config('database.connections.mysql.database'),
                'archive_hash' => hash_file('sha256', $archive),
                'created_at' => time(),
            ]));
            chmod($authorization, 0600);
            Artisan::call('down');
            $process = new Process([PHP_BINARY, base_path('artisan'), 'backup:restore-current', $archive, '--authorization='.$authorization], base_path(), null, null, 1200);
            $process->run();
            if (!$process->isSuccessful()) {
                report(new \RuntimeException('Restore failed: '.$process->getErrorOutput().' '.$process->getOutput()));
                return response('Restore failed. Maintenance mode remains active. Contact administrator to inspect recovery logs and safety backup.', 503);
            }
            Artisan::call('up');
            return redirect()->route('login')->with('status', 'Database restored. Sign in again.');
        } catch (\Throwable $e) {
            report($e);
            return response('Restore interrupted. Check maintenance mode and recovery logs.', 503);
        } finally {
            if ($authorization) File::delete($authorization);
            session()->forget('backup_restore_pending');
            File::delete($archive);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
