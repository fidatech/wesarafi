@extends('layouts.layoutMaster')

@section('title', 'Designations')

@section('vendor-style')
@endsection

@section('page-style')
<style>
    #designationForm { max-width: 1040px; flex: 0 0 auto; }
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
                        <li class="breadcrumb-item active">Designations</li>
                    </ol>
                </nav>
                <button class="btn btn-primary"
                    data-wes-form-target="#designationForm"
                    onclick="resetDesignationForm()">
                    <i class="bx bx-plus me-1"></i> Add Designation
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="fb-main">
            <div class="fb-table-wrapper">
                <h4 class="card-title mb-3">Designations</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table table-bordered table-hover text-center" id="designations-table" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name (EN)</th>
                                <th>Name (FA)</th>
                                <th>Name (PS)</th>
                                <th>Line Manager</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        {{-- Inline Form --}}
        <div class="fb-inline-form-panel d-none" tabindex="-1" id="designationForm">
            <div class="fb-inline-form-header">
                <h5 class="mb-0" id="formTitle">Add Designation</h5>
                <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
            </div>
            <form id="designationFormSubmit" class="fb-inline-form-body">
                @csrf
                <input type="hidden" name="id" id="designation_id">
                <input type="hidden" name="_method" id="form_method" value="POST">

                <div class="row g-3">
                    <div class="col-sm-6 col-lg-4">
                        <label for="name_en" class="form-label">Name (EN)</label>
                        <input type="text" class="form-control" name="name_en" id="name_en" required>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <label for="name_fa" class="form-label">Name (FA)</label>
                        <input type="text" class="form-control" name="name_fa" id="name_fa">
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <label for="name_ps" class="form-label">Name (PS)</label>
                        <input type="text" class="form-control" name="name_ps" id="name_ps">
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <label for="line_manager" class="form-label">Line Manager</label>
                        <select class="form-select" name="line_manager" id="line_manager">
                            <option value="">-- No Line Manager --</option>
                            @foreach ($lineManagers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top mt-3 pt-2">
                    <button type="button" class="btn btn-outline-secondary" data-wes-form-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@section('vendor-script')
@endsection

@section('page-script')
<script>
    let designationTable;

    function resetDesignationForm() {
        $('#formTitle').text('Add Designation');
        $('#designationFormSubmit')[0].reset();
        $('#designation_id').val('');
    }

    $(function () {
        designationTable = $('#designations-table').DataTable({
            processing: true,
            serverSide: true,
            lengthChange: false,
            searching: false,
            ajax: '{{ route("configuration.designations.data") }}',
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name_en', name: 'name_en' },
                { data: 'name_fa', name: 'name_fa' },
                { data: 'name_ps', name: 'name_ps' },
                {
                    data: 'line_manager_name',
                    name: 'line_manager_name',
                    render: (data, type, row) => {
                        const colors = [
                            'secondary',
                            'primary',
                            'success',
                            'danger',
                            'warning',
                            'info',
                            'dark',
                        ];
                        const lineManagerNames = designationTable
                            .data()
                            .pluck('line_manager_name')
                            .toArray();
                        const nameIndex = lineManagerNames.indexOf(data);
                        const color = nameIndex === -1 ? 'light' : colors[nameIndex % colors.length];
                        return `<span class="badge rounded-pill bg-${color}">${data}</span>`;
                    }
                },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });
    });

    $(function () {
        $('#designationFormSubmit').on('submit', function (e) {
            e.preventDefault();
            const id = $('#designation_id').val();
            $('#form_method').val(id ? 'PUT' : 'POST');

            const formData = $(this).serialize();
            const url = id ? `/configuration/designations/${id}` : `{{ route('configuration.designations.store') }}`;

            $.ajax({
                url: url,
                type: 'POST', // always POST, Laravel reads _method=PUT internally
                data: formData,
                success: res => {
                    wesCloseInlineForm('#designationForm');
                    Swal.fire('Success', res.success, 'success');
                    designationTable.ajax.reload();
                    $('#designationFormSubmit')[0].reset();
                    $('#designation_id').val('');
                    $('#formTitle').text('Add Designation');
                },
                error: err => {
                    console.error(err);
                    Swal.fire('Error', 'Validation failed.', 'error');
                }
            });
        });

    });

    // Edit designation
    function editDesignation(id) {
        $.get(`/configuration/designations/${id}/edit`, function (res) {
            $('#formTitle').text('Edit Designation');
            $('#designation_id').val(res.id);
            $('#name_en').val(res.name_en);
            $('#name_fa').val(res.name_fa);
            $('#name_ps').val(res.name_ps);
            $('#line_manager').val(res.line_manager).trigger('change');
            wesOpenInlineForm('#designationForm');
        });
    }


    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete this designation?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/designations/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: res => {
                        Swal.fire('Deleted', res.success, 'success');
                        designationTable.ajax.reload();
                    },
                    error: () => Swal.fire('Error', 'Delete failed.', 'error')
                });
            }
        });
    }
</script>
@endsection
