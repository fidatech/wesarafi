@extends('layouts.layoutMaster')

@section('title', 'Income Sources')

@section('vendor-style')
@endsection

@section('page-style')
<style>
    #incomeSourceForm { max-width: 1040px; flex: 0 0 auto; }
</style>
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
                        <li class="breadcrumb-item active">Income Sources</li>
                    </ol>
                </nav>
                <button class="btn btn-primary"
                    data-wes-form-target="#incomeSourceForm"
                    onclick="resetIncomeSourceForm()">
                    <i class="bx bx-plus me-1"></i> Add Income Source
                </button>
            </div>
        </div>
                <h4 class="card-title mb-3">Income Sources</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table table-bordered table-hover text-center" id="income-sources-table" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name (EN)</th>
                                <th>Name (FA)</th>
                                <th>Name (PS)</th>
                                <th>Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- Inline Form --}}
            <div class="fb-inline-form-panel d-none" tabindex="-1" id="incomeSourceForm">
                <div class="fb-inline-form-header">
                    <h5 class="mb-0" id="formTitle">Add Income Source</h5>
                    <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
                </div>
                <form id="incomeSourceFormSubmit" class="fb-inline-form-body">
                    @csrf
                    <input type="hidden" name="id" id="income_source_id">
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
                            <label for="level" class="form-label">Level</label>
                            <select class="form-select" name="level" id="level" required>
                                <option value="">Select Level</option>
                                <option value="1">Level 1</option>
                                <option value="2">Level 2</option>
                                <option value="3">Level 3</option>
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
</div>
@endsection
@section('vendor-script')
@endsection

@section('page-script')
<script>
    let incomeTable;

    function resetIncomeSourceForm() {
        $('#formTitle').text('Add Income Source');
        $('#incomeSourceFormSubmit')[0].reset();
        $('#income_source_id').val('');
        $('#form_method').val('POST');
    }

    function editIncomeSource(id) {
        $.get(`/configuration/income-sources/${id}/edit`, function (res) {
            $('#formTitle').text('Edit Income Source');
            $('#income_source_id').val(res.id);
            $('#name_en').val(res.name_en);
            $('#name_fa').val(res.name_fa);
            $('#name_ps').val(res.name_ps);
            $('#level').val(res.level);
            $('#form_method').val('PUT');
            wesOpenInlineForm('#incomeSourceForm');
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete this income source?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/income-sources/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: res => {
                        Swal.fire('Deleted', res.success, 'success');
                        incomeTable.ajax.reload();
                    },
                    error: () => Swal.fire('Error', 'Delete failed.', 'error')
                });
            }
        });
    }

    $(function () {
        incomeTable = $('#income-sources-table').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            lengthChange: false,
            ajax: '{{ route("configuration.income-sources.data") }}',
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name_en', name: 'name_en' },
                { data: 'name_fa', name: 'name_fa' },
                { data: 'name_ps', name: 'name_ps' },
                { data: 'level', name: 'level' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });

        $('#incomeSourceFormSubmit').on('submit', function (e) {
            e.preventDefault();
            const id = $('#income_source_id').val();
            const url = id ? `/configuration/income-sources/${id}` : `{{ route('configuration.income-sources.store') }}`;
            const formData = $(this).serialize();

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: res => {
                    wesCloseInlineForm('#incomeSourceForm');
                    Swal.fire('Success', res.success, 'success');
                    incomeTable.ajax.reload();
                    resetIncomeSourceForm();
                },
                error: err => {
                    console.error(err);
                    Swal.fire('Error', 'Validation failed.', 'error');
                }
            });
        });
    });
</script>
@endsection
