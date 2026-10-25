<div class="fb-sidebar" id="fbSidebar">
    <h6 class="text-muted ps-4">Financial Menu</h6>

    <button onclick="window.location='{{ route('financial_reports.account-statement.index') }}'" {{ request()->routeIs('financial_reports.account-statement.index') ? 'class=active' : '' }}>
        <i class="bx bx-file icon-xl me-2"></i> Account Statement
    </button>

    <button onclick="window.location='{{ route('financial_reports.balance-sheet.index') }}'" {{ request()->routeIs('financial_reports.balance-sheet.index') ? 'class=active' : '' }}>
        <i class="bx bx-bar-chart-alt-2 icon-xl me-2"></i> Balance Sheet
    </button>

    @if(auth()->user()?->hasRole('Super Admin') || auth()->user()?->can('manage business day'))
    <button onclick="window.location='{{ route('financial_reports.audit-dashboard.index') }}'" {{ request()->routeIs('financial_reports.audit-dashboard.index') ? 'class=active' : '' }}>
        <i class="bx bx-shield-quarter icon-xl me-2"></i> Audit Dashboard
    </button>
    <button onclick="window.location='{{ route('financial_reports.audit-export-history.index') }}'" {{ request()->routeIs('financial_reports.audit-export-history.index') ? 'class=active' : '' }}>
        <i class="bx bx-history icon-xl me-2"></i> Audit Export History
    </button>
    <button onclick="window.location='{{ route('financial_reports.historical-integrity.index') }}'" {{ request()->routeIs('financial_reports.historical-integrity.index') ? 'class=active' : '' }}>
        <i class="bx bx-shield-quarter icon-xl me-2"></i> Closing Integrity
    </button>
    @endif

    <button onclick="window.location='{{ route('financial_reports.trial-balance.index') }}'" {{ request()->routeIs('financial_reports.trial-balance.index') ? 'class=active' : '' }}>
        <i class="bx bx-task icon-xl me-2"></i> Trial Balance
    </button>

    <button onclick="window.location='{{ route('financial_reports.profit-loss.index') }}'" {{ request()->routeIs('financial_reports.profit-loss.index') ? 'class=active' : '' }}>
        <i class="bx bx-line-chart icon-xl me-2"></i> Profit & Loss
    </button>

    <button onclick="window.location='{{ route('financial_reports.open-fx-position.index') }}'" {{ request()->routeIs('financial_reports.open-fx-position.index') ? 'class=active' : '' }}>
        <i class="bx bx-world icon-xl me-2"></i> Open FX Position
    </button>

    <button onclick="window.location='{{ route('financial_reports.customers.index') }}'" {{ request()->routeIs('financial_reports.customers.index') ? 'class=active' : '' }}>
        <i class="bx bx-user icon-xl me-2"></i> Customers
    </button>

    <button onclick="window.location='{{ route('financial_reports.exchange.index') }}'" {{ request()->routeIs('financial_reports.exchange.index') ? 'class=active' : '' }}>
        <i class="bx bx-transfer-alt icon-xl me-2"></i> Exchange
    </button>

    <button onclick="window.location='{{ route('financial_reports.auctions.index') }}'" {{ request()->routeIs('financial_reports.auctions.index') ? 'class=active' : '' }}>
        <i class="bx bx-money icon-xl me-2"></i> Auctions
    </button>

    <button onclick="window.location='{{ route('financial_reports.remittances.index') }}'" {{ request()->routeIs('financial_reports.remittances.index') ? 'class=active' : '' }}>
        <i class="bx bx-mail-send icon-xl me-2"></i> Remittances
    </button>

    <button onclick="window.location='{{ route('financial_reports.commissions.index') }}'" {{ request()->routeIs('financial_reports.commissions.index') ? 'class=active' : '' }}>
        <i class="bx bx-dollar icon-xl me-2"></i> Commissions
    </button>


</div>
