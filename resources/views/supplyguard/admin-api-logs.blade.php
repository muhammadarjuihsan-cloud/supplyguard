@extends('layouts.supplyguard')

@section('title', 'Log API - SupplyGuard')
@section('page-title', 'Log API')
@section('page-subtitle', 'Pantau keberhasilan dan kegagalan integrasi API eksternal')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/admin-api-logs.css') }}?v={{ filemtime(public_path('css/admin-api-logs.css')) }}"
    >
@endpush

@section('content')
    @php
        $normalizeStatus = static function (?string $value): string {
            $value = strtolower(trim((string) $value));

            return match ($value) {
                'success' => 'success',
                'failed', 'failure' => 'failed',
                'error' => 'error',
                default => 'unknown',
            };
        };

        $statusLabel = [
            'success' => 'Berhasil',
            'failed' => 'Gagal',
            'error' => 'Error',
            'unknown' => 'Tidak diketahui',
        ];
    @endphp

    <section class="sg-log-page">
        <div class="sg-log-hero">
            <div>
                <span class="sg-log-eyebrow">
                    <i class="bi bi-activity"></i>
                    Administrasi Integrasi
                </span>
                <h2>Log API Eksternal</h2>
                <p>
                    Tinjau request ke layanan eksternal seperti REST Countries,
                    Open-Meteo, World Bank, GNews, dan layanan data lainnya.
                </p>
            </div>

            <div class="sg-log-update">
                <i class="bi bi-clock-history"></i>
                <div>
                    <small>Request terakhir</small>
                    <strong>
                        {{ $statistics['last_request']
                            ? \Carbon\Carbon::parse($statistics['last_request'])->format('d M Y, H:i:s')
                            : 'Belum tersedia' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="sg-log-stat-grid">
            <article class="sg-log-stat">
                <span class="sg-log-stat-icon">
                    <i class="bi bi-database"></i>
                </span>
                <div>
                    <small>Total Log</small>
                    <strong>{{ number_format($statistics['total'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-log-stat">
                <span class="sg-log-stat-icon is-success">
                    <i class="bi bi-check-circle-fill"></i>
                </span>
                <div>
                    <small>Berhasil</small>
                    <strong>{{ number_format($statistics['success'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-log-stat">
                <span class="sg-log-stat-icon is-warning">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </span>
                <div>
                    <small>Gagal</small>
                    <strong>{{ number_format($statistics['failed'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-log-stat">
                <span class="sg-log-stat-icon is-danger">
                    <i class="bi bi-x-octagon-fill"></i>
                </span>
                <div>
                    <small>Error</small>
                    <strong>{{ number_format($statistics['error'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-log-stat">
                <span class="sg-log-stat-icon">
                    <i class="bi bi-funnel-fill"></i>
                </span>
                <div>
                    <small>Hasil Filter</small>
                    <strong>{{ number_format($statistics['filtered'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <div class="sg-log-dashboard-grid">
            <article class="sg-log-panel">
                <div class="sg-log-panel-head">
                    <div>
                        <span>Filter Log</span>
                        <h3>Cari Aktivitas API</h3>
                    </div>

                    @if (
                        $keyword !== ''
                        || $apiName !== ''
                        || $status !== ''
                        || $dateFrom !== ''
                        || $dateTo !== ''
                    )
                        <a href="{{ route('admin.apiLogs.index') }}">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Bersihkan filter
                        </a>
                    @endif
                </div>

                <form method="GET" action="{{ route('admin.apiLogs.index') }}" class="sg-log-filter">
                    <div class="sg-log-field sg-log-field-search">
                        <label for="q">API, endpoint, pesan, atau kode respons</label>
                        <div class="sg-log-input-icon">
                            <i class="bi bi-search"></i>
                            <input
                                type="search"
                                id="q"
                                name="q"
                                value="{{ $keyword }}"
                                placeholder="Contoh: GNews, 403, quota"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="sg-log-field">
                        <label for="api_name">Nama API</label>
                        <select name="api_name" id="api_name">
                            <option value="">Semua API</option>
                            @foreach ($apiNames as $name)
                                <option value="{{ $name }}" @selected($apiName === $name)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sg-log-field">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Semua status</option>
                            <option value="success" @selected($status === 'success')>Berhasil</option>
                            <option value="failed" @selected($status === 'failed')>Gagal</option>
                            <option value="error" @selected($status === 'error')>Error</option>
                        </select>
                    </div>

                    <div class="sg-log-field">
                        <label for="date_from">Dari tanggal</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}">
                    </div>

                    <div class="sg-log-field">
                        <label for="date_to">Sampai tanggal</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}">
                    </div>

                    <div class="sg-log-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel-fill"></i>
                            Terapkan
                        </button>
                        <a href="{{ route('admin.apiLogs.index') }}" class="btn btn-light">
                            Reset
                        </a>
                    </div>
                </form>
            </article>

            <article class="sg-log-panel">
                <div class="sg-log-panel-head">
                    <div>
                        <span>Response HTTP</span>
                        <h3>Kelompok Kode Respons</h3>
                    </div>
                </div>

                <div class="sg-log-chart-wrap">
                    <canvas id="responseCodeChart"></canvas>
                </div>
            </article>
        </div>

        <div class="sg-log-dashboard-grid sg-log-dashboard-grid-secondary">
            <article class="sg-log-panel">
                <div class="sg-log-panel-head">
                    <div>
                        <span>Pemakaian API</span>
                        <h3>API Paling Sering Digunakan</h3>
                    </div>
                </div>

                <div class="sg-log-api-list">
                    @forelse ($apiUsage as $index => $item)
                        @php
                            $successRate = (int) $item->request_count > 0
                                ? (int) round(
                                    ((int) $item->success_count / (int) $item->request_count) * 100
                                )
                                : 0;
                        @endphp

                        <div class="sg-log-api-item">
                            <span class="sg-log-api-rank">{{ $index + 1 }}</span>
                            <div>
                                <strong>{{ $item->api_name ?: 'Tidak diketahui' }}</strong>
                                <small>{{ number_format($item->request_count, 0, ',', '.') }} request</small>
                            </div>
                            <span>{{ $successRate }}% berhasil</span>
                        </div>
                    @empty
                        <div class="sg-log-empty-small">
                            Belum ada statistik pemakaian API.
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="sg-log-panel sg-log-log-table-panel">
                <div class="sg-log-panel-head">
                    <div>
                        <span>Riwayat Request</span>
                        <h3>Daftar Log API</h3>
                    </div>

                    <span class="sg-log-result-badge">
                        Halaman {{ $logs->currentPage() }} dari {{ max(1, $logs->lastPage()) }}
                    </span>
                </div>

                @if ($logs->count() > 0)
                    <div class="table-responsive">
                        <table class="table sg-log-table align-middle">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>API</th>
                                    <th>Endpoint</th>
                                    <th>Status</th>
                                    <th>Kode</th>
                                    <th>Pesan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    @php
                                        $normalizedStatus = $normalizeStatus($log->status);
                                        $requestTime = $log->requested_at ?: $log->created_at;
                                    @endphp

                                    <tr>
                                        <td>
                                            <span class="sg-log-time">
                                                {{ $requestTime
                                                    ? \Carbon\Carbon::parse($requestTime)->format('d M Y') 
                                                    : '—' }}
                                                <small>
                                                    {{ $requestTime
                                                        ? \Carbon\Carbon::parse($requestTime)->format('H:i:s')
                                                        : '' }}
                                                </small>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="sg-log-api-name">
                                                {{ $log->api_name ?: 'Tidak diketahui' }}
                                            </strong>
                                        </td>
                                        <td>
                                            <code class="sg-log-endpoint">
                                                {{ $log->endpoint ?: '-' }}
                                            </code>
                                        </td>
                                        <td>
                                            <span class="sg-log-status {{ 'is-'.$normalizedStatus }}">
                                                {{ $statusLabel[$normalizedStatus] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="sg-log-response-code">
                                                {{ $log->response_code ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                class="sg-log-message"
                                                title="{{ $log->message }}"
                                            >
                                                {{ \Illuminate\Support\Str::limit(
                                                    $log->message ?: 'Tidak ada pesan.',
                                                    100
                                                ) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($logs->hasPages())
                        <nav class="sg-log-pagination" aria-label="Navigasi log API">
                            <ul class="pagination mb-0">
                                <li class="page-item {{ $logs->onFirstPage() ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ $logs->previousPageUrl() ?: '#' }}">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>

                                @foreach (
                                    $logs->getUrlRange(
                                        max(1, $logs->currentPage() - 2),
                                        min($logs->lastPage(), $logs->currentPage() + 2)
                                    ) as $page => $url
                                )
                                    <li class="page-item {{ $page === $logs->currentPage() ? 'active' : '' }}">
                                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                    </li>
                                @endforeach

                                <li class="page-item {{ $logs->hasMorePages() ? '' : 'disabled' }}">
                                    <a class="page-link" href="{{ $logs->nextPageUrl() ?: '#' }}">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    @endif
                @else
                    <div class="sg-log-empty">
                        <i class="bi bi-journal-x"></i>
                        <h3>Log API tidak ditemukan</h3>
                        <p>Belum ada log atau tidak ada data yang sesuai dengan filter.</p>
                        <a href="{{ route('admin.apiLogs.index') }}" class="btn btn-primary">
                            Tampilkan Semua Log
                        </a>
                    </div>
                @endif
            </article>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('responseCodeChart');

            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['2xx', '3xx', '4xx', '5xx', 'Tanpa kode'],
                    datasets: [{
                        data: [
                            {{ $responseCodeChart['2xx'] }},
                            {{ $responseCodeChart['3xx'] }},
                            {{ $responseCodeChart['4xx'] }},
                            {{ $responseCodeChart['5xx'] }},
                            {{ $responseCodeChart['unknown'] }}
                        ],
                        backgroundColor: [
                            '#18a875',
                            '#4f82d8',
                            '#e5a426',
                            '#e24d4d',
                            '#98a2b3'
                        ],
                        borderWidth: 0,
                        hoverOffset: 5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
