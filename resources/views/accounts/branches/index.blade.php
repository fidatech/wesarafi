@extends('layouts.layoutMaster')

@section('title', 'All Branches Accounts')

@section('vendor-style')

@endsection

@section('content')
    <div class="row g-3">
        {{-- Sidebar --}}
        <div class="col-lg-2">@include('layouts.menu.accounts-sidebar')</div>
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
                            <li class="breadcrumb-item active">All Branches Accounts</li>
                        </ol>
                    </nav>
                </div>
            </div>

            {{-- Scrollable Table Area --}}
            <div class="fb-main">
                {{-- Table + Pagination --}}
                <div class="fb-table-wrapper" id="tableCard">
                    <h4 class="card-title mb-3">All Branches Accounts List</h4>

                    <div class="table-responsive">
                        <table class="table-sm table-hover table-bordered table align-middle" id="customersTable">
                            <thead class="text-center">
                                <tr>
                                    <th style="width: 3%">ID</th>
                                    <th style="width: 8%">Code</th>
                                    <th style="width: 11%">Branch Name</th>
                                    <th style="width: 11%">Phone</th>
                                    <th style="width: 11%">Email</th>
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

            $('#customersTable').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                lengthChange: false,
                ajax: '{{ route('accounts.branches_accounts.data') }}',
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
                        data: 'balance',
                        name: 'balance', orderable: false, searchable: false
                    },
                    {
                        data: 'is_active',
                        render: d => d ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'
                    }
                ]
            });
        });
    </script>
@endsection
