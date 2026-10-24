@extends('layouts.layoutMaster')
@section('title', 'Backup & Restore')
@section('content')
@php
    $pending = session('backup_restore_pending');
    $staged = is_array($pending) && isset($pending['id'], $pending['time']) && (time() - $pending['time'] < 1800);
@endphp
<div class="row g-3">
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
    <div class="col-lg-10">
        <div class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div><h4 class="mb-1">Backup &amp; Restore</h4><p class="text-muted mb-0">Guided database recovery for WeSarafi</p></div>
            <a href="{{ route('configuration.backups.download') }}" class="btn btn-outline-primary">Download safety backup</a>
        </div></div>
        <div class="card"><div class="card-body p-4">
            @if(session('backup_validation'))<div class="alert alert-success" role="status">{{ session('backup_validation') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
            <div class="d-flex align-items-center gap-2 mb-4 flex-wrap" aria-label="Restore wizard steps">
                <span class="badge rounded-pill bg-primary">1. Upload</span><span class="text-muted">→</span>
                <span class="badge rounded-pill {{ $staged ? 'bg-primary' : 'bg-label-secondary' }}">2. Review</span><span class="text-muted">→</span>
                <span class="badge rounded-pill {{ $staged ? 'bg-primary' : 'bg-label-secondary' }}">3. Confirm</span><span class="text-muted">→</span>
                <span class="badge rounded-pill bg-label-secondary">4. Verify &amp; recover</span>
            </div>
            <div id="restore-step-upload" @if($staged) style="display:none" @endif>
                <h5>Step 1 — Upload backup</h5>
                <p class="text-muted">Select a WeSarafi ZIP backup. We validate its structure before offering the next step. Uploading does not change the database.</p>
                <form method="post" enctype="multipart/form-data" action="{{ route('configuration.backups.restore') }}">
                    @csrf
                    <label for="backup" class="form-label">Backup ZIP file</label>
                    <input id="backup" type="file" name="backup" accept=".zip" class="form-control mb-3" required>
                    <button type="submit" class="btn btn-primary">Validate &amp; continue <i class="ti ti-arrow-right ms-1"></i></button>
                </form>
            </div>
            @if($staged)
            <div id="restore-step-review">
                <h5>Step 2 — Review restore destination</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="text-muted small mb-1">Destination database</div><strong>{{ config('database.connections.mysql.database') }}</strong><div class="small text-muted mt-1">Currently connected MySQL database</div></div></div>
                    <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="text-muted small mb-1">Uploaded archive</div><strong>ZIP validated</strong><div class="small text-muted mt-1">Staged for up to 30 minutes</div></div></div>
                </div>
                <div class="alert alert-warning">The system first creates a safety backup and tests the uploaded SQL in an isolated temporary database. Only after that test passes does it replace the connected database. If the live import fails, rollback is attempted.</div>
                <div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('restore-step-review').hidden=true;document.getElementById('restore-step-upload').style.display='block';">Choose a different file</button><button type="button" class="btn btn-primary" onclick="document.getElementById('restore-step-review').hidden=true;document.getElementById('restore-step-confirm').hidden=false;">Continue to confirmation</button></div>
            </div>
            <div id="restore-step-confirm" hidden>
                <h5>Step 3 — Confirm restoration</h5>
                <p class="text-muted">For your security, confirm the exact operation and enter your current account password.</p>
                <form id="restore-apply-form" method="post" action="{{ route('configuration.backups.restore.apply') }}">
                    @csrf
                    <label for="restore_confirmation" class="form-label">Type <strong>RESTORE CURRENT DATABASE</strong></label>
                    <input id="restore_confirmation" name="confirmation" class="form-control mb-3" required autocomplete="off" spellcheck="false" placeholder="RESTORE CURRENT DATABASE">
                    <label for="restore_password" class="form-label">Account password</label>
                    <input id="restore_password" type="password" name="password" class="form-control mb-3" required autocomplete="current-password">
                    <div class="form-check mb-3"><input id="restore_ack" type="checkbox" class="form-check-input" required><label for="restore_ack" class="form-check-label">I understand the current database will be replaced and the application may be temporarily unavailable.</label></div>
                    <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('restore-step-confirm').hidden=true;document.getElementById('restore-step-review').hidden=false;">Back</button><button type="submit" class="btn btn-danger">Start database restore</button></div>
                </form>
            </div>
            <div id="restore-step-progress" hidden role="status" aria-live="polite">
                <h5>Step 4 — Restoring database</h5>
                <div class="d-flex align-items-center gap-3 my-4"><div class="spinner-border text-primary" role="status"></div><div><strong>Recovery is running</strong><p class="text-muted mb-0">The server is performing a safety backup, isolated preflight, live import, and verification. Keep this tab open.</p></div></div>
                <div class="alert alert-info">Waiting for the server to return a verified outcome. No estimated percentage is shown because database imports are not reliably measurable by the browser.</div>
                <p class="small text-muted mt-3">This is not a measured progress percentage. A restore can continue after an HTTP timeout. Do not submit another restore until the administrator checks the restore history and database state.</p>
            </div>
            <script>
            document.getElementById('restore-apply-form')?.addEventListener('submit', function(e) {
                if (!this.reportValidity()) return;
                if (document.getElementById('restore_confirmation').value !== 'RESTORE CURRENT DATABASE') { e.preventDefault(); alert('Type RESTORE CURRENT DATABASE exactly.'); return; }
                if (!window.confirm('Replace the currently connected WeSarafi database?')) { e.preventDefault(); return; }
                document.getElementById('restore-step-confirm').hidden = true;
                document.getElementById('restore-step-progress').hidden = false;
                this.querySelector('[type="submit"]').disabled = true;
            });
            </script>
            @endif
            @if(!empty($restoreHistory))
            <hr class="my-4"><h5>Recent recovery events</h5>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Time</th><th>Stage</th><th>Result</th></tr></thead><tbody>
            @foreach($restoreHistory as $event)<tr><td>{{ $event['time'] ?? '—' }}</td><td>{{ ucfirst(str_replace('_', ' ', $event['stage'] ?? 'unknown')) }}</td><td>{{ ucfirst($event['outcome'] ?? 'unknown') }}</td></tr>@endforeach
            </tbody></table></div>
            @endif
            <hr class="my-4"><p class="text-muted small mb-0">For large databases, use the protected maintenance command instead of the browser to avoid HTTP timeouts. Recovery does not include uploaded files or documents.</p>
        </div></div>
    </div>
</div>
@endsection
