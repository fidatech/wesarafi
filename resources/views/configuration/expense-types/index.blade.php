@extends('layouts.layoutMaster')

@section('title', 'Expense Types')

@section('vendor-style')
@endsection

@section('page-style')
<style>
    #expenseTypeForm { max-width: 1040px; flex: 0 0 auto; }
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
                        <li class="breadcrumb-item active">Expense Types</li>
                    </ol>
                </nav>
                <button class="btn btn-danger" data-wes-form-target="#expenseTypeForm"
                    onclick="resetExpenseTypeForm()">
                    <i class="bx bx-plus me-1"></i> Add Expense Type
                </button>
            </div>
        </div>
                <h4 class="card-title mb-3">Expense Types</h4>
                <div class="table-responsive wes-institutional-table">
                    <table class="table table-bordered table-hover text-center" id="expense-types-table"
                        style="width: 100%;">
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
            <div class="fb-inline-form-panel d-none" tabindex="-1" id="expenseTypeForm">
                <div class="fb-inline-form-header">
                    <h5 class="mb-0" id="formTitle">Add Expense Type</h5>
                    <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
                </div>
                <form id="expenseTypeFormSubmit" class="fb-inline-form-body">
                    @csrf
                    <input type="hidden" name="id" id="expense_type_id">
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
    let expenseTable;

    function resetExpenseTypeForm() {
        $('#formTitle').text('Add Expense Type');
        $('#expenseTypeFormSubmit')[0].reset();
        $('#expense_type_id').val('');
        $('#form_method').val('POST');
    }

    function editExpenseType(id) {
        $.get(`/configuration/expense-types/${id}/edit`, function (res) {
            $('#formTitle').text('Edit Expense Type');
            $('#expense_type_id').val(res.id);
            $('#name_en').val(res.name_en);
            $('#name_fa').val(res.name_fa);
            $('#name_ps').val(res.name_ps);
            $('#form_method').val('PUT');
            wesOpenInlineForm('#expenseTypeForm');
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete this expense type?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            confirmButtonColor: '#d33'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/configuration/expense-types/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: res => {
                        Swal.fire('Deleted', res.success, 'success');
                        expenseTable.ajax.reload();
                    },
                    error: () => Swal.fire('Error', 'Delete failed.', 'error')
                });
            }
        });
    }

    $(function () {
        expenseTable = $('#expense-types-table').DataTable({
            processing: true,
            serverSide: true,
            lengthChange: false,
            searching: false,
            ajax: '{{ route("configuration.expense-types.data") }}',
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name_en', name: 'name_en' },
                { data: 'name_fa', name: 'name_fa' },
                { data: 'name_ps', name: 'name_ps' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false },
            ]
        });

        $('#expenseTypeFormSubmit').on('submit', function (e) {
            e.preventDefault();
            const id = $('#expense_type_id').val();
            const method = id ? 'PUT' : 'POST';
            const url = id ? `/configuration/expense-types/${id}` : `{{ route('configuration.expense-types.store') }}`;

            $('#form_method').val(method);
            const formData = $(this).serialize();

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: res => {
                    wesCloseInlineForm('#expenseTypeForm');
                    Swal.fire('Success', res.success, 'success');
                    expenseTable.ajax.reload();
                    resetExpenseTypeForm();
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
