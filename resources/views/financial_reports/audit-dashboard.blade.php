@extends('layouts.layoutMaster')
@section('title', 'Financial Audit Dashboard')
@section('vendor-style') @include('financial_reports._report-styles') @endsection
@section('content')
<div class="row g-3 report-page">
    <div class="col-lg-2">@include('layouts.menu.financial-sidebar')</div>
    <div class="col-lg-10 fb-content"><div class="fb-main pt-3">
        <div class="report-header"><h4><i class="bx bx-shield-quarter me-2"></i>Financial Audit Dashboard</h4><p>Branch-scoped closing integrity and recorded export activity</p></div>
        <form method="GET" class="card report-filter-card my-3"><div class="card-body row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label">Branch</label><select name="branch_id" class="form-select">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === (int) $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Apply</button></div>
        </div></form>
        <div class="row g-3 mb-3">
            @foreach(['closed' => 'Closed days', 'consistent' => 'Consistent', 'discrepancy' => 'Discrepancies', 'unverifiable' => 'Unverifiable'] as $key => $label)
            <div class="col-md-3 col-sm-6"><div class="card report-card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h3 class="mt-2 mb-0">{{ number_format($counts[$key]) }}</h3></div></div></div>
            @endforeach
        </div>
        <div class="card report-card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="text-muted small">Recorded CSV export requests</div><h3 class="mb-0">{{ number_format($exportCount) }}</h3></div><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('financial_reports.historical-integrity.index', request()->only('branch_id', 'from', 'to')) }}">Closing integrity</a><a class="btn btn-outline-primary" href="{{ route('financial_reports.audit-export-history.index', request()->only('branch_id', 'from', 'to')) }}">Export history</a></div></div></div>
        <div class="card report-card"><div class="card-header"><h5 class="mb-0">Recent export requests</h5></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Requested at</th><th>Requested by</th><th>Branch</th><th>Export date range</th></tr></thead><tbody>
            @forelse($recentExports as $event)<tr><td>{{ $event->created_at?->format('Y-m-d H:i') }}</td><td>{{ $event->causer?->name ?? 'Unknown user' }}</td><td>{{ $event->properties->get('branch_id') }}</td><td>{{ $event->properties->get('from') ?? 'Any' }} — {{ $event->properties->get('to') ?? 'Any' }}</td></tr>
            @empty<tr><td colspan="4" class="text-center text-muted py-4">No export requests recorded for this selection.</td></tr>@endforelse
        </tbody></table></div></div>
        <p class="text-muted small mt-3">These checks compare recorded closing snapshots with current recalculations. They do not establish cryptographic tamper evidence or prove audit-log completeness.</p>
    </div></div>
</div>
@endsection
