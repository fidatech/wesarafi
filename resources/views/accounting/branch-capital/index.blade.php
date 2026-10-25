@extends('accounting.branch-capital.shell')
@section('branch-content')
<div class="d-flex align-items-center justify-content-between mb-3"><h4 class="mb-0">Branch Capital</h4><a href="{{ route('accounting.branch-capital.create') }}" class="btn btn-primary">Share Capital</a></div>
<p class="text-muted">Advances and outstanding branch debts by currency. Open an advance to view or record repayments.</p>
<form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
<div class="col-md-2"><label class="form-label" for="from">From</label><input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
<div class="col-md-2"><label class="form-label" for="to">To</label><input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
<div class="col-md-3"><label class="form-label" for="branch">Branch</label><select id="branch" name="branch_id" class="form-select"><option value="">All linked branches</option>
@foreach($branches as $branch)
<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>
@endforeach
</select></div>
<div class="col-md-2"><label class="form-label" for="currency">Currency</label><select id="currency" name="currency_id" class="form-select"><option value="">All currencies</option>
@foreach($currencies as $currency)
<option value="{{ $currency->id }}" @selected(request('currency_id')==$currency->id)>{{ $currency->code }}</option>
@endforeach
</select></div>
<div class="col-md-2"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select"><option value="">All</option><option value="outstanding" @selected(request('status')==='outstanding')>Outstanding</option><option value="repaid" @selected(request('status')==='repaid')>Repaid</option></select></div>
<div class="col-md-1"><button class="btn btn-primary">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive wes-institutional-table"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Funding branch</th><th>Receiving branch</th><th>Currency</th><th>Shared</th><th>Repaid</th><th>Outstanding</th><th></th></tr></thead><tbody>
@forelse($items as $item)
<tr><td>{{ $item->date->toDateString() }}</td><td>{{ $item->source->name }}</td><td>{{ $item->destination->name }}</td><td>{{ $item->currency->code }}</td><td>{{ number_format($item->amount,2) }}</td><td>{{ number_format($item->repayments_sum_amount ?? 0,2) }}</td><td><strong class="{{ $item->outstanding > 0 ? 'text-success' : 'text-muted' }}">{{ number_format($item->outstanding,2) }}</strong></td><td><a href="{{ route('accounting.branch-capital.show',$item) }}" class="btn btn-sm btn-outline-primary">Details</a></td></tr>
@empty
<tr><td colspan="8" class="text-center py-4">No matching branch advances.</td></tr>
@endforelse
</tbody></table></div><div class="card-body">{{ $items->links() }}</div></div>
@endsection
