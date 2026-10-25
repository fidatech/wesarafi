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
            <span class="dashboard-greeting">Good day,</span>
            <h2>{{ auth()->user()->name ?? 'Administrator' }}</h2>
            <p>Here is the operational overview for <strong>{{ $scopeLabel }}</strong> as of {{ $date }}.</p>
            <div class="dashboard-quick-actions">
                <a class="hero-action hero-action-exchange" href="{{ route('exchange.customers.index') }}"><i class="bx bx-transfer-alt"></i><span>New exchange</span></a>
                <a class="hero-action hero-action-remittance" href="{{ route('remittances.index') }}"><i class="bx bx-send"></i><span>Send remittance</span></a>
                <a class="hero-action hero-action-journal" href="{{ route('journal.transactions.index') }}"><i class="bx bx-book-open"></i><span>Open journal</span></a>
                <a class="hero-action hero-action-expenses" href="{{ route('accounting.expenses.index') }}"><i class="bx bx-receipt"></i><span>Expenses</span></a>
                <a class="hero-action hero-action-employees" href="{{ route('hr.employees.index') }}"><i class="bx bx-group"></i><span>Employees</span></a>
            </div>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="dashboard-filter dashboard-filter-panel" id="dashboardFilters">
            <div class="dashboard-filter-title"><i class="bx bx-bar-chart-alt-2"></i><span>Dashboard scope</span></div>
            <div>
                <label for="dashboardDate">Date</label>
                <input id="dashboardDate" type="date" name="date" max="{{ now('Asia/Kabul')->toDateString() }}" value="{{ $date }}" class="form-control">
            </div>
            <div>
                <label for="dashboardBranch">Branch</label>
                <select id="dashboardBranch" name="branch_id" class="form-select">
                    @if(!auth()->user()->branch_id)<option value="0" @selected(!$branchId)>All branches</option>@endif
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="dashboardCurrency">Currency</label><select id="dashboardCurrency" name="currency_id" class="form-select"><option value="">All currencies</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected($currencyId == $currency->id)>{{ $currency->code }}</option>@endforeach</select></div>
            <div class="dashboard-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt me-1"></i> Apply filters</button>
                <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}" title="Reset dashboard filters"><i class="bx bx-refresh"></i><span>Reset</span></a>
            </div>
        </form>
    </section>

    <div class="row g-3 mb-3 dashboard-kpi-grid">
        <div class="col-xl-3 col-md-6">
            <div class="dashboard-finance-card dashboard-kpi-card is-cash">
                <div class="dashboard-finance-icon"><i class="bx bx-wallet"></i></div>
                <div class="dashboard-kpi-copy"><span>Available cash</span><strong>{{ $currentCashAfn === null ? '—' : $money($currentCashAfn) }} <small>AFN</small></strong><small>{{ $cashBalances->firstWhere('code', 'USD') ? number_format((float) $cashBalances->firstWhere('code', 'USD')['balance'], 2).' USD' : 'All currencies' }}</small><small>{{ $scopeLabel }} · as of {{ $date }}</small></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <a class="dashboard-finance-card dashboard-kpi-card is-expense" href="{{ route('accounting.expenses.index') }}">
                <div class="dashboard-finance-icon"><i class="bx bx-receipt"></i></div>
                <div class="dashboard-kpi-copy"><span>Monthly expenses</span><strong>{{ $money($monthExpenses) }} <small>AFN</small></strong><small>{{ $monthStart }} — {{ $date }}</small></div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a class="dashboard-finance-card dashboard-kpi-card is-bank" href="{{ route('accounts.banks.index') }}">
                <div class="dashboard-finance-icon"><i class="bx bxs-bank"></i></div>
                <div class="dashboard-kpi-copy"><span>Bank activity</span><strong>{{ $money($bankTransactionVolume) }} <small>AFN</small></strong><small>This month</small></div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a class="dashboard-finance-card dashboard-kpi-card is-transactions" href="{{ route('journal.transactions.index', ['date_range' => $date . ' - ' . $date]) }}">
                <div class="dashboard-finance-icon"><i class="bx bx-transfer-alt"></i></div>
                <div class="dashboard-kpi-copy"><span>Today's transactions</span><strong>{{ $integer($summary['today_transactions']) }}</strong><small>All types</small><small>{{ $date }}</small></div>
            </a>
        </div>
    </div>

    <div class="dashboard-section-heading">
        <div><h4><i class="bx bx-bar-chart-alt-2"></i> Business at a glance</h4></div>
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
                <span class="dashboard-count-icon"><i class="bx bx-buildings"></i></span>
                <span class="dashboard-count-copy"><small>Branches</small><strong>{{ $integer($summary['branches']) }}</strong><em>Network coverage</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-green" href="{{ route('accounts.all_customers.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-user"></i></span>
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
                <span class="dashboard-count-icon"><i class="bx bx-user-pin"></i></span>
                <span class="dashboard-count-copy"><small>Active employees</small><strong>{{ $integer($summary['employees']) }}</strong><em>Workforce</em></span><i class="bx bx-chevron-right metric-arrow rtl-flip"></i>
            </a>
        </div>
        <div class="col-xxl-3 col-xl-3 col-md-4 col-sm-6">
            <a class="dashboard-count-card metric-indigo" href="{{ route('accounts.banks.index') }}">
                <span class="dashboard-count-icon"><i class="bx bx-calculator"></i></span>
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

    <div class="row g-3 mb-3 dashboard-data-row">
        <div class="col-xl-4 col-xxl-3">
            <section class="card dashboard-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-1">Current Cash by Currency</h5><small>{{ $scopeLabel }} · as of {{ $date }}</small></div>
                    <i class="bx bxs-bank fs-3 text-primary"></i>
                </div>
                <div class="card-body p-0">
                    <div class="dashboard-cash-table-head"><span>Currency</span><span>Balance</span><span>AFN Equivalent</span></div>
                    <div class="cash-balance-list">
                        @forelse($cashBalances as $cash)
                            <div class="cash-balance-row">
                                <span class="currency-pill"><span class="dashboard-currency-flag">{{ $cash['code'] === 'AFN' ? '🇦🇫' : ($cash['code'] === 'USD' ? '🇺🇸' : '💱') }}</span>{{ $cash['code'] }}</span>
                                <strong class="dashboard-cash-amount">{{ number_format((float) $cash['balance'], 4) }} {{ $cash['code'] }}</strong>
                                <span class="dashboard-cash-equivalent">{{ $cash['afn_equivalent'] === null ? '—' : $money($cash['afn_equivalent']).' AFN' }}</span>
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

        <div class="col-xl-5 col-xxl-6">
            <section class="card dashboard-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-1">Recent Treasury Movements</h5><small>Approved treasury movements up to {{ $date }}</small></div>
                    <a href="{{ route('journal.transactions.index', ['branch_id' => $branchId, 'date_range' => $monthStart . ' - ' . $date]) }}" class="btn btn-sm btn-outline-primary">Open journal</a>
                </div>
                <div class="table-responsive wes-institutional-table">
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
                                <tr><td colspan="8" class="text-center text-muted py-5"><div class="dashboard-empty-state"><i class="bx bx-file"></i><strong>No approved transactions found.</strong><small>Transactions will appear here once recorded and approved.</small></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-3 col-xxl-3">
            <section class="card dashboard-card h-100 dashboard-exchange-chart">
                <div class="card-header"><div><h5 class="mb-1"><i class="bx bx-bar-chart-alt-2 text-primary"></i> Exchange Volume <small>(Last 7 Days)</small></h5><small>Approved journal volume · AFN equivalent</small></div></div>
                <div class="card-body"><div id="dashboardExchangeChart" role="img" aria-label="Actual exchange volume by day"></div></div>
            </section>
        </div>
    </div>

    <section class="card dashboard-card organization-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="org-panel-heading"><span class="org-panel-icon"><i class="bx bx-sitemap"></i></span><span><h5 class="mb-0">Organizational Chart</h5><small>Company structure &amp; reporting lines</small></span></div>
            <div class="org-panel-tools">
                <label class="org-search-wrap" for="orgChartSearch"><i class="bx bx-search"></i><input id="orgChartSearch" type="search" placeholder="Find a position or person…" aria-label="Search organizational chart"></label>
                <button class="org-tool-btn" id="orgChartExpand" type="button" title="Expand all positions"><i class="bx bx-expand-alt"></i><span>Expand</span></button>
                <button class="org-tool-btn" id="orgChartCollapse" type="button" title="Collapse all positions"><i class="bx bx-collapse-alt"></i><span>Collapse</span></button>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.employees.index') }}"><i class="bx bx-user-plus me-1"></i> Manage employees</a>
            </div>
        </div>
        <div class="org-chart-toolbar"><span><i class="bx bx-git-branch"></i> {{ $organizationTree->count() }} top-level positions</span><span><i class="bx bx-group"></i> {{ $shareholders->count() }} active shareholders</span><span class="org-chart-tip"><i class="bx bx-mouse"></i> Scroll horizontally to explore the hierarchy</span></div>
        <div class="card-body organization-scroll" id="orgChartViewport" tabindex="0" aria-label="Scrollable organizational chart">
            <div class="org-chart" id="orgChart">
                <ul class="org-root-list">
                    <li>
                        <article class="org-node-card org-shareholder-card">
                            <div class="org-shareholder-heading"><span class="org-shareholder-icon"><i class="bx bxs-crown"></i></span><span><strong>Shareholders</strong><small>Ownership &amp; governance</small></span><span class="org-owner-count">{{ $shareholders->count() }} active</span></div>
                            <div class="org-shareholder-grid">
                                @forelse($shareholders as $shareholder)
                                    <div class="org-person shareholder-person">
                                        <span class="org-avatar">{{ mb_strtoupper(mb_substr($shareholder->name, 0, 1)) }}</span>
                                        <span><strong>{{ $shareholder->name }}</strong><small>{{ number_format((float) $shareholder->share_percentage, 2) }}% ownership</small></span>
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

    <section class="card dashboard-card dashboard-recent-transactions mt-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2"><span class="dashboard-recent-icon"><i class="bx bx-receipt"></i></span><div><h5 class="mb-1">Recent Transactions</h5><small>Latest approved journal entries through {{ $date }} · {{ $scopeLabel }}{{ $currencyId ? ' · '.$currencies->firstWhere('id', $currencyId)?->code : ' · All currencies' }}</small></div></div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('journal.transactions.index', ['branch_id' => $branchId, 'date_range' => $monthStart.' - '.$date]) }}"><i class="bx bx-list-ul me-1"></i> View all transactions</a>
        </div>
        <div class="table-responsive wes-institutional-table">
            <table class="table dashboard-table dashboard-recent-table align-middle mb-0">
                <thead><tr><th>Reference</th><th>Date</th><th>Transaction type</th><th>Account</th><th>Branch</th><th>Currency</th><th>Direction</th><th class="text-end">Amount</th><th class="text-end">AFN value</th></tr></thead>
                <tbody>
                    @forelse($recentAllTransactions as $transaction)
                        <tr>
                            <td><span class="dashboard-code">#{{ $transaction->id }}</span></td>
                            <td class="text-nowrap">{{ $transaction->date?->format('d M Y') }}</td>
                            <td><span class="dashboard-transaction-type">{{ str_replace('_', ' ', $transaction->module_name ?: ($transaction->chartOfAccount?->account_name ?: 'Journal')) }}</span></td>
                            <td><strong>{{ $transaction->account?->full_name ?? $transaction->chartOfAccount?->account_name ?? '—' }}</strong><small class="d-block text-muted">{{ $transaction->description ? \Illuminate\Support\Str::limit($transaction->description, 55) : ($transaction->chartOfAccount?->code ?? '') }}</small></td>
                            <td>{{ $transaction->branch?->name ?? '—' }}</td>
                            <td><span class="dashboard-currency-chip">{{ $transaction->currency?->code ?? '—' }}</span></td>
                            <td><span class="dashboard-direction {{ $transaction->direction === 'debit' ? 'is-in' : 'is-out' }}"><i class="bx {{ $transaction->direction === 'debit' ? 'bx-down-arrow-alt' : 'bx-up-arrow-alt' }}"></i>{{ $transaction->direction === 'debit' ? 'Debit' : 'Credit' }}</span></td>
                            <td class="text-end fw-semibold text-nowrap">{{ $money($transaction->amount) }}</td>
                            <td class="text-end text-nowrap">{{ $transaction->amount_afn === null ? '—' : $money($transaction->amount_afn) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5"><div class="dashboard-empty-state"><i class="bx bx-receipt"></i><strong>No transactions for these filters</strong><small>Try a different date, branch or currency.</small></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer dashboard-recent-footer"><span>Showing {{ $recentAllTransactions->count() }} most recent approved entries</span><span>Data as of {{ $date }}</span></div>
    </section>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const orgChart = document.getElementById('orgChart');
  if (orgChart) {
    const branches = Array.from(orgChart.querySelectorAll('.org-branch'));
    const setExpanded = (li, expanded) => {
      const childList = li.querySelector(':scope > .org-children');
      const toggle = li.querySelector(':scope > .org-node-card .org-toggle');
      if (!childList || !toggle) return;
      childList.hidden = !expanded;
      li.classList.toggle('org-collapsed', !expanded);
      toggle.setAttribute('aria-expanded', String(expanded));
      toggle.setAttribute('aria-label', (expanded ? 'Collapse' : 'Expand') + ' team');
      toggle.querySelector('i').className = 'bx ' + (expanded ? 'bx-chevron-up' : 'bx-chevron-down');
    };
    branches.forEach(li => li.querySelector(':scope > .org-node-card .org-toggle')?.addEventListener('click', () => {
      setExpanded(li, li.classList.contains('org-collapsed'));
    }));
    document.getElementById('orgChartExpand')?.addEventListener('click', () => branches.forEach(li => setExpanded(li, true)));
    document.getElementById('orgChartCollapse')?.addEventListener('click', () => branches.forEach(li => setExpanded(li, false)));
    const search = document.getElementById('orgChartSearch');
    search?.addEventListener('input', () => {
      const term = search.value.trim().toLocaleLowerCase();
      branches.forEach(li => {
        const ownText = li.querySelector(':scope > .org-node-card')?.textContent?.toLocaleLowerCase() || '';
        li.classList.toggle('org-search-match', !!term && ownText.includes(term));
        li.classList.toggle('org-search-dim', !!term && !ownText.includes(term));
      });
      if (term) branches.forEach(li => {
        if (li.classList.contains('org-search-match')) {
          let parent = li.parentElement?.closest('.org-branch');
          while (parent) {setExpanded(parent, true);parent = parent.parentElement?.closest('.org-branch');}
        }
      });
    });
  }
  const host = document.getElementById('dashboardExchangeChart');
  if (!host) return;
  const data = @json($exchangeChart);
  const series = [{key:'buy',color:'#1268f3',name:'Buy'},{key:'sell',color:'#04ad72',name:'Sell'},{key:'remittance',color:'#f68c28',name:'Remittance'}];
  const max = Math.max(1,...data.flatMap(d=>series.map(s=>Number(d[s.key])||0)));
  const svgNS='http://www.w3.org/2000/svg';
  const svg=document.createElementNS(svgNS,'svg');svg.setAttribute('viewBox','0 0 360 195');svg.setAttribute('width','100%');svg.setAttribute('aria-hidden','true');
  const el=(tag,attrs,parent=svg)=>{const n=document.createElementNS(svgNS,tag);Object.entries(attrs).forEach(([k,v])=>n.setAttribute(k,v));parent.appendChild(n);return n};
  for(let i=0;i<4;i++){const y=24+i*43;el('line',{x1:38,y1:y,x2:351,y2:y,stroke:'#e8edf5'});const t=el('text',{x:32,y:y+4,'text-anchor':'end',fill:'#8190a5','font-size':10});t.textContent=Intl.NumberFormat('en',{notation:'compact'}).format(max*(3-i)/3)}
  data.forEach((d,i)=>{const x=42+i*50;el('line',{x1:x,y1:24,x2:x,y2:153,stroke:'#f0f3f8'});const t=el('text',{x,y:173,'text-anchor':'middle',fill:'#73849b','font-size':10});t.textContent=d.date});
  series.forEach(s=>{const points=data.map((d,i)=>`${42+i*50},${153-(Number(d[s.key])||0)/max*129}`).join(' ');el('polyline',{points,fill:'none',stroke:s.color,'stroke-width':2,'stroke-linecap':'round','stroke-linejoin':'round'});data.forEach((d,i)=>el('circle',{cx:42+i*50,cy:153-(Number(d[s.key])||0)/max*129,r:2.5,fill:s.color}))});
  host.appendChild(svg);
  const legend=document.createElement('div');legend.className='dashboard-chart-legend';series.forEach(s=>{const item=document.createElement('span');item.textContent='● '+s.name;item.style.color=s.color;legend.appendChild(item)});host.prepend(legend);
});
</script>
@endpush
@endsection
