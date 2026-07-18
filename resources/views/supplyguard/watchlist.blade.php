@extends('layouts.supplyguard')

@section('title', 'Daftar Pantauan - SupplyGuard')
@section('page-title', 'Daftar Pantauan')
@section('page-subtitle', 'Pantau negara prioritas dan perubahan tingkat risiko rantai pasok')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/watchlist.css') }}?v={{ filemtime(public_path('css/watchlist.css')) }}"
    >
@endpush

@section('content')
    @php
        $riskLabels = [
            'High' => 'Tinggi',
            'Medium' => 'Sedang',
            'Low' => 'Rendah',
        ];

        $riskClasses = [
            'High' => 'is-high',
            'Medium' => 'is-medium',
            'Low' => 'is-low',
        ];
    @endphp

    <section class="sg-watch-page">
        <div class="sg-watch-hero">
            <div>
                <span class="sg-watch-eyebrow">
                    <i class="bi bi-bookmark-star-fill"></i>
                    Pemantauan Prioritas
                </span>
                <h2>Negara dalam Daftar Pantauan</h2>
                <p>
                    Simpan negara yang paling penting, bandingkan tingkat risikonya,
                    tambahkan catatan, dan buka detail analisis langsung dari satu halaman.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-primary sg-watch-add-button"
                data-bs-toggle="modal"
                data-bs-target="#addWatchlistModal"
                @disabled($availableCountries->isEmpty())
            >
                <i class="bi bi-plus-lg"></i>
                Tambah Negara
            </button>
        </div>

        <div class="sg-watch-stat-grid">
            <article class="sg-watch-stat">
                <span class="sg-watch-stat-icon">
                    <i class="bi bi-bookmarks-fill"></i>
                </span>
                <div>
                    <small>Total Pantauan</small>
                    <strong>{{ number_format($statistics['total'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-watch-stat">
                <span class="sg-watch-stat-icon is-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </span>
                <div>
                    <small>Risiko Tinggi</small>
                    <strong>{{ number_format($statistics['high'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-watch-stat">
                <span class="sg-watch-stat-icon is-warning">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </span>
                <div>
                    <small>Risiko Sedang</small>
                    <strong>{{ number_format($statistics['medium'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-watch-stat">
                <span class="sg-watch-stat-icon is-success">
                    <i class="bi bi-shield-check"></i>
                </span>
                <div>
                    <small>Rata-rata Skor</small>
                    <strong>
                        {{ $statistics['average_score'] !== null
                            ? $statistics['average_score'].'/100'
                            : '—' }}
                    </strong>
                </div>
            </article>
        </div>

        <div class="sg-watch-main-grid">
            <article class="sg-watch-panel">
                <div class="sg-watch-panel-head">
                    <div>
                        <span>Filter Data</span>
                        <h3>Cari Negara Pantauan</h3>
                    </div>

                    @if ($keyword !== '' || $riskLevel !== '')
                        <a href="{{ route('watchlist.index') }}" class="sg-watch-reset-link">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Bersihkan filter
                        </a>
                    @endif
                </div>

                <form method="GET" action="{{ route('watchlist.index') }}" class="sg-watch-filter">
                    <div class="sg-watch-field sg-watch-field-search">
                        <label for="q">Negara, wilayah, kode, atau catatan</label>
                        <div class="sg-watch-input-icon">
                            <i class="bi bi-search"></i>
                            <input
                                type="search"
                                id="q"
                                name="q"
                                value="{{ $keyword }}"
                                placeholder="Contoh: Indonesia, Asia, IDN"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="sg-watch-field">
                        <label for="risk_level">Tingkat risiko</label>
                        <select name="risk_level" id="risk_level">
                            <option value="">Semua tingkat</option>
                            @foreach ($riskLabels as $value => $label)
                                <option value="{{ $value }}" @selected($riskLevel === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sg-watch-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel-fill"></i>
                            Terapkan
                        </button>
                        <a href="{{ route('watchlist.index') }}" class="btn btn-light">
                            Reset
                        </a>
                    </div>
                </form>
            </article>

            <article class="sg-watch-panel sg-watch-distribution">
                <div class="sg-watch-panel-head">
                    <div>
                        <span>Ringkasan Risiko</span>
                        <h3>Distribusi Pantauan</h3>
                    </div>
                </div>

                <div class="sg-watch-distribution-body">
                    <div class="sg-watch-chart-wrap">
                        <canvas id="watchlistRiskChart"></canvas>
                    </div>

                    <div class="sg-watch-legend">
                        <div>
                            <span class="sg-watch-dot is-high"></span>
                            <small>Tinggi</small>
                            <strong>{{ $statistics['high'] }}</strong>
                        </div>
                        <div>
                            <span class="sg-watch-dot is-medium"></span>
                            <small>Sedang</small>
                            <strong>{{ $statistics['medium'] }}</strong>
                        </div>
                        <div>
                            <span class="sg-watch-dot is-low"></span>
                            <small>Rendah</small>
                            <strong>{{ $statistics['low'] }}</strong>
                        </div>
                        @if ($statistics['unavailable'] > 0)
                            <div>
                                <span class="sg-watch-dot is-empty"></span>
                                <small>Belum tersedia</small>
                                <strong>{{ $statistics['unavailable'] }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        </div>

        <article class="sg-watch-panel">
            <div class="sg-watch-panel-head">
                <div>
                    <span>Negara Prioritas</span>
                    <h3>Daftar Pantauan Saya</h3>
                </div>

                <span class="sg-watch-result-badge">
                    {{ number_format($watchlists->total(), 0, ',', '.') }} negara ditemukan
                </span>
            </div>

            @if ($watchlists->count() > 0)
                <div class="sg-watch-card-grid">
                    @foreach ($watchlists as $item)
                        @php
                            $riskClass = $riskClasses[$item->risk_level] ?? 'is-unavailable';
                            $riskLabel = $riskLabels[$item->risk_level] ?? 'Belum dihitung';
                            $hasRisk = $item->total_score !== null;
                        @endphp

                        <article class="sg-watch-country-card">
                            <div class="sg-watch-country-head">
                                <div class="sg-watch-country-title">
                                    <span class="sg-watch-flag">
                                        {{ strtoupper($item->cca2 ?: substr($item->country_name, 0, 2)) }}
                                    </span>
                                    <div>
                                        <h4>{{ $item->country_name }}</h4>
                                        <span>
                                            {{ $item->region ?: 'Wilayah belum tersedia' }}
                                            @if ($item->subregion)
                                                · {{ $item->subregion }}
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <span class="sg-watch-risk-badge {{ $riskClass }}">
                                    {{ $riskLabel }}
                                </span>
                            </div>

                            <div class="sg-watch-score-block">
                                <div>
                                    <small>Skor risiko terbaru</small>
                                    <strong>{{ $hasRisk ? (int) $item->total_score.'/100' : '—' }}</strong>
                                </div>

                                <span>
                                    {{ $item->score_date
                                        ? \Carbon\Carbon::parse($item->score_date)->format('d M Y')
                                        : 'Belum dihitung' }}
                                </span>
                            </div>

                            <div class="sg-watch-component-grid">
                                <div>
                                    <small>Cuaca</small>
                                    <strong>{{ $item->weather_score !== null ? (int) $item->weather_score : '—' }}</strong>
                                </div>
                                <div>
                                    <small>Inflasi</small>
                                    <strong>{{ $item->inflation_score !== null ? (int) $item->inflation_score : '—' }}</strong>
                                </div>
                                <div>
                                    <small>Kurs</small>
                                    <strong>{{ $item->currency_score !== null ? (int) $item->currency_score : '—' }}</strong>
                                </div>
                                <div>
                                    <small>Berita</small>
                                    <strong>{{ $item->news_score !== null ? (int) $item->news_score : '—' }}</strong>
                                </div>
                                <div>
                                    <small>Pelabuhan</small>
                                    <strong>{{ $item->port_score !== null ? (int) $item->port_score : '—' }}</strong>
                                </div>
                            </div>

                            <div class="sg-watch-data-summary">
                                <span>
                                    <i class="bi bi-geo-alt"></i>
                                    {{ number_format((int) $item->port_count, 0, ',', '.') }} pelabuhan
                                </span>
                                <span>
                                    <i class="bi bi-newspaper"></i>
                                    {{ number_format((int) $item->news_count, 0, ',', '.') }} berita
                                </span>
                                <span>
                                    <i class="bi bi-cash-coin"></i>
                                    {{ $item->currency_code ?: '-' }}
                                </span>
                            </div>

                            <form
                                method="POST"
                                action="{{ route('watchlist.note.update', $item->country_id) }}"
                                class="sg-watch-note-form"
                            >
                                @csrf
                                @method('PATCH')

                                <label for="note-{{ $item->country_id }}">
                                    <i class="bi bi-sticky"></i>
                                    Catatan pantauan
                                </label>

                                <div>
                                    <input
                                        type="text"
                                        id="note-{{ $item->country_id }}"
                                        name="note"
                                        value="{{ $item->note }}"
                                        maxlength="255"
                                        placeholder="Tambahkan alasan atau fokus pemantauan"
                                    >
                                    <button type="submit" title="Simpan catatan">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </div>
                            </form>

                            <div class="sg-watch-country-actions">
                                <a
                                    href="{{ route('dashboard', ['country_id' => $item->country_id]) }}"
                                    class="sg-watch-detail-link"
                                >
                                    <i class="bi bi-eye"></i>
                                    Lihat Analisis
                                </a>

                                <a
                                    href="{{ route('comparison', ['country_a' => $item->country_id]) }}"
                                    class="sg-watch-compare-link"
                                >
                                    <i class="bi bi-columns-gap"></i>
                                    Bandingkan
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('watchlist.destroyByCountry', $item->country_id) }}"
                                    onsubmit="return confirm('Hapus {{ addslashes($item->country_name) }} dari daftar pantauan?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="sg-watch-delete-button" title="Hapus pantauan">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($watchlists->hasPages())
                    <nav class="sg-watch-pagination" aria-label="Navigasi daftar pantauan">
                        <ul class="pagination mb-0">
                            <li class="page-item {{ $watchlists->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $watchlists->previousPageUrl() ?: '#' }}">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            @foreach (
                                $watchlists->getUrlRange(
                                    max(1, $watchlists->currentPage() - 2),
                                    min($watchlists->lastPage(), $watchlists->currentPage() + 2)
                                ) as $page => $url
                            )
                                <li class="page-item {{ $page === $watchlists->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach

                            <li class="page-item {{ $watchlists->hasMorePages() ? '' : 'disabled' }}">
                                <a class="page-link" href="{{ $watchlists->nextPageUrl() ?: '#' }}">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                @endif
            @else
                <div class="sg-watch-empty">
                    <i class="bi bi-bookmark-plus"></i>
                    <h3>Belum ada negara yang cocok</h3>
                    <p>
                        Tambahkan negara baru atau ubah filter untuk melihat daftar pantauan.
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#addWatchlistModal"
                        @disabled($availableCountries->isEmpty())
                    >
                        Tambah Negara
                    </button>
                </div>
            @endif
        </article>
    </section>

    <div class="modal fade" id="addWatchlistModal" tabindex="-1" aria-labelledby="addWatchlistModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content sg-watch-modal">
                <div class="modal-header">
                    <div>
                        <span>Daftar Pantauan</span>
                        <h5 class="modal-title" id="addWatchlistModalLabel">Tambah Negara</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form method="POST" action="{{ route('watchlist.store') }}">
                    @csrf

                    <div class="modal-body">
                        @if ($availableCountries->isEmpty())
                            <div class="alert alert-info mb-0">
                                Semua negara sudah berada dalam daftar pantauan.
                            </div>
                        @else
                            <div class="sg-watch-modal-field">
                                <label for="new-country-id">Negara</label>
                                <select id="new-country-id" name="country_id" required>
                                    <option value="">Pilih negara</option>
                                    @foreach ($availableCountries as $country)
                                        <option value="{{ $country->id }}">
                                            {{ $country->name }}
                                            @if ($country->cca3)
                                                ({{ $country->cca3 }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sg-watch-modal-field">
                                <label for="new-note">Catatan awal</label>
                                <textarea
                                    id="new-note"
                                    name="note"
                                    rows="3"
                                    maxlength="255"
                                    placeholder="Contoh: pemasok utama dan jalur impor prioritas"
                                ></textarea>
                                <small>Maksimal 255 karakter.</small>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            @disabled($availableCountries->isEmpty())
                        >
                            <i class="bi bi-bookmark-plus"></i>
                            Tambahkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('watchlistRiskChart');

            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Tinggi', 'Sedang', 'Rendah', 'Belum tersedia'],
                    datasets: [{
                        data: [
                            {{ $statistics['high'] }},
                            {{ $statistics['medium'] }},
                            {{ $statistics['low'] }},
                            {{ $statistics['unavailable'] }}
                        ],
                        backgroundColor: ['#e24d4d', '#e5a426', '#18a875', '#98a2b3'],
                        borderWidth: 0,
                        hoverOffset: 5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `${context.label}: ${context.raw} negara`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
