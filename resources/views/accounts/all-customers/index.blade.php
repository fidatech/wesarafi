@extends('layouts.layoutMaster')

@section('title', 'All Customers')

@section('vendor-style')

@endsection

@section('content')
    <div class="row g-3">
        {{-- Sidebar --}}
        <div class="col-lg-2">@include('layouts.menu.accounts-sidebar')</div>
        {{-- Main Content --}}
        <div class="col-lg-10 fb-content wes-accounts-ui">
            <header class="wes-list-hero">
                <div class="wes-list-heading">
                    <span class="wes-list-icon" aria-hidden="true"><i class="bx bx-group"></i></span>
                    <div><h1>All Customers</h1><p>Manage all customer accounts, view balances and transaction details.</p></div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-danger export-customers" data-format="pdf"><i class="bi bi-file-earmark-pdf-fill" aria-hidden="true"></i> Export PDF</button>
                    <button type="button" class="btn btn-outline-success export-customers" data-format="xlsx"><i class="bx bx-spreadsheet" aria-hidden="true"></i> Export Excel</button>
                </div>
            </header>

            {{-- Scrollable Table Area --}}
            <div class="fb-main">
                {{-- Table + Pagination --}}
                <div class="fb-table-wrapper wes-accounts-table" id="tableCard">
                    <div class="row g-2 mb-3 wes-accounts-filters">
                        <div class="col-md-3"><label for="filterCustomerType" class="form-label"><i class="bi bi-stack" aria-hidden="true"></i>Type</label><select id="filterCustomerType" class="form-select"><option value="">All types</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name_en }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label for="filterStatus" class="form-label"><i class="bi bi-circle-fill" aria-hidden="true"></i>Status</label><select id="filterStatus" class="form-select"><option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option></select></div>
                        <div class="col-md-2"><label for="filterBalance" class="form-label"><i class="bx bx-coin-stack" aria-hidden="true"></i>Balance</label><select id="filterBalance" class="form-select"><option value="">All balances</option><option value="credit">Credit (Cr)</option><option value="debit">Debit (Dr)</option><option value="zero">Zero</option><option value="nonzero">Non-zero</option></select></div>
                        <div class="col-md-2"><label for="filterCurrency" class="form-label"><i class="bx bx-transfer" aria-hidden="true"></i>Currency</label><select id="filterCurrency" class="form-select"><option value="">All currencies</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label for="customerSearch" class="form-label"><i class="bx bx-search" aria-hidden="true"></i>Search</label><input id="customerSearch" class="form-control" placeholder="Name, code or phone"></div>
                    </div>

                    <div class="table-responsive wes-institutional-table">
                        <table class="table-sm table-hover table-bordered table align-middle" id="customersTable">
                            <thead class="text-center">
                                <tr>
                                    <th style="width: 3%">ID</th>
                                    <th style="width: 8%">Code</th>
                                    <th style="width: 11%">Name</th>
                                    <th style="width: 11%">Phone</th>
                                    <th style="width: 11%">Email</th>
                                    <th style="width: 11%">Type</th>
                                    <th style="width: 11%">Balances by currency</th>
                                    <th style="width: 5%">Status</th>
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
        $(document).ready(function() {

            const filters = () => ({customer_type: $('#filterCustomerType').val(), status: $('#filterStatus').val(), balance: $('#filterBalance').val(), currency_id: $('#filterCurrency').val(), q: $('#customerSearch').val()});
            const table = $('#customersTable').DataTable({
                language: {
                    emptyTable: `<span class="wes-empty-title">No customers found</span>There are no customers matching your current filters.<br><a class="btn wes-empty-action" href="{{ route('accounts.regular_customers.index') }}#create">+ Add New Customer</a>`,
                    zeroRecords: `<span class="wes-empty-title">No customers found</span>There are no customers matching your current filters.<br><a class="btn wes-empty-action" href="{{ route('accounts.regular_customers.index') }}#create">+ Add New Customer</a>`
                },
                drawCallback: function () {
                    const api = this.api();
                    const container = api.table().container();
                    let count = container.querySelector('.wes-list-count');
                    if (!count) {
                        count = document.createElement('span');
                        count.className = 'wes-list-count';
                        count.setAttribute('aria-live', 'polite');
                        container.querySelector('.dt-layout-end, .row:first-child > :last-child')?.append(count);
                    }
                    count.textContent = `${api.page.info().recordsDisplay.toLocaleString()} customers`;
                },
                processing: true,
                serverSide: true,
                searching: false,
                lengthChange: true,
                order: [[1, "asc"]],
                ajax: {url: '{{ route('accounts.all_customers.data') }}', data: d => Object.assign(d, filters())},
                columns: [{ data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    {
                        data: 'account_code'
                    },
                    {
                        data: 'full_name'
                    },
                    {
                        data: 'phone'
                    },
                    {
                        data: 'email'
                    },
                    {
                        data: 'sub_category', orderable: false
                    },
                    {
                        data: 'balance',
                        name: 'balance', orderable: false, searchable: false
                    },
                    {
                        data: 'is_active',
                        render: d => d ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'
                    }
                ]
            });
            $('#filterCustomerType, #filterStatus, #filterBalance, #filterCurrency').on('change', () => table.ajax.reload());
            let searchTimer;
            $('#customerSearch').on('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(() => table.ajax.reload(), 250); });
            $('.export-customers').on('click', function () {
                window.open('{{ route('accounts.all_customers.export') }}?' + new URLSearchParams({...filters(), format: this.dataset.format}), '_blank');
            });
        });
    </script>
@endsection
