@extends('remittances.shell')
@section('remittance-content')
<div class="d-flex align-items-center justify-content-between mb-3 wes-remittance-header"><div><h4 class="mb-1">{{ __('New Remittance') }}</h4><span class="text-muted">{{ __('Choose the route, add the parties, and review the payment.') }}</span></div><a href="{{ route('remittances.index') }}" class="btn btn-outline-secondary">{{ __('Back to List') }}</a></div>
<form method="POST" action="{{ route('remittances.store') }}" enctype="multipart/form-data" id="remittanceForm">
@csrf
<div class="row g-2 mb-3">
@foreach(['outgoing'=>['bx-send','Outgoing','Send from your branch'], 'incoming'=>['bx-download','Incoming','Receive at your branch'], 'transfer'=>['bx-transfer','Branch Transfer','Direct or through Main Branch']] as $value=>$choice)
<div class="col-md-4"><input type="radio" class="btn-check" name="direction" id="type-{{ $value }}" value="{{ $value }}" @checked(old('direction',request('direction','outgoing'))===$value)><label for="type-{{ $value }}" class="btn btn-outline-primary w-100 d-flex align-items-center gap-3 text-start py-3"><i class="bx {{ $choice[0] }} fs-3"></i><span><strong class="d-block">{{ __($choice[1]) }}</strong><small>{{ __($choice[2]) }}</small></span></label></div>
@endforeach
</div>
<div class="card mb-3 wes-remittance-card"><div class="card-header"><h5 class="mb-0">{{ __('Route & Date') }}</h5></div><div class="card-body row g-3">
<div class="col-md-4"><label for="routing" class="form-label">{{ __('Routing') }}</label><select id="routing" name="routing_mode" class="form-select" required><option value="direct" @selected(old('routing_mode')==='direct')>{{ __('Direct branch to branch') }}</option><option value="via_main" @selected(old('routing_mode')==='via_main')>{{ __('Through Main Branch') }}</option></select></div>
<div class="col-md-4"><label for="fromBranch" class="form-label">{{ __('From branch') }}</label><select id="fromBranch" name="from_branch_id" class="form-select" required><option value="">{{ __('Select branch') }}</option>
@foreach($branches as $branch)
<option value="{{ $branch->id }}" @selected(old('from_branch_id',$userBranch->id)==$branch->id)>{{ $branch->name }}</option>
@endforeach
</select><small id="fromHint" class="text-muted"></small></div>
<div class="col-md-4"><label for="toBranch" class="form-label">{{ __('To branch') }}</label><select id="toBranch" name="to_branch_id" class="form-select" required><option value="">{{ __('Select branch') }}</option>
@foreach($branches as $branch)
<option value="{{ $branch->id }}" @selected(old('to_branch_id')==$branch->id)>{{ $branch->name }}</option>
@endforeach
</select><small id="toHint" class="text-muted"></small></div>
<div class="col-md-4"><label for="remittanceDate" class="form-label">{{ __('Date') }}</label><input id="remittanceDate" type="date" name="date" value="{{ old('date',now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
<div class="col-md-8 d-flex align-items-center"><div class="bg-light border rounded p-3 w-100"><strong id="routePreview">{{ __('Select a destination') }}</strong><small class="d-block text-muted mt-1" id="routeHint"></small></div></div>
</div></div>
<div class="row g-3 mb-3">
@foreach(['sender'=>'Sender','receiver'=>'Receiver'] as $party=>$label)
<div class="col-xl-6"><div class="card h-100 wes-remittance-card"><div class="card-header"><h5 class="mb-0">{{ __($label) }}</h5></div><div class="card-body row g-2">
<div class="col-12"><label class="form-label" for="{{ $party }}Account">{{ __('Existing customer') }}</label><select id="{{ $party }}Account" name="{{ $party }}_account_id" class="form-select" data-old-id="{{ old($party.'_account_id') }}"><option value="">{{ __('New / enter details') }}</option></select><small class="text-muted" id="{{ $party }}AccountHint">{{ __('Search customers in the selected branch.') }}</small></div>
@foreach(['name'=>'Full name','father_name'=>"Father’s name",'phone'=>'Phone','tazkira_number'=>'Tazkira / ID number'] as $field=>$text)
<div class="col-md-6"><label class="form-label" for="{{ $party }}-{{ $field }}">{{ __($text) }}</label><input id="{{ $party }}-{{ $field }}" name="{{ $party }}_{{ $field }}" value="{{ old($party.'_'.$field) }}" class="form-control" @required($field==='name') maxlength="{{ $field==='phone'?50:($field==='tazkira_number'?100:255) }}"></div>
@endforeach
@if($party==='sender')
<div class="col-12"><label class="form-label" for="senderFile">{{ __('Sender ID scan (optional)') }}</label><input id="senderFile" type="file" name="sender_tazkira_file" accept="image/jpeg,image/png,application/pdf" class="form-control"><small class="text-muted">{{ __('A selected customer’s saved ID is reused unless you upload a replacement.') }}</small></div>
@else
<div class="col-12"><p class="text-muted mb-0 mt-2">{{ __('The paying branch verifies the receiver’s identity when confirming payment.') }}</p></div>
@endif
</div></div></div>
@endforeach
</div>
<div class="card mb-3 wes-remittance-card"><div class="card-header"><h5 class="mb-0">{{ __('Amount & Commission') }}</h5></div><div class="card-body row g-3">
<div class="col-md-3"><label for="currency" class="form-label">{{ __('Currency') }}</label><select name="currency_id" id="currency" class="form-select" required>
@foreach($currencies as $currency)
<option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected(old('currency_id')==$currency->id)>{{ $currency->code }}</option>
@endforeach
</select></div>
<div class="col-md-3"><label for="amount" class="form-label">{{ __('Receiver gets') }}</label><input id="amount" name="amount" type="number" step="0.0001" min="0.01" max="999999999" value="{{ old('amount') }}" class="form-control" required></div>
<div class="col-md-3"><label for="rate" class="form-label">{{ __('AFN per currency unit') }}</label><input id="rate" name="exchange_rate_afn" type="number" step="0.00000001" min="0.00000001" value="{{ old('exchange_rate_afn') }}" class="form-control" required></div>
<div class="col-md-3"><label for="collection" class="form-label">{{ __('Collect from sender') }}</label><select id="collection" name="collection_method" class="form-select" required><option value="cash" @selected(old('collection_method')==='cash')>{{ __('Cash at sending branch') }}</option><option value="account" @selected(old('collection_method')==='account')>{{ __('Debit sender’s account') }}</option></select></div>
@foreach(['sender_branch_commission'=>'Sending branch commission','bridge_commission'=>'Main Branch commission','receiver_branch_commission'=>'Paying branch commission'] as $field=>$label)
<div class="col-md-4" @if($field==='bridge_commission') id="bridgeFeeField" @endif><label for="{{ $field }}" class="form-label">{{ __($label) }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" step="0.0001" min="0" value="{{ old($field,0) }}" class="form-control"></div>
@endforeach
<div class="col-12"><div class="bg-light border rounded p-3 row g-2 mx-0"><div class="col-md-4">{{ __('Receiver gets') }}<br><strong id="payoutPreview">0.00</strong></div><div class="col-md-4">{{ __('Total commission') }}<br><strong id="commissionPreview">0.00</strong></div><div class="col-md-4">{{ __('Collect from sender') }}<br><strong id="collectionPreview" class="text-primary">0.00</strong></div><div class="col-12"><small class="text-muted">{{ __('Commission is added on top. Approval records branch debts; actual branch settlements are recorded separately.') }}</small></div></div></div>
<div class="col-md-9"><label for="notes" class="form-label">{{ __('Notes / reference') }}</label><textarea id="notes" name="description" rows="2" maxlength="2000" class="form-control">{{ old('description') }}</textarea></div>
<div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100" id="storeRemittance">{{ __('Store Remittance') }}</button></div>
</div></div>
</form>
@endsection
@section('page-script')
@php($branchOptions = $branches->mapWithKeys(fn($b)=>[$b->id=>['name'=>$b->name,'main'=>(bool)$b->is_main,'access'=>$b->allows_direct_remittance && $b->users->where('status',true)->isNotEmpty()]]))
<script>
window.remittanceFormConfig = {
    userBranch: {{ $userBranch->id }}, isMain: @json($userBranch->is_main), main: @json($mainBranch?->id),
    customersUrl: @json(route('remittances.customers')),
    branches: @json($branchOptions)
};
</script>
<script src="{{ asset('js/remittance-form.js') }}?v={{ filemtime(public_path('js/remittance-form.js')) }}"></script>
@endsection
