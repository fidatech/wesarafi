@extends('layouts.layoutMaster')
@section('title', __('Customer & Bank Ledgers'))
@section('content')
<div class="row g-3">
    <div class="col-lg-2">@include('layouts.menu.journal-sidebar')</div>
    <div class="col-lg-10 fb-content"><div class="fb-main">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h4 class="mb-1">{{ __('Customer & Bank Ledgers') }}</h4><p class="text-muted mb-0">{{ __('Approved debits, credits and balances by account and currency.') }}</p></div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ledgerEntryModal"><i class="bx bx-plus me-1"></i>{{ __('New debit / credit') }}</button>
        </div>

        <form method="get" class="card mb-3"><div class="card-body row g-3 align-items-end">
            <div class="col-xl-2 col-md-4"><label class="form-label">{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">{{ __('To') }}</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">{{ __('Branch') }}</label><select name="branch_id" class="form-select"><option value="">{{ __('All branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)$branchId===$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">{{ __('Account type') }}</label><select name="account_type" class="form-select"><option value="">{{ __('Customers and banks') }}</option><option value="customer" @selected($accountType==='customer')>{{ __('Customers') }}</option><option value="bank" @selected($accountType==='bank')>{{ __('Banks') }}</option></select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">{{ __('Account') }}</label><select name="account_id" class="form-select"><option value="">{{ __('All accounts') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((int)request('account_id')===$account->id)>{{ $account->account_code }} — {{ $account->full_name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">{{ __('Currency') }}</label><select name="currency_id" class="form-select"><option value="">{{ __('All currencies') }}</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected((int)request('currency_id')===$currency->id)>{{ $currency->code }}</option>@endforeach</select></div>
            <div class="col-12 d-flex justify-content-end gap-2"><a href="{{ route('journal.transactions.account-ledgers') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a><button class="btn btn-primary"><i class="bx bx-filter-alt me-1"></i>{{ __('Apply filters') }}</button></div>
        </div></form>

        @if($totals->isNotEmpty())<div class="row g-3 mb-3">@foreach($totals as $currency=>$total)<div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><strong>{{ $currency }}</strong><i class="bx bx-wallet text-primary"></i></div><div class="row mt-3 small"><div class="col"><span class="text-muted">{{ __('Debit') }}</span><strong class="d-block text-success">{{ number_format($total['debit'],2) }}</strong></div><div class="col"><span class="text-muted">{{ __('Credit') }}</span><strong class="d-block text-body">{{ number_format($total['credit'],2) }}</strong></div><div class="col"><span class="text-muted">{{ __('Balance') }}</span><strong class="d-block text-primary">{{ \App\Support\LedgerBalance::format($total['balance']) }}</strong></div></div></div></div></div>@endforeach</div>@endif

        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ __('Account code') }}</th><th>{{ __('Customer / bank') }}</th><th>{{ __('Type') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('Entries') }}</th><th></th></tr></thead><tbody>
            @forelse($rows as $row)<tr><td><code>{{ $row->account_code }}</code></td><td><strong>{{ $row->full_name }}</strong></td><td><span class="badge bg-label-{{ $row->account_type==='bank'?'info':'primary' }}">{{ __(ucfirst($row->account_type)) }}</span></td><td>{{ $row->branch_name }}</td><td><strong>{{ $row->currency }}</strong></td><td class="text-end text-success">{{ number_format($row->debit,2) }}</td><td class="text-end text-body">{{ number_format($row->credit,2) }}</td><td class="text-end fw-bold text-body">{{ \App\Support\LedgerBalance::format($row->balance) }}</td><td class="text-end">{{ number_format($row->entries_count) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('financial_reports.account-statement.index',['account_id'=>$row->account_id,'from'=>$from,'to'=>$to,'branch_id'=>$branchId,'currency_id'=>$row->currency_id]) }}">{{ __('Statement') }}</a></td></tr>
            @empty<tr><td colspan="10" class="text-center text-muted py-5">{{ __('No customer or bank journal entries were found.') }}</td></tr>@endforelse
        </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
    </div></div>
</div>

<div class="modal fade" id="ledgerEntryModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" action="{{ route('journal.transactions.account-ledgers.store') }}" class="modal-content">@csrf
    <div class="modal-header"><div><h5 class="modal-title">{{ __('Store customer or bank transaction') }}</h5><small class="text-muted">{{ __('The related safe transaction is created automatically.') }}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">{{ __('Customer / bank') }} *</label><select name="account_id" id="entryAccount" class="form-select" required><option value="">{{ __('Select account') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-type="{{ $account->type }}" @selected(old('account_id')==$account->id)>{{ $account->account_code }} — {{ $account->full_name }} ({{ __(ucfirst($account->type)) }})</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Branch') }} *</label><select name="branch_id" class="form-select" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id',auth()->user()->branch_id)==$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Date') }} *</label><input type="date" name="date" value="{{ old('date',now()->toDateString()) }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('Direction') }} *</label><select name="direction" class="form-select" required><option value="debit" @selected(old('direction')==='debit')>{{ __('Debit customer/bank') }}</option><option value="credit" @selected(old('direction')==='credit')>{{ __('Credit customer/bank') }}</option></select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Currency') }} *</label><select name="currency_id" class="form-select" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_id')==$currency->id)>{{ $currency->code }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('Amount') }} *</label><input type="number" name="amount" value="{{ old('amount') }}" step=".0001" min=".0001" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('AFN rate') }} *</label><input type="number" name="exchange_rate_afn" value="{{ old('exchange_rate_afn',1) }}" step=".00000001" min=".00000001" class="form-control" required></div>
        <div class="col-12"><div class="alert alert-info mb-0"><strong>{{ __('Posting rules') }}:</strong> {{ __('Customer or bank credit debits Safe (money in); customer or bank debit credits Safe (money out).') }}</div></div>
        <div class="col-12"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea></div>
    </div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary"><i class="bx bx-save me-1"></i>{{ __('Store transaction') }}</button></div>
</form></div></div>
@endsection
@section('page-script')<script>$(function(){@if($errors->any())new bootstrap.Modal(document.getElementById('ledgerEntryModal')).show();@endif});</script>@endsection
