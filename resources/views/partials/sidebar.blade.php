<aside class="sg-sidebar" id="appSidebar">
    <div class="sg-sidebar-header">
        <a href="{{ route('dashboard') }}" class="sg-brand">
            <span class="sg-brand-mark">
                <i class="bi bi-shield-check"></i>
            </span>
            <span>
                <strong>SupplyGuard</strong>
                <small>Risk Intelligence</small>
            </span>
        </a>

        <button class="sg-sidebar-close d-lg-none" id="sidebarClose" type="button" aria-label="Tutup menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="sg-nav">
        <div class="sg-nav-group">
            <span class="sg-nav-label">Utama</span>

            <a href="{{ route('dashboard') }}"
               class="sg-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i>
                <span>Dasbor</span>
            </a>

            <a href="{{ route('comparison') }}"
               class="sg-nav-link {{ request()->routeIs('comparison') ? 'active' : '' }}">
                <i class="bi bi-columns-gap"></i>
                <span>Perbandingan Negara</span>
            </a>

            <a href="{{ route('dashboard') }}#watchlist" class="sg-nav-link">
                <i class="bi bi-bookmark"></i>
                <span>Daftar Pantauan</span>
            </a>
        </div>

        <div class="sg-nav-group">
            <span class="sg-nav-label">Monitoring</span>

            <a href="{{ route('dashboard') }}#visualisasi" class="sg-nav-link">
                <i class="bi bi-bar-chart-line"></i>
                <span>Visualisasi Data</span>
            </a>

            <a href="{{ route('dashboard') }}#pelabuhan" class="sg-nav-link">
                <i class="bi bi-geo-alt"></i>
                <span>Lokasi Pelabuhan</span>
            </a>

            <a href="{{ route('dashboard') }}#berita" class="sg-nav-link">
                <i class="bi bi-newspaper"></i>
                <span>Intelijen Berita</span>
            </a>

            <a href="{{ route('dashboard') }}#api" class="sg-nav-link">
                <i class="bi bi-braces"></i>
                <span>REST API</span>
            </a>
        </div>

        @if (auth()->user()->role === 'admin')
            <div class="sg-nav-group">
                <span class="sg-nav-label">Administrasi</span>

                <a href="{{ route('admin.index') }}"
                   class="sg-nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders2"></i>
                    <span>Admin Dashboard</span>
                </a>
            </div>
        @endif
    </nav>

    <div class="sg-sidebar-footer">
        <div class="sg-user-mini">
            <div class="sg-user-avatar">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="sg-user-meta">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Pengguna' }}</span>
            </div>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sg-logout-button">
                <i class="bi bi-box-arrow-left"></i>
                <span>Keluar</span>
            </button>
        </form>
    </div>
</aside>
