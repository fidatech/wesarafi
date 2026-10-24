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
            <style>
                .ws-stepper{display:flex;gap:.55rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem}
                .ws-step{display:flex;align-items:center;gap:.5rem;color:#8592a3;font-size:.875rem}
                .ws-step .ws-number{display:inline-grid;place-items:center;width:28px;height:28px;border:1px solid #d9dee3;border-radius:50%;font-weight:700}
                .ws-step.is-active{color:#245db3;font-weight:700}.ws-step.is-active .ws-number{color:#fff;background:#245db3;border-color:#245db3}
                .ws-step.is-done{color:#21866d}.ws-step.is-done .ws-number{border-color:#21866d;background:#e5f7f1}
                .ws-step-divider{width:28px;height:1px;background:#d9dee3}
                [hidden]{display:none!important}
            </style>
            <div class="ws-stepper" aria-label="Restore wizard progress">
                @foreach(['Upload','Review','Confirm','Restore'] as $i => $label)
                    @if($i > 0)<span class="ws-step-divider" aria-hidden="true"></span>@endif
                    <div class="ws-step" data-ws-step="{{ $i + 1 }}" @if($i===0) aria-current="step" @endif><span class="ws-number">{{ $i + 1 }}</span><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <div id="restore-step-upload" @if($staged) hidden @endif>
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
                <p class="text-muted small">The system creates a safety backup and checks the SQL in an isolated database before replacing the current database. Failed imports trigger a rollback attempt.</p>
                <div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary" onclick="wsRestoreStep(1)">Choose a different file</button><button type="button" class="btn btn-primary" onclick="wsRestoreStep(3)">Continue to confirmation</button></div>
            </div>
            <div id="restore-step-confirm" hidden>
                <h5>Step 3 — Confirm restoration</h5>
                <p class="text-muted">For your security, confirm the exact operation and enter your current account password.</p>
                <form id="restore-apply-form" autocomplete="off" method="post" action="{{ route('configuration.backups.restore.apply') }}">
                    @csrf
                    <label for="restore_confirmation" class="form-label">Type <strong>RESTORE CURRENT DATABASE</strong></label>
                    <input id="restore_confirmation" name="restore_phrase_input" class="form-control mb-3" required autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="RESTORE CURRENT DATABASE">
                    <label for="restore_password" class="form-label">Account password</label>
                    <input id="restore_password" type="password" name="restore_password_input" class="form-control mb-3" required autocomplete="off">
                    <div class="form-check mb-3"><input id="restore_ack" type="checkbox" class="form-check-input" required><label for="restore_ack" class="form-check-label">I understand the current database will be replaced and the application may be temporarily unavailable.</label></div>
                    <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" onclick="wsRestoreStep(2)">Back</button><button type="submit" class="btn btn-danger">Start database restore</button></div>
                </form>
            </div>
            <div id="restore-step-progress" hidden role="status" aria-live="polite">
                <h5>Step 4 — Restoring database</h5>
                <div class="d-flex align-items-center gap-3 my-4"><div class="spinner-border text-primary" role="status"></div><div><strong>Recovery is running</strong><p class="text-muted mb-0">The server is performing a safety backup, isolated preflight, live import, and verification. Keep this tab open.</p></div></div>
                <div class="rounded p-3" style="background:#101a2a;color:#d7f9e4;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;max-height:360px;overflow:auto" id="ws-restore-terminal" role="log" aria-label="Live database restore log"><div>&gt; Waiting for server...</div></div>
                <p class="small text-muted mt-3">This is not a measured progress percentage. A restore can continue after an HTTP timeout. Do not submit another restore until the administrator checks the restore history and database state.</p>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const notices = [];
                @if(session('backup_validation'))
                notices.push({icon:'success',title:'Backup validated',text:@json(session('backup_validation'))});
                @endif
                @if($errors->any())
                notices.push({icon:'error',title:'Action could not be completed',text:@json($errors->first())});
                @endif
                if (notices.length && typeof Swal !== 'undefined') {
                    Swal.fire({confirmButtonText:'OK',...notices[0]});
                }
            });
            function wsRestoreStep(step) {
                const names = ['upload','review','confirm','progress'];
                names.forEach((name,index) => {
                    const panel = document.getElementById('restore-step-'+name);
                    if (panel) panel.hidden = index + 1 !== step;
                });
                document.querySelectorAll('[data-ws-step]').forEach((node,index) => {
                    const number = index + 1;
                    node.classList.toggle('is-active', number === step);
                    node.classList.toggle('is-done', number < step);
                    if (number === step) node.setAttribute('aria-current','step');
                    else node.removeAttribute('aria-current');
                });
            }
            wsRestoreStep({{ $staged ? 2 : 1 }});
            // Browser autofill may ignore autocomplete=off for recognizable credentials.
            // Keep visible controls readonly until intentional focus and use non-login names.
            for (const id of ['restore_confirmation','restore_password']) {
                const input = document.getElementById(id);
                if (!input) continue;
                input.readOnly = true;
                input.addEventListener('focus', () => { input.readOnly = false; }, {once:true});
            }
            document.getElementById('restore-apply-form')?.addEventListener('submit', function(e) {
                if (!this.reportValidity()) return;
                e.preventDefault();
                if (document.getElementById('restore_confirmation').value !== 'RESTORE CURRENT DATABASE') {
                    Swal.fire({icon:'error',title:'Confirmation does not match',text:'Type RESTORE CURRENT DATABASE exactly.'});
                    return;
                }
                Swal.fire({
                    icon:'warning', title:'Restore current database?',
                    text:'This will replace the connected WeSarafi database. A safety backup and preflight check run first.',
                    showCancelButton:true, confirmButtonText:'Yes, restore database', cancelButtonText:'Cancel',
                    confirmButtonColor:'#d33', reverseButtons:true, focusCancel:true
                }).then(async result => {
                    if (!result.isConfirmed) return;
                    wsRestoreStep(4);
                    const submit = this.querySelector('[type="submit"]');
                    submit.disabled = true;
                    const terminal = document.getElementById('ws-restore-terminal');
                    terminal.replaceChildren();
                    const appendLine = (message) => {
                        const row = document.createElement('div');
                        row.textContent = '> ' + message;
                        terminal.appendChild(row);
                        terminal.scrollTop = terminal.scrollHeight;
                    };
                    let completed = false;
                    const handle = async (event) => {
                        if (event.type === 'success') {
                            completed = true;
                            appendLine(event.message);
                            await Swal.fire({icon:'success',title:'Restore completed',text:event.message,confirmButtonText:'OK',allowOutsideClick:false,allowEscapeKey:false});
                            // Logout is a POST: invalidate the authenticated session before redirecting.
                            const logout = document.createElement('form');
                            logout.method = 'POST';
                            logout.action = @json(route('logout'));
                            const csrf = document.createElement('input');
                            csrf.type = 'hidden';
                            csrf.name = '_token';
                            csrf.value = @json(csrf_token());
                            logout.appendChild(csrf);
                            document.body.appendChild(logout);
                            logout.submit();
                        } else if (event.type === 'error') {
                            completed = true;
                            appendLine(event.message);
                            await Swal.fire({icon:'error',title:'Restore failed',text:event.message});
                        } else if (event.type === 'table' || event.type === 'stage') {
                            appendLine(event.message);
                        }
                    };
                    try {
                        const payload = new FormData(this);
                    payload.set('confirmation', document.getElementById('restore_confirmation').value);
                    payload.set('password', document.getElementById('restore_password').value);
                    const response = await fetch(this.action, {
                            method:'POST',body:payload,
                            headers:{'Accept':'application/x-ndjson','X-Requested-With':'XMLHttpRequest'},
                            credentials:'same-origin'
                        });
                        if (!response.ok || !response.body) throw new Error('Server did not start the restore. Check validation and logs.');
                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';
                        while (true) {
                            const {value,done} = await reader.read();
                            buffer += decoder.decode(value || new Uint8Array(),{stream:!done});
                            let newline;
                            while ((newline=buffer.indexOf('\n')) >= 0) {
                                const line=buffer.slice(0,newline);buffer=buffer.slice(newline+1);
                                if (line.trim()) await handle(JSON.parse(line));
                            }
                            if (done) break;
                        }
                        if (!completed) throw new Error('Connection ended without a verified recovery result.');
                    } catch (error) {
                        appendLine('Connection lost or restore status unknown. Do not retry before checking server logs.');
                        await Swal.fire({icon:'error',title:'Restore status unknown',text:'The browser could not verify completion. Do not retry until the administrator checks recovery history and database status.'});
                    }
                });
            });
            </script>
            @else
            <script>document.querySelector('[data-ws-step="1"]')?.classList.add('is-active');</script>
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
