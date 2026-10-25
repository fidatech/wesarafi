@extends('remittances.shell')
@section('remittance-content')
@php($r=$remittance)
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h4 class="mb-1">{{ $r->reference }}</h4><span class="badge bg-label-{{ $r->status==='pending'?'warning':($r->status==='rejected'?'danger':'success') }}">{{ __(ucfirst($r->status)) }}</span> <span class="text-muted">{{ $r->date->toDateString() }} · {{ ucfirst($r->direction) }}</span></div><div class="d-flex gap-2"><a href="{{ route('remittances.index') }}" class="btn btn-outline-secondary">{{ __('Back to List') }}</a><a href="{{ route('remittances.slip',$r) }}" target="_blank" class="btn btn-outline-primary">{{ __('Receipt') }}</a><a href="{{ route('remittances.slip',[$r,'pdf'=>1]) }}" class="btn btn-outline-danger">{{ __('PDF') }}</a></div></div>
<div class="card mb-3"><div class="card-body"><h5>{{ $r->fromBranch?->name }} →
@if($r->bridgeBranch)
{{ $r->bridgeBranch->name }} →
@endif
{{ $r->toBranch?->name }}</h5><div class="row g-3"><div class="col-md-4"><small class="text-muted">{{ __('Sender') }}</small><strong class="d-block">{{ $r->sender_name }}</strong><span>{{ $r->sender_father_name }} · {{ $r->sender_phone }}</span></div><div class="col-md-4"><small class="text-muted">{{ __('Receiver') }}</small><strong class="d-block">{{ $r->receiver_name }}</strong><span>{{ $r->receiver_father_name }} · {{ $r->receiver_phone }}</span></div><div class="col-md-4"><small class="text-muted">{{ __('Collection') }}</small><strong class="d-block">{{ $r->collection_method==='account'?'Debit sender account':'Cash at sending branch' }}</strong></div></div></div></div>
<div class="card mb-3"><div class="card-body row g-3"><div class="col-md-4">{{ __('Receiver gets') }}<br><strong>{{ number_format($r->amount,2) }} {{ $r->currency->code }}</strong></div><div class="col-md-4">{{ __('Total commission') }}<br><strong>{{ number_format($r->commission,2) }} {{ $r->currency->code }}</strong></div><div class="col-md-4">{{ __('Collect from sender') }}<br><strong class="text-primary">{{ number_format($r->total_collected,2) }} {{ $r->currency->code }}</strong></div><div class="col-12 text-muted">Commission: {{ $r->fromBranch?->name }} {{ number_format($r->sender_branch_commission,2) }}
@if($r->bridgeBranch)
 · {{ $r->bridgeBranch->name }} {{ number_format($r->bridge_commission,2) }}
@endif
 · {{ $r->toBranch?->name }} {{ number_format($r->receiver_branch_commission,2) }} {{ $r->currency->code }}</div>
@if($r->description)
<div class="col-12">{{ $r->description }}</div>
@endif
</div></div>
@if($r->status==='pending' && $canApprove)
<div class="card mb-3"><div class="card-body"><p>{{ __('Approval records collection at the sending branch, branch debts, and commission income. Branch settlement and receiver payout are separate actions.') }}</p><div class="d-flex gap-2"><form method="POST" action="{{ route('remittances.approve',$r) }}">
@csrf
<button class="btn btn-success">{{ __('Approve Remittance') }}</button></form><button class="btn btn-outline-danger" data-wes-form-target="#rejectRemittancePanel">{{ __('Reject') }}</button></div></div></div>
<div class="fb-inline-form-panel d-none" id="rejectRemittancePanel"><div class="fb-inline-form-header"><h5 class="mb-0">{{ __('Reject Remittance') }}</h5><button type="button" class="btn btn-outline-secondary" data-wes-form-close>{{ __('Back to Details') }}</button></div><form method="POST" action="{{ route('remittances.reject',$r) }}" class="fb-inline-form-body">
@csrf
<label for="reason" class="form-label">{{ __('Reason') }}</label><textarea id="reason" name="reason" class="form-control mb-3" rows="3" required maxlength="1000">{{ old('reason') }}</textarea><button class="btn btn-danger">{{ __('Confirm Rejection') }}</button></form></div>
@endif
@if($r->status==='rejected')
<div class="alert alert-danger">{{ $r->rejection_reason }}</div>
@endif
@if($r->status==='approved' && $canPay && $r->workflow_version==2)
<div class="mb-3"><button class="btn btn-primary" data-wes-form-target="#remittancePaymentPanel">Pay Receiver · {{ number_format($r->amount,2) }} {{ $r->currency->code }}</button></div>
@include('remittances.payment')
@endif
@if($r->status==='completed')
<div class="alert alert-success">Receiver payment completed {{ $r->completed_at?->format('Y-m-d H:i') }}.</div>
@endif
@if($r->status==='approved' && $r->workflow_version!=2)
<div class="alert alert-warning">{{ __('This older remittance requires ledger reconciliation before payout.') }}</div>
@endif
@if($r->legs->isNotEmpty())
<div class="card mb-3"><div class="card-header"><h5 class="mb-0">{{ __('Branch Settlement') }}</h5><small class="text-muted">{{ __('These obligations remain outstanding until actual payment is recorded.') }}</small></div><div class="table-responsive wes-institutional-table"><table class="table table-sm mb-0"><thead><tr><th>{{ __('Paying branch') }}</th><th>{{ __('Owed to') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Settled') }}</th><th>{{ __('Outstanding') }}</th><th></th></tr></thead><tbody>
@foreach($r->legs as $leg)
<tr><td>{{ $leg->debtor->name }}</td><td>{{ $leg->creditor->name }}</td><td>{{ number_format($leg->amount,2) }}</td><td>{{ number_format($leg->settlements->sum('amount'),2) }}</td><td><strong>{{ number_format($leg->outstanding,2) }} {{ $r->currency->code }}</strong></td><td>
@if($leg->outstanding>0 && in_array(auth()->user()->branch_id,[$leg->debtor_branch_id,$leg->creditor_branch_id]))
<button class="btn btn-sm btn-outline-primary" data-wes-form-target="#settlementPanel{{ $leg->id }}">{{ __('Record Payment') }}</button>
@endif
</td></tr>
@endforeach
</tbody></table></div></div>
@foreach($r->legs as $leg)
@if($leg->outstanding>0 && in_array(auth()->user()->branch_id,[$leg->debtor_branch_id,$leg->creditor_branch_id]))
<div class="fb-inline-form-panel d-none" id="settlementPanel{{ $leg->id }}"><div class="fb-inline-form-header"><h5 class="mb-0">{{ $leg->debtor->name }} pays {{ $leg->creditor->name }}</h5><button type="button" class="btn btn-outline-secondary" data-wes-form-close>{{ __('Back to Details') }}</button></div><form method="POST" action="{{ route('remittances.settle',[$r,$leg]) }}" class="fb-inline-form-body">
@csrf
<input type="hidden" name="form_panel" value="settlementPanel{{ $leg->id }}"><p>{{ __('Outstanding:') }}<strong>{{ number_format($leg->outstanding,2) }} {{ $r->currency->code }}</strong>{{ __('. Confirm only a payment that has actually been made.') }}</p><div class="row g-3"><div class="col-md-4"><label for="settleAmount{{ $leg->id }}" class="form-label">{{ __('Amount') }}</label><input id="settleAmount{{ $leg->id }}" type="number" name="amount" class="form-control" min="0.01" max="{{ $leg->outstanding }}" step="0.0001" required value="{{ old('form_panel')==='settlementPanel'.$leg->id?old('amount'):$leg->outstanding }}"></div><div class="col-md-4"><label for="settleDate{{ $leg->id }}" class="form-label">{{ __('Payment date') }}</label><input id="settleDate{{ $leg->id }}" type="date" name="date" value="{{ now()->toDateString() }}" min="{{ $r->date->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" required></div><div class="col-md-4"><label for="settleRate{{ $leg->id }}" class="form-label">{{ __('AFN per unit') }}</label><input id="settleRate{{ $leg->id }}" name="exchange_rate_afn" type="number" min="0.00000001" step="0.00000001" class="form-control" value="{{ $r->currency->code==='AFN'?1:old('exchange_rate_afn') }}" @readonly($r->currency->code==='AFN') required></div><div class="col-md-8"><label for="settleReference{{ $leg->id }}" class="form-label">{{ __('Payment reference / notes') }}</label><input id="settleReference{{ $leg->id }}" name="reference" value="{{ old('reference') }}" class="form-control" maxlength="255"></div><div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100">{{ __('Confirm Branch Payment') }}</button></div></div></form></div>
@endif
@endforeach
<div class="card"><div class="card-header">{{ __('Settlement history') }}</div><div class="table-responsive wes-institutional-table"><table class="table table-sm mb-0"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Route') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Recorded by') }}</th></tr></thead><tbody>
@foreach($r->legs as $leg)
@foreach($leg->settlements as $payment)
<tr><td>{{ $payment->date->toDateString() }}</td><td>{{ $leg->debtor->name }} → {{ $leg->creditor->name }}</td><td>{{ number_format($payment->amount,2) }} {{ $r->currency->code }}</td><td>{{ $payment->reference }}</td><td>{{ $payment->creator?->name }}</td></tr>
@endforeach
@endforeach
</tbody></table></div></div>
@endif
@endsection
@section('page-script')
<script src="{{ asset('js/remittance-payment.js') }}?v={{ filemtime(public_path('js/remittance-payment.js')) }}"></script>
@if(isset($errors) && $errors->any())
<script>
const panelId = @json(old('form_panel', old('reason') !== null ? 'rejectRemittancePanel' : 'remittancePaymentPanel'));
const panel = document.getElementById(panelId);
if (panel?.classList.contains('fb-inline-form-panel')) window.wesOpenInlineForm(panel);
</script>
@endif
@endsection
