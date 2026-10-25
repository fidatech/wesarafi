@extends('layouts.layoutMaster')

@section('title', 'Assets')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.accounting-sidebar')</div>
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
                            <li class="breadcrumb-item active">Assets</li>
                        </ol>
                    </nav>
                    <button class="btn btn-primary" id="toggleCreate">
                        <i class="bx bx-plus me-1"></i> Add Asset
                    </button>
                </div>
            </div>

            <div class="fb-main">
                <div class="card mb-4" id="createCard" style="display: none;">
                    <div class="card-header text-dark py-2">
                        <h4 class="mb-0"><i class="bx bx-plus-circle me-2"></i> Add Asset</h4>
                    </div>
                    <div class="card-body">
                        <form id="assetForm" method="POST">
                            @csrf
                            <input type="hidden" name="id" id="asset_id">
                            <input type="hidden" name="_method" id="form_method" value="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Asset Category</label>
                                    <select name="asset_type" id="asset_type" class="form-select" required>
                                        <option value="">Select Tangible or Intangible</option>
                                        @foreach($assetTypes as $type)
                                            <option value="{{ $type->id }}" data-code="{{ $type->code }}">{{ $type->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Asset Account</label>
                                    <select name="chart_of_account_id" id="chart_of_account_id" class="form-select" required>
                                        <option value="">Select Asset Account</option>
                                        @foreach($assetAccounts as $account)
                                            <option value="{{ $account->id }}" data-category="{{ str_starts_with($account->code, '101') ? '1100' : '1200' }}">
                                                {{ $account->code }} — {{ $account->account_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Asset Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label>Currency</label>
                                    <select name="currency_id" class="form-select" required>
                                        @foreach($currencies as $curr)
                                            <option value="{{ $curr->id }}">{{ $curr->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Amount</label>
                                    <input type="number" name="amount" step="0.01" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label>Date</label>
                                    <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label>Description</label>
                                    <input type="text" name="description" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" id="toggleUsefulLife"> Add Useful Life (Years)
                                    </label>
                                    <input type="number" step="1" name="useful_life_years" id="usefulLifeInput" class="form-control mt-2" style="display: none;">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="bx bx-save me-2"></i> Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="fb-table-wrapper" id="tableCard">
                    <h4 class="card-title mb-3">Asset Entries</h4>
                    <div class="table-responsive wes-institutional-table">
                        <table class="table-sm table-hover table-bordered nowrap table align-middle" id="assetTable">
                            <thead class="text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Branch</th>
                                    <th>Date</th>
                                    <th>Useful Life</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('vendor-script')
@endsection

@section('page-script')
<script>
    function filterAssetAccounts(selectedAccount = null) {
        const categoryCode = $('#asset_type option:selected').data('code');
        $('#chart_of_account_id option').each(function () {
            const optionCategory = $(this).data('category');
            const visible = !optionCategory || !categoryCode || optionCategory === categoryCode;
            $(this).prop('hidden', !visible).prop('disabled', !visible);
        });

        if (selectedAccount) {
            $('#chart_of_account_id').val(String(selectedAccount));
        } else if ($('#chart_of_account_id option:selected').prop('disabled')) {
            $('#chart_of_account_id').val('');
        }
    }

    $(document).ready(function () {
        $('#toggleUsefulLife').on('change', function () {
            $('#usefulLifeInput').toggle(this.checked);
        });

        $('#asset_type').on('change', function () {
            filterAssetAccounts();
        });

        filterAssetAccounts();
    });

    $('#toggleCreate').on('click', function () {
    $('#createCard').slideToggle();
    $('#tableCard').toggle();
    $('#assetForm')[0].reset();
    $('#asset_id').val('');
    $('#form_method').val('POST');
});

</script>
<script>
let isSubmitting = false;

const table = $('#assetTable').DataTable({
    ajax: '{{ route('accounting.assets.data') }}',
    columns: [
    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
    { data: 'asset_type', name: 'asset_type' },
    { data: 'name', name: 'name' },
    { data: 'amount', name: 'amount', className: 'text-end' },
    { data: 'currency_code', name: 'currency.code' },
    { data: 'branch_name', name: 'branch.name' },
    { data: 'date', name: 'date' },
    { data: 'useful_life_years', name: 'useful_life_years', className: 'text-center' },
    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' },
],
});

$(document).on('click', '#toggleUsefulLife', function() {
    $('#usefulLifeField').toggleClass('d-none');
});

// Add/Edit logic
$(document).on('submit', '#assetForm', function(e) {
    e.preventDefault();
    if (isSubmitting) return;
    isSubmitting = true;
    const form = $(this);
    const id = $('#asset_id').val();
    const url = id ? `/accounting/assets/${id}` : `{{ route('accounting.assets.store') }}`;
    const method = id ? 'PUT' : 'POST';

    $.ajax({
        url, method,
        data: form.serialize(),
        success: res => {
            Swal.fire({ icon: 'success', title: res.message, timer: 1000 });
            table.ajax.reload();
            $('#assetForm')[0].reset();
            $('#form_method').val('POST');
            $('#asset_id').val('');
            $('#toggleUsefulLife').prop('checked', false);
            $('#usefulLifeInput').hide();
            $('#createCard').slideUp();
            $('#tableCard').show();
        },
        error: err => {
            const errors = err.responseJSON?.errors || {};
            const message = Object.values(errors).flat().join('\n');
            Swal.fire({ icon: 'error', title: message });
            console.log(errors + '\n' + message);

        },
        complete: () => isSubmitting = false
    });
});

// Edit
$(document).on('click', '.editAsset', function () {
    const id = $(this).data('id');
    $.get(`/accounting/assets/${id}`, function (asset) {
        $('#asset_id').val(asset.id);
        $('#form_method').val('PUT');
        $('[name="asset_type"]').val(asset.asset_type);
        filterAssetAccounts(asset.chart_of_account_id);
        $('[name="name"]').val(asset.name);
        $('[name="currency_id"]').val(asset.currency_id);
        $('[name="amount"]').val(asset.amount);
        $('[name="date"]').val(asset.date);
        $('[name="description"]').val(asset.description);

        if (asset.useful_life_years) {
            $('#toggleUsefulLife').prop('checked', true);
            $('#usefulLifeInput').val(asset.useful_life_years).show();
        } else {
            $('#toggleUsefulLife').prop('checked', false);
            $('#usefulLifeInput').hide();
        }

        // Show form, hide table
        $('#tableCard').hide();
        $('#createCard').slideDown();
    });
});


// Delete
$(document).on('click', '.deleteAsset', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Are you sure?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!'
    }).then(result => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/accounting/assets/${id}`,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: res => {
                    Swal.fire({ icon: 'success', title: res.message, timer: 1000 });
                    table.ajax.reload();
                },
                error: err => {
                    Swal.fire({ icon: 'error', title: err.responseJSON?.message || 'Error' });
                }
            });
        }
    });
});
</script>
@endsection
