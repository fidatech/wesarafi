@extends('layouts.layoutMaster')

@section('title', 'Depreciation')

@section('vendor-style')
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-2">@include('layouts.menu.accounting-sidebar')</div>
        <div class="col-lg-10 fb-content">


            <div class="fb-main">
                <div class="card">
                <div class="fb-header">
                <div class="d-flex justify-content-between align-items-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}" class="text-primary text-decoration-none">
                                    <i class="bx bx-home-alt me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Depreciation</li>
                        </ol>
                    </nav>
                    <button class="btn btn-primary" data-wes-form-target="#depreciationFormPanel" onclick="resetDepreciationForm()">
                        <i class="bx bx-plus me-1"></i> Add Depreciation
                    </button>
                </div>
            </div>
                    <div class="card-body">
                        <h4 class="card-title mb-3">Depreciation</h4>
                        <div class="table-responsive wes-institutional-table">
                            <table class="table-sm table-hover table-bordered nowrap table align-middle" id="depreciationTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Asset</th>
                                        <th>Amount</th>
                                        <th>Currency</th>
                                        <th>Branch</th>
                                        <th>Note</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Inline Form --}}
    <div class="fb-inline-form-panel d-none" tabindex="-1" id="depreciationFormPanel">
        <div class="fb-inline-form-header">
            <h5 class="mb-0">Add Depreciation</h5>
            <button type="button" class="btn btn-label-secondary btn-sm" data-wes-form-close><i class="bx bx-arrow-back me-1"></i> Back to List</button>
        </div>
        <div class="fb-inline-form-body">
            <form id="depreciationForm">
                @csrf
                <input type="hidden" name="id" id="id">
                <div class="mb-3">
                    <label>Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="mb-3">
                    <label>Asset</label>
                    <select name="asset_id" id="asset_id" class="form-select" required>
                        <option value="">Select Asset</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }} - {{ $asset->amount }} {{ $asset->currency->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label>Amount</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Currency</label>
                    <select name="currency_id" id="currency_id" class="form-select" required>
                        <option value="">Select Currency</option>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label>Note</label>
                    <textarea name="note" id="note" rows="2" class="form-control"></textarea>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('vendor-script')
@endsection

@section('page-script')
    <script>
            window.resetDepreciationForm = function () {
                const form = document.getElementById('depreciationForm');
                form.reset();
                $('#id').val('');
                $(form).find('select').trigger('change');
            };
        let table;

        $(document).ready(function() {
            table = $('#depreciationTable').DataTable({
                ajax: '{{ route('accounting.depreciations.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'asset',
                        name: 'asset'
                    },
                    {
                        data: 'amount',
                        name: 'amount'
                    },
                    {
                        data: 'currency',
                        name: 'currency'
                    },
                    {
                        data: 'branch',
                        name: 'branch'
                    },
                    {
                        data: 'note',
                        name: 'note'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            $('#depreciationForm').submit(function(e) {
                e.preventDefault();
                let form = $(this);
                const id = $('#id').val();
                const url = id ? `/accounting/depreciations/${id}` : '{{ route('accounting.depreciations.store') }}';
                const method = id ? 'PUT' : 'POST';

                $.ajax({
                    type: method,
                    url: url,
                    data: form.serialize(),
                    success: function(res) {
                        wesCloseInlineForm('#depreciationFormPanel');
                        Swal.fire({
                            icon: 'success',
                            title: res.message,
                            timer: 1000,
                            showConfirmButton: false
                        });
                        table.ajax.reload();

                        form[0].reset();
                        $('#id').val('');
                    },
                    error: function(err) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: err.responseJSON.message || 'Something went wrong.'
                        });
                    }
                });
            });


            $(document).on('click', '.edit-btn', function () {
                const id = $(this).data('id');
                $.ajax({
                    url: `/accounting/depreciations/${id}`,
                    method: 'GET',
                    success: function (res) {
                        $('#id').val(res.id);
                        $('#date').val(res.date);
                        $('#asset_id').val(res.asset_id);
                        $('#amount').val(res.amount);
                        $('#currency_id').val(res.currency_id);
                        $('#note').val(res.note);

                        wesOpenInlineForm('#depreciationFormPanel');
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to fetch depreciation data'
                        });
                    }
                });
            });


            $(document).on('click', '.delete-btn', function() {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            type: 'DELETE',
                            url: `/accounting/depreciations/${id}`,
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(res) {
                                Swal.fire({
                                    icon: 'success',
                                    title: res.message,
                                    timer: 1000,
                                    showConfirmButton: false
                                });
                                table.ajax.reload();
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
