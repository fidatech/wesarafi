<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme">
  <div class="container-fluid d-flex h-100">
    <ul class="menu-inner">

      <!-- Dashboard -->
      <li class="menu-item @if (request()->routeIs('dashboard')) active @endif">
        <a href="{{ route('dashboard') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-home-alt"></i>
          <div>{{ __('Dashboard') }}</div>
        </a>
      </li>

      <!-- Customers -->
      <li class="menu-item @if (request()->routeIs('accounts.*')) active @endif">
        <a href="{{ route('accounts.all_customers.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-group"></i>
          <div>{{ __('Accounts') }}</div>
        </a>
      </li>


      <!-- Journal -->
      <li class="menu-item @if (request()->routeIs('journal*')) active @endif">
        <a href="{{ route('journal.transactions.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-notepad"></i>
          <div>{{ __('Journal') }}</div>
        </a>
      </li>

      <!-- Exchange -->
      <li class="menu-item @if(request()->routeIs('exchange*')) active @endif">
        <a href="{{ route('exchange.customers.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-transfer-alt"></i>
          <div>{{ __('Exchanges') }}</div>
        </a>
      </li>

      <!-- Remittances -->
      <li class="menu-item @if(request()->routeIs('remittances.*')) active @endif"><a href="{{ route('remittances.index') }}" class="menu-link"><i class="menu-icon icon-base bx bx-mail-send"></i><div>{{ __('Remittances') }}</div></a></li>


      {{-- <li class="menu-item">
        <a href="#" class="menu-link menu-toggle">
          <i class="menu-icon icon-base bx bx-transfer"></i>
          <div>Transactions</div>
        </a>
        <ul class="menu-sub">
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-dollar-circle"></i><div>Money Exchange</div></a></li>
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-money"></i><div>Auction</div></a></li>
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-upload"></i><div>Sent Remittances</div></a></li>
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-download"></i><div>Received Remittances</div></a></li>
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-error-circle"></i><div>Pending / Returned</div></a></li>
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-sort-alt-2"></i><div>Account to Account Transfer</div></a></li>
          <li class="menu-item"><a href="#" class="menu-link"><i class="menu-icon bx bx-refresh"></i><div>Branch Reconciliation</div></a></li>
        </ul>
      </li> --}}


      <!-- Accounting -->
      <li class="menu-item @if(request()->routeIs('accounting.*')) active @endif" style="cursor: pointer;" onclick="showAccounting()">
        <a href="{{ route('accounting.capital.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-calculator"></i>
          <div>{{ __('Accounting') }}</div>
        </a>
      </li>

      <!-- Human Resources -->
      <li class="menu-item @if (request()->routeIs('hr.*')) active @endif">
        <a href="{{ route('hr.shareholders.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-group"></i>
          <div>{{ __('Human Resources') }}</div>
        </a>
      </li>

      <!-- Financial Statements -->
      <li class="menu-item @if (request()->routeIs('financial_reports.*')) active @endif" style="cursor: pointer;" onclick="showFinancialReports()">
        <a href="{{ route('financial_reports.account-statement.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-bar-chart-square"></i>
          <div>{{ __('Financial Reports') }}</div>
        </a>
      </li>

      <!-- Monitoring -->
      <li class="menu-item @if(request()->routeIs('monitoring.*')) active @endif"><a href="{{ route('monitoring.approvals') }}" class="menu-link"><i class="menu-icon icon-base bx bx-search-alt"></i><div>{{ __('Monitoring') }}</div></a></li>

      <!-- Configuration -->
      <li class="menu-item @if (request()->routeIs('configuration.*')) active @endif">
        <a href="{{ route('configuration.settings.index') }}" class="menu-link">
          <i class="menu-icon icon-base bx bx-cog"></i>
          <div>{{ __('Configuration') }}</div>
        </a>
      </li>

    </ul>
  </div>
</aside>
