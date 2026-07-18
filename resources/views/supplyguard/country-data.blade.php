@extends('layouts.supplyguard')

@section('title', 'Data Negara - SupplyGuard')
@section('page-title', 'Data Negara')
@section('page-subtitle', 'Jelajahi profil, data logistik, berita, dan risiko seluruh negara')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/country-data.css') }}?v={{ filemtime(public_path('css/country-data.css')) }}"
    >
@endpush

@section('content')
    @php
        $riskLabels = [
            'Low' => 'Rendah',
            'Medium' => 'Sedang',
            'High' => 'Tinggi',
        ];

        $riskClass = static function (?string $level): string {
            return match ($level) {
                'Low' => 'is-low',
                'Medium' => 'is-medium',
                'High' => 'is-high',
                default => 'is-empty',
            };
        };
    @endphp

    <section class="sg-country-page">
        <div class="sg-country-hero">
            <div>
                <span class="sg-country-eyebrow">
                    <i class="bi bi-globe-asia-australia"></i>
                    Dataset Global
                </span>

                <h2>Data Negara Global</h2>

                <p>
                    Lihat identitas negara, wilayah, mata uang, jumlah
                    pelabuhan, berita, serta skor risiko terbaru dalam satu
                    halaman terstruktur.
                </p>
            </div>

            <div class="sg-country-hero-badge">
                <i class="bi bi-database-check"></i>
                <div>
                    <small>Sumber data negara</small>
                    <strong>REST Countries API v5</strong>
                    <span>
                        Diperbarui
                        {{ $statistics['last_updated_at']
                            ? \Carbon\Carbon::parse($statistics['last_updated_at'])->format('d M Y, H:i')
                            : 'belum tersedia' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="sg-country-stat-grid">
            <article class="sg-country-stat">
                <span><i class="bi bi-globe2"></i></span>
                <div>
                    <small>Total Negara</small>
                    <strong>
                        {{ number_format($statistics['total'], 0, ',', '.') }}
                    </strong>
                </div>
            </article>

            <article class="sg-country-stat">
                <span><i class="bi bi-map-fill"></i></span>
                <div>
                    <small>Wilayah Global</small>
                    <strong>
                        {{ number_format($statistics['regions'], 0, ',', '.') }}
                    </strong>
                </div>
            </article>

            <article class="sg-country-stat">
                <span><i class="bi bi-shield-check"></i></span>
                <div>
                    <small>Memiliki Skor Risiko</small>
                    <strong>
                        {{ number_format($statistics['with_risk'], 0, ',', '.') }}
                    </strong>
                </div>
            </article>

            <article class="sg-country-stat">
                <span><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <small>Memiliki Pelabuhan</small>
                    <strong>
                        {{ number_format($statistics['with_ports'], 0, ',', '.') }}
                    </strong>
                </div>
            </article>

            <article class="sg-country-stat">
                <span><i class="bi bi-newspaper"></i></span>
                <div>
                    <small>Memiliki Berita</small>
                    <strong>
                        {{ number_format($statistics['with_news'], 0, ',', '.') }}
                    </strong>
                </div>
            </article>

            <article class="sg-country-stat">
                <span><i class="bi bi-funnel-fill"></i></span>
                <div>
                    <small>Hasil Filter</small>
                    <strong>
                        {{ number_format($statistics['filtered'], 0, ',', '.') }}
                    </strong>
                </div>
            </article>
        </div>

        <article class="sg-country-panel">
            <div class="sg-country-panel-head">
                <div>
                    <span>Pencarian Dataset</span>
                    <h3>Filter Data Negara</h3>
                </div>

                @if (
                    $keyword !== ''
                    || $region !== ''
                    || $subregion !== ''
                    || $riskLevel !== ''
                    || $sort !== 'name'
                )
                    <a href="{{ route('countries.index') }}">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Bersihkan filter
                    </a>
                @endif
            </div>

            <form
                method="GET"
                action="{{ route('countries.index') }}"
                class="sg-country-filter"
            >
                <div class="sg-country-field sg-country-search">
                    <label for="q">
                        Nama, kode, ibu kota, atau mata uang
                    </label>

                    <div class="sg-country-input-icon">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="{{ $keyword }}"
                            placeholder="Contoh: Indonesia, IDN, Jakarta"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="sg-country-field">
                    <label for="region">Wilayah</label>
                    <select name="region" id="region">
                        <option value="">Semua wilayah</option>

                        @foreach ($regions as $regionItem)
                            <option
                                value="{{ $regionItem }}"
                                @selected($region === $regionItem)
                            >
                                {{ $regionItem }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-country-field">
                    <label for="subregion">Subwilayah</label>
                    <select name="subregion" id="subregion">
                        <option value="">Semua subwilayah</option>

                        @foreach ($subregions as $subregionItem)
                            <option
                                value="{{ $subregionItem }}"
                                @selected($subregion === $subregionItem)
                            >
                                {{ $subregionItem }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-country-field">
                    <label for="risk_level">Risiko</label>
                    <select name="risk_level" id="risk_level">
                        <option value="">Semua risiko</option>

                        @foreach ($riskLabels as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($riskLevel === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-country-field">
                    <label for="sort">Urutkan</label>
                    <select name="sort" id="sort">
                        <option value="name" @selected($sort === 'name')>
                            Nama A–Z
                        </option>
                        <option
                            value="risk_desc"
                            @selected($sort === 'risk_desc')
                        >
                            Risiko tertinggi
                        </option>
                        <option
                            value="ports_desc"
                            @selected($sort === 'ports_desc')
                        >
                            Pelabuhan terbanyak
                        </option>
                        <option
                            value="news_desc"
                            @selected($sort === 'news_desc')
                        >
                            Berita terbanyak
                        </option>
                    </select>
                </div>

                <div class="sg-country-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel-fill"></i>
                        Terapkan
                    </button>

                    <a
                        href="{{ route('countries.index') }}"
                        class="btn btn-light"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </article>

        <article class="sg-country-panel">
            <div class="sg-country-panel-head">
                <div>
                    <span>Dataset Negara</span>
                    <h3>Daftar Negara</h3>
                </div>

                <span class="sg-country-page-badge">
                    Halaman {{ $countries->currentPage() }}
                    dari {{ max(1, $countries->lastPage()) }}
                </span>
            </div>

            @if ($countries->count() > 0)
                <div class="table-responsive">
                    <table class="table sg-country-table align-middle">
                        <thead>
                            <tr>
                                <th>Negara</th>
                                <th>Wilayah</th>
                                <th>Ibu Kota</th>
                                <th>Mata Uang & Bahasa</th>
                                <th>Pelabuhan</th>
                                <th>Berita</th>
                                <th>Risiko</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($countries as $country)
                                <tr>
                                    <td>
                                        <div class="sg-country-name-cell">
                                            <span class="sg-country-code">
                                                {{ $country->cca2 ?: '—' }}
                                            </span>

                                            <div>
                                                <strong>{{ $country->name }}</strong>
                                                <small>
                                                    {{ $country->official_name
                                                        ?: 'Nama resmi belum tersedia' }}
                                                </small>
                                                <code>{{ $country->cca3 ?: '—' }}</code>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <strong>
                                            {{ $country->region ?: '—' }}
                                        </strong>
                                        <small class="d-block">
                                            {{ $country->subregion ?: '—' }}
                                        </small>
                                    </td>

                                    <td>
                                        {{ $country->capital ?: 'Belum tersedia' }}
                                    </td>

                                    <td>
                                        <strong>
                                            {{ $country->currency_code ?: '—' }}
                                        </strong>
                                        <small class="d-block">
                                            {{ \Illuminate\Support\Str::limit(
                                                $country->currency_name
                                                    ?: 'Mata uang belum tersedia',
                                                28
                                            ) }}
                                        </small>
                                        <small class="d-block sg-country-language">
                                            {{ \Illuminate\Support\Str::limit(
                                                $country->language
                                                    ?: 'Bahasa belum tersedia',
                                                34
                                            ) }}
                                        </small>
                                    </td>

                                    <td>
                                        <span class="sg-country-count">
                                            <i class="bi bi-geo-alt"></i>
                                            {{ number_format(
                                                (int) $country->port_count,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="sg-country-count">
                                            <i class="bi bi-newspaper"></i>
                                            {{ number_format(
                                                (int) $country->news_count,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="sg-country-risk {{ $riskClass($country->risk_level) }}"
                                        >
                                            @if ($country->risk_level)
                                                {{ $riskLabels[$country->risk_level]
                                                    ?? $country->risk_level }}
                                                ·
                                                {{ number_format(
                                                    (float) $country->total_score,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}/100
                                            @else
                                                Belum dihitung
                                            @endif
                                        </span>
                                    </td>

                                    <td>
                                        <div class="sg-country-actions">
                                            <a
                                                href="{{ route('dashboard', [
                                                    'country_id' => $country->id,
                                                ]) }}"
                                                class="sg-country-action-primary"
                                                title="Lihat analisis {{ $country->name }}"
                                            >
                                                <i class="bi bi-graph-up-arrow"></i>
                                                Analisis
                                            </a>

                                            <a
                                                href="{{ route('comparison', [
                                                    'country_a' => $country->id,
                                                ]) }}"
                                                class="sg-country-action-icon"
                                                title="Bandingkan {{ $country->name }} dengan negara lain"
                                                aria-label="Bandingkan {{ $country->name }} dengan negara lain"
                                            >
                                                <i class="bi bi-columns-gap"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($countries->hasPages())
                    <nav
                        class="sg-country-pagination"
                        aria-label="Navigasi data negara"
                    >
                        <ul class="pagination mb-0">
                            <li
                                class="page-item {{ $countries->onFirstPage()
                                    ? 'disabled'
                                    : '' }}"
                            >
                                <a
                                    class="page-link"
                                    href="{{ $countries->previousPageUrl() ?: '#' }}"
                                >
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            @foreach (
                                $countries->getUrlRange(
                                    max(1, $countries->currentPage() - 2),
                                    min(
                                        $countries->lastPage(),
                                        $countries->currentPage() + 2
                                    )
                                ) as $page => $url
                            )
                                <li
                                    class="page-item {{ $page
                                        === $countries->currentPage()
                                            ? 'active'
                                            : '' }}"
                                >
                                    <a class="page-link" href="{{ $url }}">
                                        {{ $page }}
                                    </a>
                                </li>
                            @endforeach

                            <li
                                class="page-item {{ $countries->hasMorePages()
                                    ? ''
                                    : 'disabled' }}"
                            >
                                <a
                                    class="page-link"
                                    href="{{ $countries->nextPageUrl() ?: '#' }}"
                                >
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                @endif
            @else
                <div class="sg-country-empty">
                    <i class="bi bi-search"></i>
                    <h3>Negara tidak ditemukan</h3>
                    <p>
                        Tidak ada negara yang sesuai dengan filter yang dipilih.
                    </p>
                    <a
                        href="{{ route('countries.index') }}"
                        class="btn btn-primary"
                    >
                        Tampilkan Semua Negara
                    </a>
                </div>
            @endif
        </article>
    </section>
@endsection
