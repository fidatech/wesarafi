<div class="fb-sidebar" id="fbSidebar">
    <h6 class="text-muted ps-4">Configuration Menu</h6>
    @php
    $configItems = [
        ['label' => 'System Settings', 'icon' => 'bx-cog', 'route' => 'configuration.settings.index'],
        ['label' => 'Branches', 'icon' => 'bx-git-branch', 'route' => 'configuration.branches.index'],
        ['label' => 'Users', 'icon' => 'bx-group', 'route' => 'configuration.users.index'],
        ['label' => 'Roles', 'icon' => 'bx-shield-quarter', 'route' => 'configuration.roles.index'],
        ['label' => 'Permissions', 'icon' => 'bx-lock-open', 'route' => 'configuration.permissions.index'],
        ['label' => 'Currencies', 'icon' => 'bx-globe', 'route' => 'configuration.currencies.index'],
        ['label' => 'Designations', 'icon' => 'bx-briefcase', 'route' => 'configuration.designations.index'],
        ['label' => 'Expense Types', 'icon' => 'bx-pie-chart-alt', 'route' => 'configuration.expense-types.index'],
        // ['label' => 'Liability Types', 'icon' => 'bx-building-house', 'route' => 'configuration.liability-types.index'],
        ['label' => 'Income Sources', 'icon' => 'bx-trending-up', 'route' => 'configuration.income-sources.index'],
        ['label' => 'Backups', 'icon' => 'bx-archive-in', 'route' => 'configuration.backups.index'],
        ['label' => 'Audit Logs', 'icon' => 'bx-clipboard', 'route' => 'configuration.auditlogs.index'],
    ];
    @endphp

    @foreach ($configItems as $item)
    <button onclick="window.location='{{ route($item['route']) }}'" {{ request()->routeIs($item['route']) ? 'class=active' : '' }}><i class="bx {{ $item['icon'] }} icon-xl me-2"></i> {{ $item['label'] }}</button>
    @endforeach
</div>
