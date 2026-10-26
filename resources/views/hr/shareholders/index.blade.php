@extends('layouts.layoutMaster')

@section('title', 'Shareholders')

@section('vendor-style')
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-2">@include('layouts.menu.hr-sidebar')</div>
    <div class="col-lg-10 fb-content">


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
                        <li class="breadcrumb-item active">Shareholders</li>
                    </ol>
                </nav>

                <button class="btn btn-primary" data-wes-form-target="#shareholderFormPanel" onclick="resetShareholderForm()">
                    <i class="bx bx-user-plus me-1"></i> Add Shareholder
                </button>
            </div>
        </div>
                <h4 class="card-title mb-3">Shareholders List</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table-sm table-hover table-bordered table align-middle nowrap" id="shareholdersTable">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Share %</th>
                                <th>Joining Date</th>
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

<div class="fb-inline-form-panel d-none" tabindex="-1" id="shareholderFormPanel">
    <div class="fb-inline-form-header">
        <h5 class="mb-0">Shareholder Form</h5>
        <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
    </div>
    <div class="fb-inline-form-body">
        <form id="shareholderForm" action="{{ route('hr.shareholders.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" id="shareholder_id">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label small">Father's Name</label>
                    <input type="text" name="father_name" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small">Address</label>
                    <textarea name="address" class="form-control" rows="1"></textarea>
                </div>
                <div class="col-6">
                    <label class="form-label small">Share %</label>
                    <input type="number" step="0.01" max="100" min="1" name="share_percentage" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label small">National ID</label>
                    <input type="text" name="national_id" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label small">Profile Picture</label>
                    <input type="file" name="profile_picture" class="form-control" onchange="previewImage(event)" accept="image/*">
                    <img id="profilePreview" src="{{ asset('uploads/no_image.jpg') }}" class="mt-2 rounded" width="50">
                </div>
                <div class="col-12">
                    <label class="form-label small">Status</label>
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="bx bx-save me-2"></i> Save Shareholder
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
$(function () {
    const table = $('#shareholdersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route('hr.shareholders.data') }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'profile_picture', render: d => `<img src="${d ? '/' + String(d).replace(/^\/+/, '') : '{{ asset('uploads/no_image.jpg') }}'}" class="rounded" width="50" height="50" loading="lazy" alt="">`, orderable: false },
            { data: 'name' },
            { data: 'phone' },
            { data: 'email' },
            { data: 'share_percentage' },
            { data: 'joining_date', render: d => new Date(d).toLocaleDateString('en-GB') },
            {
                data: 'is_active',
                render: d => d ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'
            },
            {
                data: 'actions',
                orderable: false,
                searchable: false
            }
        ]
    });

    window.resetShareholderForm = function () {
        const form = document.getElementById('shareholderForm');
        form.reset();
        $('#shareholder_id').val('');
        $('#profilePreview').attr('src', '{{ asset('uploads/no_image.jpg') }}');
        $(form).find('select').trigger('change');
    };

    window.editShareholder = function (id) {
        $.get(`/hr/shareholders/${id}/edit`, function (data) {
            for (const key in data) {
                const el = $(`[name="${key}"]`);
                if (!el.length || el.attr('type') === 'file') continue;

                if (key === 'joining_date' && data[key]) {
                    const formattedDate = new Date(data[key]).toISOString().split('T')[0];
                    el.val(formattedDate);
                    continue;
                }

                if (key === 'is_active') {
                    el.val(data[key] ? '1' : '0').trigger('change');
                    continue;
                }

                if (el.is('select')) {
                    el.val(data[key]).trigger('change');
                } else {
                    el.val(data[key]);
                }
            }

            const imagePath = data.profile_picture ? `/${data.profile_picture}` : '{{ asset('uploads/no_image.jpg') }}';
            $('#profilePreview').attr('src', imagePath);

            wesOpenInlineForm('#shareholderFormPanel');
        });
    }

    window.deleteShareholder = function (id) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'You won\'t be able to revert this!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(`/hr/shareholders/${id}`, {
                    _method: 'DELETE',
                    _token: $('meta[name="csrf-token"]').attr('content')
                }, function () {
                    Swal.fire('Deleted!', 'Shareholder has been deleted.', 'success');
                    $('#shareholdersTable').DataTable().ajax.reload();
                }).fail(function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong.', 'error');
                });
            }
        });
    }



    $('#shareholderForm').on('submit', function (e) {
        e.preventDefault();
        let formData = new FormData(this);
        let $btn = $('#submitBtn');

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function () {
                Swal.fire({ icon: 'success', title: 'Saved!', timer: 1500, showConfirmButton: false });
                $('#shareholderForm')[0].reset();
                $('#profilePreview').attr('src', '{{ asset('uploads/no_image.jpg') }}');
                table.ajax.reload();
                wesCloseInlineForm('#shareholderFormPanel');
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON.message });
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-2"></i> Save Shareholder');
            }
        });
    });
});

function previewImage(event) {
    const output = document.getElementById('profilePreview');
    output.src = URL.createObjectURL(event.target.files[0]);
    output.onload = function () {
        URL.revokeObjectURL(output.src);
    }
}
</script>
@endsection
