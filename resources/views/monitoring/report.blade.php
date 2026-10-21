@extends('layouts.layoutMaster')
@section('title', $title)
@section('content')
<div class="row g-3"><div class="col-lg-2">@include('layouts.menu.monitoring-sidebar')</div><div class="col-lg-10 fb-content"><div class="fb-main">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2"><h4 class="mb-0">{{ $title }}</h4><div class="d-flex gap-2"><a class="btn btn-outline-danger" href="{{ request()->fullUrlWithQuery(['format'=>'pdf','page'=>null]) }}">Export PDF</a><a class="btn btn-outline-success" href="{{ request()->fullUrlWithQuery(['format'=>'xlsx','page'=>null]) }}">Export Excel</a></div></div>
@if(isset($errors) && $errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<p class="text-muted mb-2">
@switch($report)
@case('cumulative') Accounts with two or more approved entries totaling the threshold within the selected dates, checked separately for debit and credit. Results require review; they do not establish intent. @break
@case('rapid') Approved inflows at or above the threshold followed by outflows of at least 80% within the configured days and selected dates. Dates and entry order are used; overlapping windows may reference the same entries. @break
@case('inactive') No nondeleted debit or credit in any currency in the preceding configured days. Never-used accounts are included only after that age. @break
@case('cash-flow') Opening + money in − money out = closing. Treasury entries only; a linked-account filter shows only that account’s contribution. @break
@case('kyc') Current KYC completeness and document deadlines as of the selected date. No historical document snapshots are inferred. @break
@case('exceptions') Unapproved, missing-conversion, backdated (over seven days), and unbalanced linked postings. @break
@default Individual approved account entries at or above the configured AFN threshold.
@endswitch
</p>
<form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
@if(!in_array($report,['inactive','kyc']))<div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ $context['from'] }}" class="form-control"></div>@endif
<div class="col-md-2"><label class="form-label">{{ in_array($report,['inactive','kyc']) ? 'As of' : 'To' }}</label><input type="date" name="to" value="{{ $context['to'] }}" class="form-control"></div>
<div class="col-md-2"><label class="form-label">Branch</label><select name="branch_id" class="form-select">@if(!auth()->user()->branch_id)<option value="">All branches</option>@endif @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($context['branch_id']==$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">{{ $report==='cash-flow' ? 'Linked account' : 'Account' }}</label><select name="account_id" class="form-select"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($context['account_id']==$account->id)>{{ $account->full_name }}</option>@endforeach</select></div>
@if(!in_array($report,['inactive','kyc']))<div class="col-md-2"><label class="form-label">Currency</label><select name="currency_id" class="form-select"><option value="">All currencies</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected($context['currency_id']==$currency->id)>{{ $currency->code }}</option>@endforeach</select></div>@endif
@if(in_array($report,['cumulative','large','rapid']))<div class="col-md-2"><label class="form-label">Threshold AFN</label><input type="number" name="threshold" min="1" step="0.01" value="{{ $context['threshold'] }}" class="form-control"></div>@endif
@if($report==='inactive')<div class="col-md-2"><label class="form-label">Inactive days</label><input type="number" name="days" min="1" max="3650" value="{{ $context['days'] }}" class="form-control"></div>@endif
@if($report==='rapid')<div class="col-md-2"><label class="form-label">Window days</label><input type="number" name="window_days" min="1" max="30" value="{{ $context['window_days'] }}" class="form-control"></div>@endif
<div class="col-md-auto"><button class="btn btn-primary">Apply filters</button></div>
</div></form>
@foreach($warnings as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
<div class="small text-muted mb-2">{{ $items->total() }} results</div>
@forelse($items as $item)
<div class="card mb-3"><div class="card-body"><div class="row g-2 mb-2">@foreach($item['cells'] as $label=>$value)<div class="col-md-3"><div class="small text-muted">{{ $label }}</div><strong>{{ is_float($value) ? number_format($value,2) : $value }}</strong></div>@endforeach</div>
@if($item['details'])<details><summary>Transaction details ({{ count($item['details']) }})</summary>@include('monitoring.transaction-details', ['details'=>$item['details']])</details>@endif
@if($report!=='cash-flow')<details class="mt-2"><summary>Record review</summary><form method="POST" action="{{ route('monitoring.report.review', $report) }}" class="row g-2 mt-1">@csrf @foreach($context as $name=>$value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach<input type="hidden" name="item_key" value="{{ $item['key'] }}"><div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select" required>@foreach(['open','reviewing','cleared','escalated'] as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Assign to</label><select name="assigned_to" class="form-select"><option value="">Unassigned</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}">{{ $reviewer->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Review notes</label><textarea name="notes" required maxlength="5000" rows="2" class="form-control"></textarea></div><div class="col-12 text-end"><button class="btn btn-primary">Save review</button></div></form></details>@endif
</div></div>
@empty<div class="card"><div class="card-body text-muted">No matching results.</div></div>@endforelse
{{ $items->links() }}
</div></div></div>
@endsection
