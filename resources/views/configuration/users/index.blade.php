@extends('layouts.layoutMaster')

@section('title', 'Users')

@section('vendor-style')
@endsection

@section('page-style')
<style>
    #userForm { max-width: 1040px; flex: 0 0 auto; }
</style>
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
                            <li class="breadcrumb-item active">Users</li>
                        </ol>
                    </nav>
                    <button class="btn btn-primary"
                        data-wes-form-target="#userForm"
                        onclick="$('#formTitle').text('Add User'); $('#userFormSubmit')[0].reset(); $('#user_id').val(''); $('#previewImage').attr('src', '{{ asset("uploads/no_image.jpg") }}'); $('.password-block').show(); $('#userFormSubmit select').trigger('change'); $('.is-invalid').removeClass('is-invalid'); $('.invalid-feedback').text('');">
                        <i class="bx bx-plus me-1"></i> Add User
                    </button>
                </div>
            </div>

            {{-- Scrollable Table Area --}}
            <div class="fb-main">
                {{-- Table + Pagination --}}
                <div class="fb-table-wrapper">
                    <h4 class="card-title mb-3">Users</h4>

                    <div class="card-body pb-0">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bx bx-filter"></i></span>
                                    <select id="filterRole" class="form-select">
                                        <option value="">All Roles</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bx bx-toggle-left"></i></span>
                                    <select id="filterStatus" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                                    <input type="text" id="searchBox" class="form-control" placeholder="Search name, email...">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive wes-institutional-table">
                            <table class="table-hover table-bordered table align-middle" id="users-table" style="width: 100%;">
                                <thead class="table-light sticky-top text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Branch</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>

                @include('configuration.users.form')
            </div>
        </div>
    </div>

@endsection
@section('vendor-script')
@endsection
@section('page-script')
<script>
    let userTable;

    $(function () {
        userTable = $('#users-table').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            lengthChange: false,
            pageLength: 10,
            ajax: {
                url: "{{ route('configuration.users.data') }}",
                data: function (d) {
                    d.search = {
                        value: $('#searchBox').val()
                    };
                    d.role = $('#filterRole').val();
                    d.status = $('#filterStatus').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', className: 'text-center', orderable: false, searchable: false },
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'username', name: 'username' },
                { data: 'role', name: 'role' },
                { data: 'status', name: 'status', className: 'text-center' },
                { data: 'branch', name: 'branch' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
            ]
        });

        $('#filterRole, #filterStatus').on('change', function () {
            userTable.ajax.reload();
        });

        // Avoid a server request for every keystroke on large user directories.
        let searchTimer;
        $('#searchBox').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => userTable.ajax.reload(), 300);
        });
    });

    function editUser(id) {
        $('#formTitle').text('Edit User');
        $('#userFormSubmit')[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
        $('.password-block').hide();

        $.get(`/configuration/users/${id}/edit`, function (res) {
            $('#user_id').val(res.id);
            $('#name').val(res.name);
            $('#email').val(res.email);
            $('#username').val(res.username);
            $('#branch_id').val(res.branch_id);
            $('#status').val(res.status);
            $('#role').val(res.roles[0].name);
            $('#previewImage').attr('src', res.image ? `/storage/${res.image}` : '{{ asset("uploads/no_image.jpg") }}');
            wesOpenInlineForm('#userForm');
        });
    }

    $('#userFormSubmit').on('submit', function (e) {
        e.preventDefault();
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        let formData = new FormData(this);
        let id = $('#user_id').val();
        let url = id ? `/configuration/users/${id}` : `{{ route('configuration.users.store') }}`;
        let method = id ? 'POST' : 'POST';

        if (id) {
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                wesCloseInlineForm('#userForm');

                Swal.fire({
                    icon: 'success',
                    title: res.message,
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'swal2-shadow'
                    }
                }).then(() => {
                    userTable.ajax.reload(null, false);
                });
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    console.log(xhr.responseJSON);
                    let errors = xhr.responseJSON.errors;
                    for (let field in errors) {
                        $(`#${field}`).addClass('is-invalid');
                        $(`#error-${field}`).text(errors[field][0]);
                    }
                }
            }
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/users/${id}`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: function (res) {
                        userTable.ajax.reload(null, false);
                        Swal.fire({
                            icon: 'success',
                            title: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    },
                    error: function () {
                        Swal.fire('Error', 'Failed to delete user.', 'error');
                    }
                });
            }
        });
    }
</script>
@endsection
