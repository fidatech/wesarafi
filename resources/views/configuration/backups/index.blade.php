@extends('layouts.layoutMaster')
@section('title', 'Backups')
@section('content')
<div class="row g-3">
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
    <div class="col-lg-10">
        <div class="card mb-3"><div class="card-body">
            <h4>Download Backup</h4>
            <p>Download a ZIP containing a snapshot of the current database.</p>
            <a href="{{ route('configuration.backups.download') }}" class="btn btn-primary">Download backup</a>
        </div></div>
        <div class="card"><div class="card-body">
            <h4>Validate Backup for Restore</h4>
            <p>Upload a backup to check its ZIP structure and SQL format. This does not change your database.</p>
            @if(session('backup_validation'))
                <div class="alert alert-success" role="status">{{ session('backup_validation') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" enctype="multipart/form-data" action="{{ route('configuration.backups.restore') }}">
                @csrf
                <label for="backup" class="form-label">Backup ZIP</label>
                <input id="backup" type="file" name="backup" accept=".zip" class="form-control mb-3" required>
                <label for="confirmation" class="form-label">Type VALIDATE to confirm</label>
                <input id="confirmation" name="confirmation" class="form-control mb-3" placeholder="VALIDATE" required>
                <button type="submit" class="btn btn-primary">Validate backup</button>
            </form>
            @if(session('backup_restore_pending'))
            <hr>
            <h5>Restore into a separate empty database</h5>
            <p>This step imports the staged backup into a separate database only. It cannot overwrite the current database.</p>
            <form method="post" action="{{ route('configuration.backups.restore.apply') }}">
                @csrf
                <label for="database" class="form-label">Existing empty MySQL database</label>
                <input id="database" name="database" class="form-control mb-3" required placeholder="wesarafi_restore_test3">
                <label for="restore_confirmation" class="form-label">Type RESTORE TO TEST DATABASE</label>
                <input id="restore_confirmation" name="confirmation" class="form-control mb-3" required>
                <button type="submit" class="btn btn-danger">Restore to empty test database</button>
            </form>
            @endif
            <div class="alert alert-warning mt-3 mb-0">To restore into a separate, empty MySQL database, use the server terminal: <code>php artisan backup:restore /absolute/path/to/backup.zip --database=empty_restore_database</code>. The target database must exist and must not be the active database. The page can import into an empty, separate database after confirmation; it never overwrites the active database. Large imports may exceed web request limits.</div>
        </div></div>
    </div>
</div>
@endsection
