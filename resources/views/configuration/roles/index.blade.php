@php
    use Illuminate\Support\Str;

    $grouped = [];

    foreach ($permissions as $permission) {
        $parts = explode(' ', strtolower($permission->name)); // e.g., view users
        if (count($parts) < 2) {
            continue;
        }

        $action = $parts[0]; // view
        $entity = strtoupper($parts[1]); // users

        $grouped[$entity][$action] = [
            'id' => $permission->id,
            'name' => $permission->name,
            'action' => ucfirst($action),
        ];
    }

    $actionOrder = ['view', 'create', 'edit', 'delete'];
@endphp
@extends('layouts.layoutMaster')

@section('title', 'Roles')

@section('vendor-style')
@endsection

@section('content')
<div class="row g-3">
    {{-- Sidebar --}}
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
    {{-- Main Content --}}
    <div class="col-lg-10 fb-content">
        {{-- Sticky Header --}}
        <div class="fb-header">
            <div class="d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                <i class="bx bx-home-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Roles</li>
                    </ol>
                </nav>
                <button class="btn btn-primary"
                    data-wes-form-target="#roleForm"
                    onclick="$('#formTitle').text('Add Role'); $('#roleFormSubmit')[0].reset(); $('#role_id').val(''); $('.permission-checkbox').prop('checked', false);">
                    <i class="bx bx-plus me-1"></i> Add Role
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="fb-main">
            <div class="fb-table-wrapper">
                <h4 class="card-title mb-3">Roles</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table-hover table table-bordered align-middle text-center" id="roles-table" style="width: 100%;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>Role</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        {{-- Inline Form --}}
            <div class="fb-inline-form-panel d-none" tabindex="-1" id="roleForm">
                <div class="fb-inline-form-header">
                    <h5 id="formTitle">Add Role</h5>
                    <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
                </div>
                <form id="roleFormSubmit" class="fb-inline-form-body">
                    @csrf
                    <input type="hidden" id="role_id" name="id">
                    @php
                        $groupedPermissions = collect($permissions)->groupBy(function ($perm) {
                            return strtoupper(explode('.', $perm->name)[0]);
                        });
                    @endphp

                    <div class="mb-3">
                        <label for="name" class="form-label">Role Name</label>
                        <input type="text" name="name" id="name" class="form-control">
                        <small class="text-danger" id="error-name"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Permissions</label>

                        <div class="d-flex justify-content-between mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPermissions()">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="unselectAllPermissions()">Unselect All</button>
                        </div>

                        <div class="card border shadow-sm" style="max-height: 400px; overflow-y: auto;">
                            <div class="card-body">
                                <div class="row g-3">
                                    @foreach ($grouped as $entity => $actions)
                                        <div class="col-md-4">
                                            <div class="border rounded-3 shadow-sm p-3 h-100">
                                                <div class="fw-semibold text-uppercase text-muted small mb-2">{{ $entity }}</div>
                                                <div class="d-flex flex-wrap gap-3">
                                                    @foreach ($actionOrder as $action)
                                                        @if (isset($actions[$action]))
                                                            <div class="form-check form-check-inline">
                                                                <input type="checkbox"
                                                                    class="form-check-input permission-checkbox"
                                                                    name="permissions[]"
                                                                    value="{{ $actions[$action]['name'] }}"
                                                                    id="perm_{{ $actions[$action]['id'] }}">
                                                                <label class="form-check-label text-capitalize"
                                                                    for="perm_{{ $actions[$action]['id'] }}">
                                                                    {{ $actions[$action]['action'] }}
                                                                </label>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>


                        <small class="text-danger" id="error-permissions"></small>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
@endsection

@section('page-script')
<script>
        $(document).ready(function() {
            $('#permissions').select2({
                dropdownParent: $('#roleForm')
            });
        });
    </script>
    <script>
        function selectAllPermissions() {
            $('.permission-checkbox').prop('checked', true);
        }

        function unselectAllPermissions() {
            $('.permission-checkbox').prop('checked', false);
        }
    </script>
    <script>
        function togglePermissions(roleId, button) {
            const rows = document.querySelectorAll('.more-perms-' + roleId);
            rows.forEach(el => el.classList.toggle('d-none'));

            const expanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(expanded));
            button.textContent = expanded ? 'Show Less' : 'Show More';
        }
    </script>
<script>
    let roleTable;

    $(function () {
        roleTable = $('#roles-table').DataTable({
            processing: true,
            serverSide: true,
            lengthChange: false,
            searching: false,
            ajax: "{{ route('configuration.roles.data') }}",
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name', name: 'name' },
                { data: 'permissions', name: 'permissions', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });
    });

    $('#roleFormSubmit').on('submit', function(e) {
        e.preventDefault();
        $('.is-invalid').removeClass('is-invalid');
        $('#error-name, #error-permissions').text('');

        let formData = new FormData(this);
        let id = $('#role_id').val();
        let url = id ? `/configuration/roles/${id}` : `{{ route('configuration.roles.store') }}`;
        if (id) formData.append('_method', 'PUT');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                wesCloseInlineForm('#roleForm');
                Swal.fire({
                    icon: 'success',
                    title: res.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                roleTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    if (xhr.responseJSON.errors.name) {
                        $('#name').addClass('is-invalid');
                        $('#error-name').text(xhr.responseJSON.errors.name[0]);
                    }
                }
            }
        });
    });

    function editRole(id) {
        $('#formTitle').text('Edit Role');
        $('#roleFormSubmit')[0].reset();
        $('#error-name').text('');
        $('#role_id').val(id);
        $('.permission-checkbox').prop('checked', false);

        $.get(`/configuration/roles/${id}/edit`, function(res) {
            $('#name').val(res.name);
            res.permissions.forEach(p => {
                $(`.permission-checkbox[value="${p.name}"]`).prop('checked', true);
            });
            wesOpenInlineForm('#roleForm');
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the role.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/roles/${id}`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        roleTable.ajax.reload(null, false);
                        Swal.fire({
                            icon: 'success',
                            title: res.message,
                            timer: 1000,
                            showConfirmButton: false
                        });
                    }
                });
            }
        });
    }

    function selectAllPermissions() {
        $('.permission-checkbox').prop('checked', true);
    }

    function unselectAllPermissions() {
        $('.permission-checkbox').prop('checked', false);
    }
</script>
@endsection
