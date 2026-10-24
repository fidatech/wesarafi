@php
    $user = Auth::user();
    $userName = $user?->name ?? 'User';
    $initials = collect(preg_split('/\s+/', trim($userName)))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="container-fluid navbar-shell">
        <a href="{{ route('dashboard') }}" class="app-brand-link navbar-brand-link" aria-label="WeSarafi dashboard">
            <span class="app-brand-logo"><img src="{{ asset('assets/img/wesarafi.svg') }}" alt="WeSarafi"></span>
        </a>

        <div class="company-name text-center d-none d-lg-block">
            <div class="navbar-product-name">WeSarafi <span>Accounting System</span></div>
            <div class="navbar-branch"><i class="bx bx-map-pin"></i> {{ $user?->branch?->name ?? 'All Branches' }}</div>
        </div>

        <ul class="navbar-nav ms-auto flex-row align-items-center gap-1">
            <li class="nav-item d-md-none">
                <button class="btn btn-icon btn-sm btn-outline-secondary" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
                    <i class="bx bx-menu"></i>
                </button>
            </li>

            <li class="nav-item d-none d-md-flex gap-2 me-1">
                <button class="btn btn-outline-secondary btn-sm navbar-action" id="btnFxOpen" type="button">
                    <i class="bx bx-door-open"></i><span>Opening Rates</span>
                </button>
                <button class="btn btn-primary btn-sm navbar-action" id="btnFxClose" type="button">
                    <i class="bx bx-exit"></i><span>Closing Rates</span>
                </button>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle hide-arrow navbar-icon-link" href="#" data-bs-toggle="dropdown" aria-label="DAB services">
                    <i class="bx bx-buildings"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end regulatory-menu">
                    <li><h6 class="dropdown-header">Regulatory Services</h6></li>
                    <li><a target="_blank" rel="noopener" href="https://online.dab.gov.af/auction" class="dropdown-item"><i class="bx bx-money"></i><span>DAB Auction</span></a></li>
                    <li><a target="_blank" rel="noopener" href="http://sanctionscreening.dab.gov.af:5050/" class="dropdown-item"><i class="bx bx-shield-quarter"></i><span>Sanction Screening</span></a></li>
                    <li><a target="_blank" rel="noopener" href="https://reports.fintraca.gov.af/Login.aspx" class="dropdown-item"><i class="bx bx-file"></i><span>FinTRACA Reporting</span></a></li>
                    <li><a target="_blank" rel="noopener" href="http://license.dab.gov.af:5051/" class="dropdown-item"><i class="bx bx-badge-check"></i><span>License Validation</span></a></li>
                </ul>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle hide-arrow navbar-icon-link" href="#" data-bs-toggle="dropdown" aria-label="Language">
                    <i class="bx bx-globe"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('lang.switch', 'ps') }}">پښتو</a></li>
                    <li><a class="dropdown-item" href="{{ route('lang.switch', 'fa') }}">دری</a></li>
                    <li><a class="dropdown-item" href="{{ route('lang.switch', 'en') }}">English</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown-user dropdown ms-1">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="#" data-bs-toggle="dropdown">
                    <span class="user-avatar">{{ $initials ?: 'U' }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end user-menu">
                    <li>
                        <div class="dropdown-item-text user-menu-header">
                            <span class="user-avatar user-avatar-lg">{{ $initials ?: 'U' }}</span>
                            <div><strong>{{ $userName }}</strong><small>{{ $user?->email }}</small></div>
                        </div>
                    </li>
                    <li><div class="dropdown-divider"></div></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bx bx-user"></i><span>My Profile</span></a></li>
                    <li><a class="dropdown-item" href="{{ route('configuration.settings.index') }}"><i class="bx bx-cog"></i><span>Settings</span></a></li>
                    <li><div class="dropdown-divider"></div></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="bx bx-power-off"></i><span>Logout</span></button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
