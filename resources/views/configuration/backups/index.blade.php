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
            <h5>Restore current database</h5>
            <div class="alert alert-danger">This replaces all data in the currently connected <strong>{{ config('database.connections.mysql.database') }}</strong> database. An automatic safety backup is created first. The application enters maintenance mode and rollback is attempted if the import fails.</div>
            <form method="post" action="{{ route('configuration.backups.restore.apply') }}" onsubmit="return confirm('Replace the CURRENT WeSarafi database with the uploaded backup?');">
                @csrf
                <label for="restore_confirmation" class="form-label">Type RESTORE CURRENT DATABASE</label>
                <input id="restore_confirmation" name="confirmation" class="form-control mb-3" required autocomplete="off">
                <label for="restore_password" class="form-label">Confirm your password</label>
                <input id="restore_password" name="password" type="password" class="form-control mb-3" required autocomplete="current-password">
                <button class="btn btn-danger" type="submit">Restore current database</button>
            </form>
            @endif
            <p class="text-muted mt-3">Large restores may exceed web-server time limits. Use the maintenance CLI for large backups.</p>
        </div></div>
    </div>
</div>
@endsection
