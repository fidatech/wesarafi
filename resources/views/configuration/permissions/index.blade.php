@extends('layouts.layoutMaster')

@section('title', 'Permissions')

@section('content')
@php
    $grouped = [];

    foreach ($permissions as $permission) {
        $parts = explode(' ', strtolower($permission->name));
        if (count($parts) < 2) continue;

        $action = $parts[0];
        $entity = strtoupper($parts[1]);

        $grouped[$entity][$action] = [
            'id' => $permission->id,
            'name' => $permission->name,
            'action' => ucfirst($action),
        ];
    }

    $allActions = collect($grouped)->flatMap(fn($actions) => array_keys($actions))->unique()->values();
    $actionOrder = $allActions->sort()->toArray();
@endphp

<div class="row g-3">
    {{-- Sidebar --}}
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
    {{-- Main Content --}}
    <div class="col-lg-10 fb-content">
        {{-- Sticky Header --}}


        {{-- Permissions Grouped Cards --}}
        <div class="fb-main">
            <div class="fb-table-wrapper">
                <div class="fb-header">
            <div class="d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                <i class="bx bx-home-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Permissions</li>
                    </ol>
                </nav>
                <button class="btn btn-primary"
                    data-wes-form-target="#permissionForm"
                    onclick="$('#permissionFormSubmit')[0].reset(); $('#permission_id').val(''); $('#permTitle').text('Add Permission'); $('#perm_name').removeClass('is-invalid'); $('#error-name').text('');">
                    <i class="bx bx-plus me-1"></i> Add Permission
                </button>
            </div>
        </div>
                <h4 class="card-title mb-3">Permissions</h4>
                <div class="row g-3 mt-1">
                    @foreach ($grouped as $entity => $actions)
                        <div class="col-md-4">
                            <div class="border rounded-3 h-100 p-3 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-semibold text-uppercase">{{ $entity }}</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($actionOrder as $action)
                                        @if (isset($actions[$action]))
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button"
                                                    class="btn btn-sm @if ($action === 'view') bg-primary
                                                    @elseif($action === 'create') bg-success
                                                    @elseif($action === 'edit') bg-warning
                                                    @elseif($action === 'delete') bg-danger
                                                    @elseif($action === 'approve') bg-info
                                                    @elseif($action === 'reject') bg-secondary
                                                    @else bg-dark @endif text-white"
                                                    onclick="editPermission({{ $actions[$action]['id'] }})"
                                                    aria-label="Edit {{ $actions[$action]['name'] }}" title="Edit {{ $actions[$action]['action'] }}">
                                                    <i class="bx bx-edit-alt"></i> {{ $actions[$action]['action'] }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete({{ $actions[$action]['id'] }})" aria-label="Delete {{ $actions[$action]['name'] }}" title="Delete {{ $actions[$action]['action'] }}">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Inline Form --}}
            <div class="fb-inline-form-panel d-none" tabindex="-1" id="permissionForm">
                <div class="fb-inline-form-header">
                    <h5 id="permTitle">Add Permission</h5>
                    <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
                </div>
                <form id="permissionFormSubmit" class="fb-inline-form-body">
                    @csrf
                    <input type="hidden" id="permission_id" name="id">

                    <div class="mb-3">
                        <label for="perm_name" class="form-label">Permission Name</label>
                        <input type="text" name="name" id="perm_name" class="form-control" placeholder="e.g. view users" required>
                        <small class="text-danger" id="error-name"></small>
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
    $('#permissionFormSubmit').on('submit', function(e) {
        e.preventDefault();
        const id = $('#permission_id').val();
        const formData = $(this).serialize();
        const url = id ? `/configuration/permissions/${id}` : `{{ route('configuration.permissions.store') }}`;
        const method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: formData,
            success: function(res) {
                $('#permissionFormSubmit')[0].reset();
                wesCloseInlineForm('#permissionForm');
                Swal.fire({
                    icon: 'success',
                    title: res.success,
                    timer: 1500,
                    showConfirmButton: false
                });
                location.reload();
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    $('#perm_name').addClass('is-invalid');
                    $('#error-name').text(xhr.responseJSON.errors.name[0]);
                }
            }
        });
    });

    function editPermission(id) {
        $('#permTitle').text('Edit Permission');
        $('#permissionFormSubmit')[0].reset();
        $('#error-name').text('');
        $('#perm_name').removeClass('is-invalid');

        $.get(`/configuration/permissions/${id}/edit`, function(res) {
            $('#permission_id').val(res.id);
            $('#perm_name').val(res.name);
            wesOpenInlineForm('#permissionForm');
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete this permission?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/permissions/${id}`,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        Swal.fire('Deleted!', res.success, 'success');
                        location.reload();
                    },
                    error: function() {
                        Swal.fire('Error', 'Could not delete permission.', 'error');
                    }
                });
            }
        });
    }
</script>
@endsection
