@extends('layouts.layoutMaster')

@section('title', 'Dashboard')

@section('content')
@php
    $money = fn ($value) => number_format((float) ($value ?? 0), 2);
    $integer = fn ($value) => number_format((int) ($value ?? 0));
    $scopeLabel = $selectedBranch?->name ?? 'All branches';
@endphp
<div class="wes-dashboard">
    <section class="dashboard-hero dashboard-hero-modern mb-4">
        <div class="dashboard-hero-copy">
            <span class="dashboard-live-pill"><i class="bx bx-pulse"></i> Live financial overview</span>
            <h2>Good day, {{ auth()->user()->name ?? 'Administrator' }}</h2>
            <p>Here is the operational position for <strong>{{ $scopeLabel }}</strong> as of {{ $date }}.</p>
            <div class="dashboard-quick-actions">
                <a href="{{ route('exchange.customers.index') }}"><i class="bx bx-transfer-alt"></i><span>New exchange</span></a>
                <a href="{{ route('remittances.index') }}"><i class="bx bx-send"></i><span>Send remittance</span></a>
                <a href="{{ route('journal.transactions.index') }}"><i class="bx bx-book-open"></i><span>Open journal</span></a>
            </div>
        </div>
        <form method="GET" class="dashboard-filter dashboard-filter-panel">
            <div class="dashboard-filter-title"><i class="bx bx-slider-alt"></i><span>Dashboard scope</span></div>
            <div>
                <label>Date</label>
                <input type="date" name="date" value="{{ $date }}" class="form-control">
            </div>
            <div>
                <label>Branch</label>
                <select name="branch_id" class="form-select">
                    @if(!auth()->user()->branch_id)<option value="0" @selected(!$branchId)>All branches</option>@endif
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="dashboardCurrency">Currency</label><select id="dashboardCurrency" name="currency_id" class="form-select"><option value="">All currencies</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected($currencyId == $currency->id)>{{ $currency->code }}</option>@endforeach</select></div>
            <button class="btn btn-light"><i class="bx bx-check me-1"></i> Apply filters</button>
        </form>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dashboard-finance-card dashboard-kpi-card is-cash">
                <div class="dashboard-finance-icon"><i class="bx bx-wallet"></i></div>
                <div class="dashboard-kpi-copy"><span>Available cash</span>@foreach($cashBalances as $cash)<strong>{{ $money($cash['balance']) }} <small>{{ $cash['code'] }}</small></strong>@endforeach<small>{{ $scopeLabel }} · as of {{ $date }}</small></div>
                <span class="dashboard-kpi-mark"><i class="bx bx-trending-up"></i></span>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <a class="dashboard-finance-card dashboard-kpi-card is-expense" href="{{ route('accounting.expenses.index') }}">
                <div class="dashboard-finance-icon"><i class="bx bx-receipt"></i></div>
                <div class="dashboard-kpi-copy"><span>Monthly expenses</span><strong>{{ $money($monthExpenses) }} <small>AFN</small></strong><small>{{ $monthStart }} — {{ $date }}</small></div>
                <span class="dashboard-kpi-mark"><i class="bx bx-line-chart-down"></i></span>
            </a>
        </div>
        <div class="col-xl-4 col-md-6">
            <a class="dashboard-finance-card dashboard-kpi-card is-bank" href="{{ route('accounts.banks.index') }}">
                <div class="dashboard-finance-icon"><i class="bx bx-building-house"></i></div>
                <div class="dashboard-kpi-copy"><span>Bank activity</span><strong>{{ $money($bankTransactionVolume) }} <small>AFN</small></strong><small>Transaction volume this month</small></div>
                <span class="dashboard-kpi-mark"><i class="bx bx-bar-chart-alt-2"></i></span>
            </a>
        </div>
    </div>

    <div class="dashboard-section-heading">
        <div><span>Operations</span><h4>Business at a glance</h4></div>
        <small>Live record counts for the selected scope</small>
    </div>
    <div class="row g-3 mb-4 dashboard-count-grid dashboard-metric-grid">
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-blue" href="{{ route('configuration.users.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-user-circle"></i></span>
                <span class="dashboard-count-copy"><small>System users</small><strong>{{ $integer($summary['users']) }}</strong><em>Manage access</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-cyan" href="{{ route('configuration.branches.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-git-branch"></i></span>
                <span class="dashboard-count-copy"><small>Branches</small><strong>{{ $integer($summary['branches']) }}</strong><em>Network coverage</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-green" href="{{ route('accounts.all_customers.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-group"></i></span>
                <span class="dashboard-count-copy"><small>All customers</small><strong>{{ $integer($summary['customers']) }}</strong><em>Customer accounts</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-amber" href="{{ route('accounts.temporary_customers.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-time-five"></i></span>
                <span class="dashboard-count-copy"><small>Temporary customers</small><strong>{{ $integer($summary['temporary_customers']) }}</strong><em>Awaiting promotion</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-violet" href="{{ route('hr.employees.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-id-card"></i></span>
                <span class="dashboard-count-copy"><small>Active employees</small><strong>{{ $integer($summary['employees']) }}</strong><em>Workforce</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-indigo" href="{{ route('accounts.banks.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-building"></i></span>
                <span class="dashboard-count-copy"><small>Bank accounts</small><strong>{{ $integer($summary['bank_accounts']) }}</strong><em>Connected accounts</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-rose" href="{{ route('hr.shareholders.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-crown"></i></span>
                <span class="dashboard-count-copy"><small>Shareholders</small><strong>{{ $integer($summary['shareholders']) }}</strong><em>Company-wide ownership</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-slate" href="{{ route('journal.transactions.index', ['date_range' => $date . ' - ' . $date, 'branch_id' => $branchId]) }}">
                <span class="dashboard-count-icon"><i class="bx bx-transfer"></i></span>
                <span class="dashboard-count-copy"><small>Treasury movements</small><strong>{{ $integer($summary['today_transactions']) }}</strong><em>On {{ $date }}</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <section class="card dashboard-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-1">Current Cash by Currency</h5><small>{{ $scopeLabel }} · as of {{ $date }}</small></div>
                    <i class="bx bx-money-withdraw fs-3 text-primary"></i>
                </div>
                <div class="card-body p-0">
                    <div class="cash-balance-list">
                        @forelse($cashBalances as $cash)
                            <div class="cash-balance-row">
                                <span class="currency-pill">{{ $cash['code'] }}</span>
                                <span class="cash-balance-values">
                                    <strong>{{ number_format((float) $cash['balance'], 4) }}</strong>
                                    <small>{{ $cash['afn_equivalent'] === null ? 'Closing rate required' : $money($cash['afn_equivalent']).' AFN' }}</small>
                                </span>
                            </div>
                        @empty
                            <div class="dashboard-empty-state"><i class="bx bx-wallet-alt"></i><span>No cash balance found for this branch.</span></div>
                        @endforelse
                    </div>
                </div>
                <div class="card-footer cash-total-footer">
                    <span>Total AFN equivalent</span><strong>{{ $currentCashAfn === null ? 'Set missing closing rates' : $money($currentCashAfn).' AFN' }}</strong>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card dashboard-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-1">Recent Treasury Movements</h5><small>Approved treasury movements up to {{ $date }}</small></div>
                    <a href="{{ route('journal.transactions.index', ['branch_id' => $branchId, 'date_range' => $monthStart . ' - ' . $date]) }}" class="btn btn-sm btn-outline-primary">Open journal</a>
                </div>
                <div class="table-responsive">
                    <table class="table dashboard-table mb-0">
                        <thead><tr><th>Date</th><th>Reference</th><th>Linked Account</th><th>COA</th><th>Branch</th><th>Currency</th><th>Movement</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @forelse($recentTransactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->date?->format('Y-m-d') }}</td>
                                    <td><span class="dashboard-code">{{ $transaction->linked_id ? substr($transaction->linked_id, 0, 8) : '#' . $transaction->id }}</span></td>
                                    <td>{{ $transaction->journalLinkedAccounts() }}</td>
                                    <td><span class="dashboard-code">{{ $transaction->chartOfAccount?->code }}</span> {{ $transaction->chartOfAccount?->account_name }}</td>
                                    <td>{{ $transaction->branch?->name ?? '—' }}</td>
                                    <td>{{ $transaction->currency?->code ?? '—' }}</td>
                                    <td>{{ $transaction->direction === 'debit' ? 'Money in' : 'Money out' }}</td><td class="text-end fw-semibold">{{ $money($transaction->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-5">No approved transactions found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="card dashboard-card organization-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h5 class="mb-1">Organizational Tree</h5><small>Shareholders first, followed by designation hierarchy through <code>line_manager</code>.</small></div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.employees.index') }}">Manage employees</a>
        </div>
        <div class="card-body organization-scroll">
            <div class="org-chart">
                <ul class="org-root-list">
                    <li>
                        <article class="org-node-card org-shareholder-card">
                            <div class="org-node-title">Shareholders</div>
                            <div class="org-node-subtitle">سهم داران</div>
                            <div class="org-shareholder-grid">
                                @forelse($shareholders as $shareholder)
                                    <div class="org-person shareholder-person">
                                        <span class="org-avatar">{{ strtoupper(substr($shareholder->name, 0, 1)) }}</span>
                                        <span><strong>{{ $shareholder->name }}</strong><small>{{ number_format((float) $shareholder->share_percentage, 2) }}% share</small></span>
                                    </div>
                                @empty
                                    <div class="org-vacant">No active shareholders added.</div>
                                @endforelse
                            </div>
                        </article>

                        @if($organizationTree->isNotEmpty())
                            <ul>
                                @foreach($organizationTree as $node)
                                    @include('dashboard._org-node', ['node' => $node])
                                @endforeach
                            </ul>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </section>
</div>
@endsection
