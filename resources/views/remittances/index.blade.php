@extends('remittances.shell')
@section('remittance-content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">{{ __('Remittances') }}</h4><span class="text-muted">{{ __('Payment instructions, branch routes, and payout status.') }}</span></div><a href="{{ route('remittances.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i>{{ __('New Remittance') }}</a></div>
<form method="GET" action="{{ route('remittances.index') }}" class="card mb-3"><div class="card-body row g-2 align-items-end">
<div class="col-md-2"><label class="form-label" for="filterSearch">{{ __('Reference / customer') }}</label><input id="filterSearch" name="search" value="{{ request('search') }}" class="form-control"></div>
<div class="col-md-2"><label class="form-label" for="filterType">{{ __('Type') }}</label><select id="filterType" name="direction" class="form-select"><option value="">{{ __('All types') }}</option>
@foreach(['outgoing','incoming','transfer'] as $type)
<option value="{{ $type }}" @selected(request('direction',$status)===$type)>{{ __(ucfirst($type)) }}</option>
@endforeach
</select></div>
<div class="col-md-2"><label class="form-label" for="filterStatus">{{ __('Status') }}</label><select id="filterStatus" name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>
@foreach(['pending','approved','completed','rejected'] as $value)
<option value="{{ $value }}" @selected(request('status',$status)===$value)>{{ __(ucfirst($value)) }}</option>
@endforeach
</select></div>
<div class="col-md-2"><label class="form-label" for="filterCurrency">{{ __('Currency') }}</label><select id="filterCurrency" name="currency_id" class="form-select"><option value="">{{ __('All currencies') }}</option>
@foreach($currencies as $currency)
<option value="{{ $currency->id }}" @selected(request('currency_id')==$currency->id)>{{ $currency->code }}</option>
@endforeach
</select></div>
<div class="col-md-2"><label class="form-label" for="filterFrom">{{ __('From') }}</label><input id="filterFrom" type="date" name="from_date" value="{{ request('from_date') }}" class="form-control"></div>
<div class="col-md-2"><label class="form-label" for="filterTo">{{ __('To') }}</label><input id="filterTo" type="date" name="to_date" value="{{ request('to_date') }}" class="form-control"></div>
<div class="col-12 text-end"><a href="{{ route('remittances.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a> <button class="btn btn-primary">{{ __('Apply filters') }}</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ __('Reference / date') }}</th><th>{{ __('Route') }}</th><th>{{ __('Sender / receiver') }}</th><th>{{ __('Payout') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
@forelse($remittances as $r)
<tr><td><strong>{{ $r->reference }}</strong><small class="d-block text-muted">{{ $r->date->toDateString() }} · {{ ucfirst($r->direction) }}</small></td><td>{{ $r->fromBranch?->name }} →
@if($r->bridgeBranch)
{{ $r->bridgeBranch->name }} →
@endif
{{ $r->toBranch?->name }}</td><td>{{ $r->sender_name }}<small class="d-block text-muted">To: {{ $r->receiver_name }}</small></td><td><strong>{{ number_format($r->amount,2) }} {{ $r->currency?->code }}</strong></td><td>{{ number_format($r->commission,2) }}</td><td><span class="badge bg-label-{{ $r->status==='pending'?'warning':($r->status==='rejected'?'danger':'success') }}">{{ __(ucfirst($r->status)) }}</span></td><td><a href="{{ route('remittances.show',$r) }}" class="btn btn-sm btn-outline-primary">{{ __('Details') }}</a></td></tr>
@empty
<tr><td colspan="7" class="text-center text-muted py-4">{{ __('No matching remittances.') }}</td></tr>
@endforelse
</tbody></table></div><div class="card-body">{{ $remittances->links() }}</div></div>
@endsection
