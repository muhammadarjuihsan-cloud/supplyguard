@extends('layouts.supplyguard')

@section('title', 'Intelijen Berita - SupplyGuard')
@section('page-title', 'Intelijen Berita')
@section('page-subtitle', 'Pantau berita ekonomi dan logistik global beserta hasil analisis sentimen')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/news-intelligence.css') }}?v={{ filemtime(public_path('css/news-intelligence.css')) }}"
    >
@endpush

@section('content')
    @php
        $sentimentLabels = [
            'positive' => 'Positif',
            'neutral' => 'Netral',
            'negative' => 'Negatif',
        ];

        $sentimentClasses = [
            'positive' => 'is-positive',
            'neutral' => 'is-neutral',
            'negative' => 'is-negative',
        ];
    @endphp

    <section class="sg-news-page">
        <div class="sg-news-hero">
            <div>
                <span class="sg-news-eyebrow">
                    <i class="bi bi-newspaper"></i>
                    Pemantauan Informasi Global
                </span>
                <h2>Berita Rantai Pasok Dunia</h2>
                <p>
                    Telusuri artikel terbaru berdasarkan negara, tanggal, dan sentimen.
                    Seluruh berita yang tersimpan telah dianalisis menggunakan kamus
                    sentimen SupplyGuard.
                </p>
            </div>

            <div class="sg-news-update">
                <i class="bi bi-clock-history"></i>
                <div>
                    <small>Pembaruan data terakhir</small>
                    <strong>
                        {{ $statistics['last_updated']
                            ? \Carbon\Carbon::parse($statistics['last_updated'])->format('d M Y, H:i')
                            : 'Belum tersedia' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="sg-news-stat-grid">
            <article class="sg-news-stat">
                <span class="sg-news-stat-icon">
                    <i class="bi bi-files"></i>
                </span>
                <div>
                    <small>Total Berita</small>
                    <strong>{{ number_format($statistics['total_news'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-news-stat">
                <span class="sg-news-stat-icon">
                    <i class="bi bi-funnel"></i>
                </span>
                <div>
                    <small>Hasil Filter</small>
                    <strong>{{ number_format($statistics['filtered_news'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-news-stat">
                <span class="sg-news-stat-icon">
                    <i class="bi bi-flag"></i>
                </span>
                <div>
                    <small>Negara Terjangkau</small>
                    <strong>{{ number_format($statistics['countries_covered'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-news-stat">
                <span class="sg-news-stat-icon">
                    <i class="bi bi-building"></i>
                </span>
                <div>
                    <small>Sumber Berita</small>
                    <strong>{{ number_format($statistics['sources'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <article class="sg-news-panel">
            <div class="sg-news-panel-head">
                <div>
                    <span>Filter Data</span>
                    <h3>Cari Berita</h3>
                </div>

                @if ($keyword !== '' || $countryId > 0 || $sentiment !== '' || $dateFrom !== '' || $dateTo !== '')
                    <a href="{{ route('news.index') }}" class="sg-news-reset-link">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Bersihkan semua filter
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('news.index') }}" class="sg-news-filter">
                <div class="sg-news-field sg-news-field-search">
                    <label for="q">Judul, isi, sumber, atau negara</label>
                    <div class="sg-news-input-icon">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            name="q"
                            id="q"
                            value="{{ $keyword }}"
                            placeholder="Contoh: shipping delay, Indonesia, Reuters"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="sg-news-field">
                    <label for="country_id">Negara</label>
                    <select name="country_id" id="country_id">
                        <option value="">Semua negara</option>
                        @foreach ($countries as $country)
                            <option
                                value="{{ $country->id }}"
                                @selected((int) $countryId === (int) $country->id)
                            >
                                {{ $country->name }}
                                @if ($country->cca2)
                                    ({{ $country->cca2 }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-news-field">
                    <label for="sentiment">Sentimen</label>
                    <select name="sentiment" id="sentiment">
                        <option value="">Semua sentimen</option>
                        @foreach ($sentimentLabels as $value => $label)
                            <option value="{{ $value }}" @selected($sentiment === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-news-field">
                    <label for="date_from">Dari tanggal</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="sg-news-field">
                    <label for="date_to">Sampai tanggal</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}">
                </div>

                <div class="sg-news-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel-fill"></i>
                        Terapkan
                    </button>
                    <a href="{{ route('news.index') }}" class="btn btn-light">
                        Reset
                    </a>
                </div>
            </form>
        </article>

        <div class="sg-news-insight-grid">
            <article class="sg-news-panel sg-news-chart-panel">
                <div class="sg-news-panel-head">
                    <div>
                        <span>Analisis Otomatis</span>
                        <h3>Komposisi Sentimen Hasil Filter</h3>
                    </div>
                </div>

                <div class="sg-news-chart-content">
                    <div class="sg-news-chart-wrap">
                        <canvas id="newsSentimentChart"></canvas>
                    </div>

                    <div class="sg-news-sentiment-summary">
                        @foreach ($sentimentLabels as $key => $label)
                            <div class="sg-news-sentiment-row">
                                <span class="sg-news-sentiment-dot {{ $sentimentClasses[$key] }}"></span>
                                <div>
                                    <small>{{ $label }}</small>
                                    <strong>{{ number_format($sentimentChart[$key], 0, ',', '.') }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="sg-news-panel">
                <div class="sg-news-panel-head">
                    <div>
                        <span>Cakupan Data</span>
                        <h3>Negara dengan Berita Terbanyak</h3>
                    </div>
                </div>

                <div class="sg-news-country-list">
                    @forelse ($topCountries as $index => $country)
                        <a
                            href="{{ route('news.index', ['country_id' => $country->id]) }}"
                            class="sg-news-country-item"
                        >
                            <span class="sg-news-country-rank">{{ $index + 1 }}</span>
                            <div>
                                <strong>{{ $country->name }}</strong>
                                <small>{{ $country->cca2 ?: 'Tanpa kode negara' }}</small>
                            </div>
                            <span>{{ number_format($country->article_count, 0, ',', '.') }} artikel</span>
                        </a>
                    @empty
                        <div class="sg-news-empty-small">
                            Belum ada statistik negara.
                        </div>
                    @endforelse
                </div>
            </article>
        </div>

        <article class="sg-news-panel">
            <div class="sg-news-panel-head">
                <div>
                    <span>Daftar Artikel</span>
                    <h3>Berita Ditemukan</h3>
                </div>

                <span class="sg-news-result-badge">
                    Halaman {{ $newsItems->currentPage() }} dari {{ max(1, $newsItems->lastPage()) }}
                </span>
            </div>

            @if ($newsItems->count() > 0)
                <div class="sg-news-card-grid">
                    @foreach ($newsItems as $item)
                        @php
                            $normalizedSentiment = strtolower($item->sentiment ?? 'neutral');
                            $normalizedSentiment = array_key_exists($normalizedSentiment, $sentimentLabels)
                                ? $normalizedSentiment
                                : 'neutral';

                            $externalUrl = is_string($item->url)
                                && filter_var($item->url, FILTER_VALIDATE_URL)
                                && in_array(parse_url($item->url, PHP_URL_SCHEME), ['http', 'https'], true)
                                    ? $item->url
                                    : null;
                        @endphp

                        <article class="sg-news-article">
                            <div class="sg-news-article-top">
                                <div class="sg-news-badges">
                                    <span class="sg-news-country-badge">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        {{ $item->country_name ?: 'Global' }}
                                    </span>

                                    <span class="sg-news-sentiment-badge {{ $sentimentClasses[$normalizedSentiment] }}">
                                        {{ $sentimentLabels[$normalizedSentiment] }}
                                    </span>
                                </div>

                                <span class="sg-news-date">
                                    <i class="bi bi-calendar3"></i>
                                    {{ $item->published_at
                                        ? \Carbon\Carbon::parse($item->published_at)->format('d M Y, H:i')
                                        : 'Tanggal tidak tersedia' }}
                                </span>
                            </div>

                            <div class="sg-news-article-body">
                                <span class="sg-news-category">
                                    {{ $item->category ?: 'Umum' }}
                                </span>

                                <h4>{{ $item->title }}</h4>

                                <p>
                                    {{ \Illuminate\Support\Str::limit(
                                        $item->description ?: 'Deskripsi berita belum tersedia.',
                                        220
                                    ) }}
                                </p>
                            </div>

                            <div class="sg-news-score-grid">
                                <div>
                                    <span>Skor positif</span>
                                    <strong>{{ (int) ($item->positive_score ?? 0) }}</strong>
                                </div>
                                <div>
                                    <span>Skor negatif</span>
                                    <strong>{{ (int) ($item->negative_score ?? 0) }}</strong>
                                </div>
                            </div>

                            <div class="sg-news-article-foot">
                                <span>
                                    <i class="bi bi-building"></i>
                                    {{ $item->source_name ?: 'Sumber tidak diketahui' }}
                                </span>

                                @if ($externalUrl)
                                    <a
                                        href="{{ $externalUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Baca sumber
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @else
                                    <span class="sg-news-link-disabled">
                                        Tautan tidak tersedia
                                    </span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($newsItems->hasPages())
                    <nav class="sg-news-pagination" aria-label="Navigasi halaman berita">
                        <ul class="pagination mb-0">
                            <li class="page-item {{ $newsItems->onFirstPage() ? 'disabled' : '' }}">
                                <a
                                    class="page-link"
                                    href="{{ $newsItems->previousPageUrl() ?: '#' }}"
                                    aria-label="Halaman sebelumnya"
                                >
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            @foreach (
                                $newsItems->getUrlRange(
                                    max(1, $newsItems->currentPage() - 2),
                                    min($newsItems->lastPage(), $newsItems->currentPage() + 2)
                                ) as $page => $url
                            )
                                <li class="page-item {{ $page === $newsItems->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach

                            <li class="page-item {{ $newsItems->hasMorePages() ? '' : 'disabled' }}">
                                <a
                                    class="page-link"
                                    href="{{ $newsItems->nextPageUrl() ?: '#' }}"
                                    aria-label="Halaman berikutnya"
                                >
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                @endif
            @else
                <div class="sg-news-empty">
                    <i class="bi bi-search"></i>
                    <h3>Berita tidak ditemukan</h3>
                    <p>Tidak ada artikel yang sesuai dengan filter yang dipilih.</p>
                    <a href="{{ route('news.index') }}" class="btn btn-primary">
                        Tampilkan Semua Berita
                    </a>
                </div>
            @endif
        </article>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartCanvas = document.getElementById('newsSentimentChart');

            if (!chartCanvas || typeof Chart === 'undefined') {
                return;
            }

            new Chart(chartCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Positif', 'Netral', 'Negatif'],
                    datasets: [{
                        data: [
                            {{ $sentimentChart['positive'] }},
                            {{ $sentimentChart['neutral'] }},
                            {{ $sentimentChart['negative'] }}
                        ],
                        backgroundColor: ['#18a875', '#7b8798', '#e24d4d'],
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
                                    return `${context.label}: ${context.raw} berita`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
