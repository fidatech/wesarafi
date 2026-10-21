@extends('accounting.branch-capital.shell')
@section('branch-content')
<div class="d-flex align-items-center justify-content-between mb-3"><h4 class="mb-0">Branch Advance #{{ $item->id }}</h4><a href="{{ route('accounting.branch-capital.index') }}" class="btn btn-outline-secondary">Back to list</a></div>
<div class="card mb-3"><div class="card-body">
<h5>{{ $item->source->name }} → {{ $item->destination->name }}</h5>
<p>{{ $item->date->toDateString() }} · {{ $item->currency->code }} · AFN rate {{ number_format($item->exchange_rate_afn,8) }}</p>
<div class="row g-3"><div class="col-md-4">Shared<br><strong>{{ number_format($item->amount,2) }} {{ $item->currency->code }}</strong></div><div class="col-md-4">Repaid<br><strong>{{ number_format($item->repayments->sum('amount'),2) }} {{ $item->currency->code }}</strong></div><div class="col-md-4">Outstanding debt<br><strong class="text-success">{{ number_format($item->outstanding,2) }} {{ $item->currency->code }}</strong></div></div>
<p class="mt-3 mb-0">{{ $item->description }}</p>
</div></div>
@if($item->outstanding > 0)
<button class="btn btn-primary mb-3" data-wes-form-target="#branchRepaymentPanel">Record Repayment</button>
<div id="branchRepaymentPanel" class="fb-inline-form-panel d-none"><div class="fb-inline-form-header"><h5 class="mb-0">Record Repayment</h5><button type="button" class="btn btn-outline-secondary" data-wes-form-close>Back to History</button></div><div class="fb-inline-form-body"><p class="text-muted">{{ $item->destination->name }} pays {{ $item->source->name }} in {{ $item->currency->code }}. Both treasuries and the branch debt are updated together.</p>
<form method="POST" action="{{ route('accounting.branch-capital.repay',$item) }}" class="row g-3">
@csrf
@include('accounting.branch-capital.fields', ['rate' => $item->currency->code === 'AFN' ? 1 : ''])
<div class="col-12 text-end"><button class="btn btn-primary">Record Repayment</button></div>
</form></div></div>
@endif
<div class="card"><div class="card-header">Repayment history</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Amount</th><th>AFN rate</th><th>Recorded by</th><th>Notes</th></tr></thead><tbody>
@forelse($item->repayments as $repayment)
<tr><td>{{ $repayment->date->toDateString() }}</td><td><strong>{{ number_format($repayment->amount,2) }} {{ $item->currency->code }}</strong></td><td>{{ number_format($repayment->exchange_rate_afn,8) }}</td><td>{{ $repayment->creator?->name }}</td><td>{{ $repayment->description }}</td></tr>
@empty
<tr><td colspan="5" class="text-center py-3">No repayments recorded.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection

@section('page-script')
@if(isset($errors) && $errors->any())
<script>window.wesOpenInlineForm('#branchRepaymentPanel');</script>
@endif
@endsection
