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
            <hr>
            <h5>Restore the current WeSarafi database</h5>
            <p>The currently connected database is <strong>{{ config('database.connections.mysql.database') }}</strong>. You do not need to create another database.</p>
            <div class="alert alert-warning">Live restoration is available through the protected maintenance command, not through an HTTP request. First run <code>php artisan down</code>, then <code>php artisan backup:restore-current /absolute/path/to/backup.zip</code>. After verifying the result, run <code>php artisan up</code>. The command saves a safety SQL backup and attempts rollback on import failure. Do not close the terminal during recovery.</div>
        </div></div>
    </div>
</div>
@endsection
