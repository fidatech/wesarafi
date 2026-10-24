@extends('layouts.layoutMaster')
@section('title', 'Modified Transactions')
@section('content')
<div class="row g-3"><div class="col-lg-2">@include('layouts.menu.monitoring-sidebar')</div><div class="col-lg-10 fb-content"><div class="fb-main">
    <h4>Modified Transactions</h4>
    <p class="text-muted">Recorded financial edits and replacement postings. Earlier changes without recorded snapshots are not reconstructed.</p>
    <form method="GET" class="card mb-3"><div class="card-body row g-3 align-items-end">
        <div class="col-md-3"><label for="modifiedFrom" class="form-label">Modified from</label><input id="modifiedFrom" type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-md-3"><label for="modifiedTo" class="form-label">Modified to</label><input id="modifiedTo" type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-md-3"><label for="modifiedBranch" class="form-label">Branch</label><select id="modifiedBranch" name="branch_id" class="form-select">@if(!auth()->user()->branch_id)<option value="0">All branches</option>@endif @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><button class="btn btn-primary">Apply filters</button></div>
    </div></form>
    @forelse($changes as $change)
        <div class="card mb-3"><div class="card-header d-flex justify-content-between gap-2 flex-wrap"><strong>{{ $change->description }}</strong><span>{{ $branches->firstWhere('id', (int) $change->properties->get('branch_id'))?->name ?? '—' }}</span><span>{{ $change->created_at->format('Y-m-d H:i:s') }} · {{ $change->causer?->name ?? 'System' }}</span></div><div class="card-body">
            <details><summary>View before and after</summary>
                @foreach(['before' => 'Before', 'after' => 'After'] as $key => $label)
                    <h6 class="mt-3 mb-2">{{ $label }}</h6><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>ID / Reference</th><th>Date</th><th>Account</th><th>COA</th><th>Currency</th><th>Direction</th><th>Amount</th><th>AFN / Rate</th><th>Description</th></tr></thead><tbody>
                    @foreach($change->properties->get($key, []) as $row)<tr><td>#{{ $row['id'] }}<br><small>{{ $row['linked_id'] }}</small></td><td>{{ substr($row['date'], 0, 10) }}</td><td>{{ $row['account'] ?? '—' }}</td><td>{{ $row['coa'] ?? '—' }}</td><td>{{ $row['currency'] ?? '—' }}</td><td>{{ ucfirst($row['direction']) }}</td><td>{{ number_format((float)$row['amount'], 2) }}</td><td>{{ number_format((float)$row['amount_afn'], 2) }}<br><small>{{ $row['exchange_rate_afn'] }}</small></td><td>{{ $row['description'] }}</td></tr>@endforeach
                    </tbody></table></div>
                @endforeach
            </details>
        </div></div>
    @empty<div class="card"><div class="card-body text-muted">No recorded modifications in this period.</div></div>@endforelse
    {{ $changes->links() }}
</div></div></div>
@endsection
