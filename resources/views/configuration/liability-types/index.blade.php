@extends('layouts.layoutMaster')

@section('title', 'Liability Types')

@section('vendor-style')
@endsection

@section('content')
<div class="row g-3">
    {{-- Sidebar --}}
    <div class="col-lg-2">@include('layouts.menu.configuration-sidebar')</div>
    {{-- Main Content --}}
    <div class="col-lg-10 fb-content">
        {{-- Sticky Header --}}


        {{-- Table --}}
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
                        <li class="breadcrumb-item active">Liability Types</li>
                    </ol>
                </nav>
                <button class="btn btn-danger"
                    data-wes-form-target="#liabilityTypeForm"
                    onclick="resetLiabilityTypeForm()">
                    <i class="bx bx-plus me-1"></i> Add Liability Type
                </button>
            </div>
        </div>
                <h4 class="card-title mb-3">Liability Types</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table table-bordered table-hover text-center" id="liability-types-table" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name (EN)</th>
                                <th>Name (FA)</th>
                                <th>Name (PS)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- Inline Form --}}
            <div class="fb-inline-form-panel d-none" tabindex="-1" id="liabilityTypeForm">
                <div class="fb-inline-form-header">
                    <h5 id="formTitle">Add Liability Type</h5>
                    <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
                </div>
                <form id="liabilityTypeFormSubmit" class="fb-inline-form-body">
                    @csrf
                    <input type="hidden" name="id" id="liability_type_id">
                    <input type="hidden" name="_method" id="form_method" value="POST">

                    <div class="mb-3">
                        <label for="name_en" class="form-label">Name (EN)</label>
                        <input type="text" class="form-control" name="name_en" id="name_en" required>
                    </div>
                    <div class="mb-3">
                        <label for="name_fa" class="form-label">Name (FA)</label>
                        <input type="text" class="form-control" name="name_fa" id="name_fa">
                    </div>
                    <div class="mb-3">
                        <label for="name_ps" class="form-label">Name (PS)</label>
                        <input type="text" class="form-control" name="name_ps" id="name_ps">
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
    let liabilityTable;

    function resetLiabilityTypeForm() {
        $('#formTitle').text('Add Liability Type');
        $('#liabilityTypeFormSubmit')[0].reset();
        $('#liability_type_id').val('');
        $('#form_method').val('POST');
    }

    $(function () {
        liabilityTable = $('#liability-types-table').DataTable({
            processing: true,
            serverSide: true,
            lengthChange: false,
            searching: false,
            ajax: '{{ route("configuration.liability-types.data") }}',
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name_en', name: 'name_en' },
                { data: 'name_fa', name: 'name_fa' },
                { data: 'name_ps', name: 'name_ps' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });

        $('#liabilityTypeFormSubmit').on('submit', function (e) {
            e.preventDefault();
            const id = $('#liability_type_id').val();
            $('#form_method').val(id ? 'PUT' : 'POST');
            const formData = $(this).serialize();
            const url = id ? `/configuration/liability-types/${id}` : `{{ route('configuration.liability-types.store') }}`;

            $.ajax({
                url,
                type: 'POST',
                data: formData,
                success: res => {
                    wesCloseInlineForm('#liabilityTypeForm');
                    Swal.fire('Success', res.success, 'success');
                    liabilityTable.ajax.reload();
                    resetLiabilityTypeForm();
                },
                error: err => {
                    Swal.fire('Error', 'Validation failed.', 'error');
                }
            });
        });
    });

    function editLiabilityType(id) {
        $.get(`/configuration/liability-types/${id}/edit`, function (res) {
            $('#formTitle').text('Edit Liability Type');
            $('#liability_type_id').val(res.id);
            $('#name_en').val(res.name_en);
            $('#name_fa').val(res.name_fa);
            $('#name_ps').val(res.name_ps);
            $('#form_method').val('PUT');
            wesOpenInlineForm('#liabilityTypeForm');
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete this item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            confirmButtonColor: '#d33'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/liability-types/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: res => {
                        Swal.fire('Deleted', res.success, 'success');
                        liabilityTable.ajax.reload();
                    },
                    error: () => Swal.fire('Error', 'Delete failed.', 'error')
                });
            }
        });
    }
</script>
@endsection
