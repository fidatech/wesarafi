<div class="fb-sidebar">
    <h6 class="text-muted ps-4">Accounts Menu</h6>
    <button onclick="window.location='{{ route('accounts.all_customers.index') }}'" {{ request()->routeIs('accounts.all_customers.index') ? 'class=active' : '' }}><i
            class="bx bx-group icon-xl me-2"></i> All Customers</button>
    <button onclick="window.location='{{ route('accounts.regular_customers.index') }}'" {{ request()->routeIs('accounts.regular_customers.index') ? 'class=active' : '' }}><i
            class="bx bx-user-circle icon-xl me-2"></i> Regular Customers</button>
    <button onclick="window.location='{{ route('accounts.corporate_customers.index') }}'" {{ request()->routeIs('accounts.corporate_customers.index') ? 'class=active' : '' }}><i
            class="bx bx-building icon-xl me-2"></i> Corporate Customers</button>
    <button onclick="window.location='{{ route('accounts.temporary_customers.index') }}'" {{ request()->routeIs('accounts.temporary_customers.index') ? 'class=active' : '' }}><i
            class="bx bx-time icon-xl me-2"></i> Temporary Customers</button>
    <button onclick="window.location='{{ route('accounts.street_vendors.index') }}'" {{ request()->routeIs('accounts.street_vendors.index') ? 'class=active' : '' }}><i
            class="bx bx-cart icon-xl me-2"></i> Street Vendors</button>
    <button onclick="window.location='{{ route('accounts.branches_accounts.index') }}'" {{ request()->routeIs('accounts.branches_accounts.index') ? 'class=active' : '' }}><i
            class="bx bx-store icon-xl me-2"></i> Branch Accounts</button>
    <button onclick="window.location='{{ route('accounts.banks.index') }}'" {{ request()->routeIs('accounts.banks.index') ? 'class=active' : '' }}><i
            class="bx bx-dollar icon-xl me-2"></i> Banks</button>
</div>
