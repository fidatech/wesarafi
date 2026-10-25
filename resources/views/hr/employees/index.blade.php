@extends('layouts.layoutMaster')

@section('title', 'Employees')

@section('vendor-style')
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-2">@include('layouts.menu.hr-sidebar')</div>
    <div class="col-lg-10 fb-content">
        <div class="fb-header">
            <div class="d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                <i class="bx bx-home-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Employees</li>
                    </ol>
                </nav>

                <button class="btn btn-primary" data-wes-form-target="#employeeFormPanel" onclick="resetEmployeeForm()">
                    <i class="bx bx-user-plus me-1"></i> Add Employee
                </button>
            </div>
        </div>

        <div class="fb-main">
            <div class="row mb-3">
                <div class="col-md-3">
                    <select id="filter_branch" class="form-select">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filter_designation" class="form-select">
                        <option value="">All Designations</option>
                        @foreach($designations as $desig)
                            <option value="{{ $desig->id }}">{{ $desig->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filter_status" class="form-select">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="fb-table-wrapper">
                <h4 class="card-title mb-3">Employees List</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table-sm table-hover table-bordered table align-middle nowrap" id="employeesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>Designation</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fb-inline-form-panel d-none" tabindex="-1" id="employeeFormPanel">
    <div class="fb-inline-form-header">
        <h5 class="mb-0" id="employeeFormTitle">Employee Form</h5>
        <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
    </div>
    <div class="fb-inline-form-body">
        <form id="employeeForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" id="employee_id">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Father's Name</label>
                    <input type="text" name="father_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Salary</label>
                    <input type="number" name="salary" step="0.01" min="0" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Currency</label>
                    <select name="currency_id" class="form-select">
                        <option value="">Select Currency</option>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">National ID <small class="text-muted">(Tazkira/Passport)</small></label>
                    <input type="text" name="national_id" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">TIN Number</label>
                    <input type="text" name="tin_number" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select">
                        <option value="">Select Branch</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Designation</label>
                    <select name="designation_id" class="form-select">
                        <option value="">Select Designation</option>
                        @foreach($designations as $desig)
                            <option value="{{ $desig->id }}">{{ $desig->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Profile Picture</label>
                    <input type="file" name="profile_picture" class="form-control" onchange="previewImage(event)" accept="image/*">
                    <img id="profilePreview" src="{{ asset('uploads/no_image.jpg') }}" class="rounded mt-2" width="70">
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i> Save Employee
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('vendor-script')

@endsection

@section('page-script')
<script>
function previewImage(event) {
    const output = document.getElementById('profilePreview');
    output.src = URL.createObjectURL(event.target.files[0]);
    output.onload = () => URL.revokeObjectURL(output.src);
}

function resetEmployeeForm() {
    const form = document.getElementById('employeeForm');
    form.reset();
    $('#employee_id').val('');
    $('#employeeFormTitle').text('Add Employee');
    $('#profilePreview').attr('src', '{{ asset('uploads/no_image.jpg') }}');
    $(form).find('select').trigger('change');
}

window.editEmployee = function (id) {
    $('#employeeFormTitle').text('Edit Employee');
    $.get(`/hr/employees/${id}/edit`, function (data) {
        for (const key in data) {
            const el = $(`[name="${key}"]`);
            if (!el.length || el.attr('type') === 'file') continue;

            if (key === 'joining_date' && data[key]) {
                const formatted = new Date(data[key]).toISOString().split('T')[0];
                el.val(formatted);
                continue;
            }

            if (key === 'is_active') {
                el.val(data[key] ? '1' : '0').trigger('change');
                continue;
            }

            el.val(data[key]);
        }

        const preview = data.profile_picture ? `/storage/${data.profile_picture}` : '{{ asset('uploads/no_image.jpg') }}';
        $('#profilePreview').attr('src', preview);
        wesOpenInlineForm('#employeeFormPanel');
    });
}

$('#employeeForm').on('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const id = $('#employee_id').val();
    const method = id ? 'POST' : 'POST';
    const url = id ? `/hr/employees/${id}` : `/hr/employees`;
    if (id) formData.append('_method', 'PUT');

    $.ajax({
        url,
        type: method,
        data: formData,
        processData: false,
        contentType: false,
        success: function () {
            Swal.fire('Success!', 'Employee has been saved.', 'success');
            $('#employeesTable').DataTable().ajax.reload();
            document.getElementById('employeeForm').reset();
            document.getElementById('profilePreview').src = '{{ asset('uploads/no_image.jpg') }}';
            wesCloseInlineForm('#employeeFormPanel');
            $('#employeeFormTitle').text('Add Employee');
            $('#employeesTable').DataTable().ajax.reload();
        },
        error: function (xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong.', 'error');
        }
    });
});

window.deleteEmployee = function (id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the employee!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/hr/employees/${id}`,
                type: 'DELETE',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function () {
                    Swal.fire('Deleted!', 'Employee has been removed.', 'success');
                    $('#employeesTable').DataTable().ajax.reload();
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong.', 'error');
                }
            });
        }
    });
}

$(function () {
    const table = $('#employeesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('hr.employees.data') }}',
            data: function (d) {
                d.branch_id = $('#filter_branch').val();
                d.designation_id = $('#filter_designation').val();
                d.is_active = $('#filter_status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            {
                data: 'profile_picture',
                render: d => d === 'uploads/no_image.jpg' ? `<img src="{{ asset('uploads/no_image.jpg') }}" class="rounded" width="40" height="40" loading="lazy" alt="">` : `<img src="/${d}" class="rounded" width="40" height="40" loading="lazy" alt="">`,
                orderable: false
            },
            { data: 'name' },
            { data: 'phone' },
            { data: 'email' },
            { data: 'branch' },
            { data: 'designation' },
            {
                data: 'is_active',
                render: d => d ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>',
                orderable: false
            },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });

    $('#filter_branch, #filter_designation, #filter_status').on('change', function () {
        table.ajax.reload();
    });
});
</script>
@endsection
