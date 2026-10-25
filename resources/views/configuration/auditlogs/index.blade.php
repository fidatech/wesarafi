@extends('layouts.layoutMaster')

@section('title', 'Audit Logs')

@section('vendor-style')
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
                        <li class="breadcrumb-item active">Audit Logs</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="fb-main">
            <div class="fb-table-wrapper">
                <h4 class="card-title mb-4">
                    <i class="bx bx-history me-2"></i> Audit Logs
                </h4>

                {{-- Filters --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="filter_user" class="form-label">User</label>
                        <select id="filter_user" class="form-select">
                            <option value="">All</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filter_date" class="form-label">Date</label>
                        <input type="text" id="filter_date" class="form-control" placeholder="Select date">
                    </div>

                    <div class="col-md-3">
                        <label for="filter_action" class="form-label">Action</label>
                        <select id="filter_action" class="form-select">
                            <option value="">All</option>
                            <option value="store">Store</option>
                            <option value="update">Update</option>
                            <option value="destroy">Destroy</option>
                        </select>
                    </div>
                </div>

                {{-- Table --}}
                <div class="table-responsive wes-institutional-table">
                    <table id="audit-table" class="table table-bordered table-striped w-100">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Controller</th>
                                <th>Action</th>
                                <th>Route</th>
                                <th>IP</th>
                                <th>User Agent</th>
                                <th>Date</th>
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
        flatpickr('#filter_date', { dateFormat: 'Y-m-d', allowInput: true });

        const table = $('#audit-table').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            lengthChange: true,
            pageLength: 15,
            ajax: {
                url: '{{ route('configuration.auditlogs.data') }}',
                data: function (d) {
                    d.user_id = $('#filter_user').val();
                    d.date = $('#filter_date').val();
                    d.action = $('#filter_action').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'user', name: 'user'},
                {data: 'controller', name: 'controller'},
                {data: 'action', name: 'action'},
                {data: 'route', name: 'route'},
                {data: 'ip', name: 'ip'},
                {data: 'user_agent', name: 'user_agent'},
                {data: 'created_at', name: 'created_at'}
            ]
        });

        $('#filter_user, #filter_date, #filter_action').on('change', function () {
            table.ajax.reload();
        });
    </script>
@endsection
