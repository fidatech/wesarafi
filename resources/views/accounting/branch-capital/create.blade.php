@extends('accounting.branch-capital.shell')
@section('branch-content')
<div class="d-flex align-items-center justify-content-between mb-3"><h4 class="mb-0">Share Capital with a Branch</h4><a href="{{ route('accounting.branch-capital.index') }}" class="btn btn-outline-secondary">Back to list</a></div>
<div class="card"><div class="card-body">
<p class="text-muted">Move money between branch treasuries. The receiving branch owes the funding branch; the amount is recorded under حسابات قابل حصول.</p>
<form method="POST" action="{{ route('accounting.branch-capital.store') }}" class="row g-3">
@csrf
<div class="col-md-4"><label for="source" class="form-label">Funding branch</label><select id="source" name="source_branch_id" class="form-select" required>
@foreach($branches as $branch)
@if(!auth()->user()->branch_id || auth()->user()->branch_id == $branch->id)
<option value="{{ $branch->id }}" @selected(old('source_branch_id',auth()->user()->branch_id)==$branch->id)>{{ $branch->name }}</option>
@endif
@endforeach
</select></div>
<div class="col-md-4"><label for="destination" class="form-label">Receiving branch</label><select id="destination" name="destination_branch_id" class="form-select" required><option value="">Select branch</option>
@foreach($branches as $branch)
<option value="{{ $branch->id }}" @selected(old('destination_branch_id')==$branch->id)>{{ $branch->name }}</option>
@endforeach
</select></div>
<div class="col-md-4"><label for="currency" class="form-label">Currency</label><select id="currency" name="currency_id" class="form-select" required>
@foreach($currencies as $currency)
<option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected(old('currency_id')==$currency->id)>{{ $currency->code }}</option>
@endforeach
</select></div>
@include('accounting.branch-capital.fields', ['rate' => ''])
<div class="col-12 d-flex justify-content-end"><button class="btn btn-primary">Share Capital</button></div>
</form></div></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const source = document.getElementById('source'), destination = document.getElementById('destination');
    const currency = document.getElementById('currency'), rate = document.getElementById('rate');
    function syncBranch() {
        Array.from(destination.options).forEach(option => option.disabled = option.value === source.value);
        if (destination.value === source.value) destination.value = '';
    }
    function syncRate() {
        const afn = currency.selectedOptions[0]?.dataset.code === 'AFN';
        rate.readOnly = afn; rate.required = !afn;
        if (afn) rate.value = 1;
        else if (rate.value === '1') rate.value = '';
    }
    source.addEventListener('change', syncBranch); currency.addEventListener('change', syncRate);
    syncBranch(); syncRate();
});
</script>
@endsection
