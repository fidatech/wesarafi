@extends('layouts.layoutMaster')
@section('title', 'Financial Audit Export History')
@section('vendor-style') @include('financial_reports._report-styles') @endsection
@section('content')
<div class="row g-3 report-page">
    <div class="col-lg-2">@include('layouts.menu.financial-sidebar')</div>
    <div class="col-lg-10 fb-content"><div class="fb-main pt-3">
        <div class="report-header"><h4><i class="bx bx-history me-2"></i>Financial Audit Export History</h4><p>Read-only record of historical closing integrity CSV export requests</p></div>
        <form method="GET" class="card report-filter-card my-3"><div class="card-body row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">Branch</label><select class="form-select" name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === (int) $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Requested by</label><select class="form-select" name="user_id"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bx bx-filter-alt me-1"></i>Apply</button></div>
        </div></form>
        <div class="card report-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Requested at</th><th>User</th><th>Branch</th><th>Export date range</th><th>IP address</th><th>Action</th></tr></thead><tbody>
            @forelse($events as $event)
                <tr><td>{{ $event->created_at?->format('Y-m-d H:i:s') }}</td><td>{{ $event->causer?->name ?? 'Unknown user' }}</td><td>{{ $event->properties->get('branch_id') }}</td><td>{{ $event->properties->get('from') ?? 'Any' }} — {{ $event->properties->get('to') ?? 'Any' }}</td><td>{{ $event->properties->get('ip_address') ?? '—' }}</td><td><span class="badge bg-label-info">CSV requested</span></td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">No audit export requests match the selected filters.</td></tr>
            @endforelse
        </tbody></table></div></div>
        <div class="mt-3">{{ $events->links() }}</div>
    </div></div>
</div>
@endsection
