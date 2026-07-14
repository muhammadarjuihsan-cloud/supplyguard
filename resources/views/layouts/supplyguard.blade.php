<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SupplyGuard')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/supplyguard.css') }}?v={{ filemtime(public_path('css/supplyguard.css')) }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
    <div class="sg-app">
        @include('partials.sidebar')

        <div class="sg-sidebar-overlay" id="sidebarOverlay"></div>

        <div class="sg-page">
            @include('partials.topbar')

            <main class="sg-main">
                @unless (request()->routeIs('admin.*'))
                    @if (session('success'))
                        <div class="alert alert-success sg-alert" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger sg-alert" role="alert">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif
                @endunless

                @if ($errors->any())
                    <div class="alert alert-danger sg-alert align-items-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>Data belum dapat disimpan.</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const openButton = document.getElementById('sidebarToggle');
            const closeButton = document.getElementById('sidebarClose');

            function openSidebar() {
                sidebar?.classList.add('is-open');
                overlay?.classList.add('is-visible');
                document.body.classList.add('sg-lock-scroll');
            }

            function closeSidebar() {
                sidebar?.classList.remove('is-open');
                overlay?.classList.remove('is-visible');
                document.body.classList.remove('sg-lock-scroll');
            }

            openButton?.addEventListener('click', openSidebar);
            closeButton?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
