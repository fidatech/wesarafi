<div class="fb-sidebar" id="fbSidebar">
  <h6 class="text-muted ps-4">Accounting Menu</h6>

  <button onclick="window.location='{{ url('accounting/capital') }}'" {{ request()->is('accounting/capital*') ? 'class=active' : '' }}>
    <i class="bx bx-coin-stack icon-xl me-2"></i> Capital
  </button>

  <button onclick="window.location='{{ route('accounting.branch-capital.index') }}'" {{ request()->is('accounting/branch-capital*') ? 'class=active' : '' }}>
    <i class="bx bx-transfer icon-xl me-2"></i> Branch Capital
  </button>

  <button onclick="window.location='{{ url('accounting/assets') }}'" {{ request()->is('accounting/assets*') ? 'class=active' : '' }}>
    <i class="bx bx-briefcase-alt icon-xl me-2"></i> Assets
  </button>

  <button onclick="window.location='{{ url('accounting/liabilities') }}'" {{ request()->is('accounting/liabilities*') ? 'class=active' : '' }}>
    <i class="bx bx-building icon-xl me-2"></i> Liabilities
  </button>

  <button onclick="window.location='{{ url('accounting/expenses') }}'" {{ request()->is('accounting/expenses*') ? 'class=active' : '' }}>
    <i class="bx bx-wallet icon-xl me-2"></i> Expenses
  </button>

  <button onclick="window.location='{{ url('accounting/depreciations') }}'" {{ request()->is('accounting/depreciations*') ? 'class=active' : '' }}>
    <i class="bx bx-down-arrow-alt icon-xl me-2"></i> Depreciation
  </button>

  <button onclick="window.location='{{ url('accounting/amortizations') }}'" {{ request()->is('accounting/amortizations*') ? 'class=active' : '' }}>
    <i class="bx bx-down-arrow icon-xl me-2"></i> Amortization
  </button>
</div>
