@php
    $userName = auth()->user()->name ?? 'Pengguna';
    $initial = strtoupper(substr($userName, 0, 1));
@endphp

<header class="sg-topbar">
    <div class="sg-topbar-left">
        <button type="button" class="sg-menu-button d-lg-none" id="sidebarToggle" aria-label="Buka menu">
            <i class="bi bi-list"></i>
        </button>

        <div>
            <h1>@yield('page-title', 'SupplyGuard')</h1>
            <p>@yield('page-subtitle', 'Platform pemantauan risiko rantai pasok global')</p>
        </div>
    </div>

    <div class="sg-topbar-right">
        <div class="sg-system-status d-none d-md-flex">
            <span class="sg-status-dot"></span>
            <div>
                <strong>Sistem aktif</strong>
                <small>{{ now()->format('d M Y') }}</small>
            </div>
        </div>

        <div class="dropdown">
            <button class="sg-profile-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="sg-profile-avatar">{{ $initial }}</span>
                <span class="sg-profile-copy d-none d-sm-block">
                    <strong>{{ $userName }}</strong>
                    <small>{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Pengguna' }}</small>
                </span>
                <i class="bi bi-chevron-down sg-profile-chevron"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end sg-profile-menu">
                <div class="sg-profile-menu-head">
                    <strong>{{ $userName }}</strong>
                    <span>{{ auth()->user()->email }}</span>
                </div>
                <div class="dropdown-divider"></div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi bi-box-arrow-left me-2"></i>Keluar dari akun
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
