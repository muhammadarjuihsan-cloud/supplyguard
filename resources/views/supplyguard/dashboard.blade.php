@extends('layouts.supplyguard')

@section('title', 'Dasbor - SupplyGuard')
@section('page-title', 'Dasbor Negara Global')
@section('page-subtitle', 'Pantau indikator ekonomi, cuaca, kurs, berita, dan risiko rantai pasok')

@push('styles')
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>
        .sg-map-card {
            position: relative;
            overflow: hidden;
        }

        #portMap {
            width: 100% !important;
            height: 430px !important;
            min-height: 430px !important;
            position: relative !important;
            display: block !important;
            overflow: hidden !important;
            border-radius: 10px;
            background: #e7edf1;
            z-index: 1;
        }

        #portMap.leaflet-container {
            width: 100% !important;
            height: 430px !important;
            min-height: 430px !important;
            font-family: "Inter", Arial, sans-serif;
        }

        #portMap img,
        #portMap .leaflet-tile,
        #portMap .leaflet-marker-icon,
        #portMap .leaflet-marker-shadow {
            max-width: none !important;
            max-height: none !important;
        }

        #portMap .leaflet-control-zoom a {
            color: #263444;
        }

        .sg-risk-unavailable {
            background: #eef1f4;
            color: #6b7785;
        }

        .sg-risk-dot.is-unavailable {
            background: #98a4af;
        }

        /* Status kelengkapan data negara */
        .sg-data-status-card {
            margin-bottom: 18px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            background: #ffffff;
            border: 1px solid var(--sg-border-soft);
            border-radius: var(--sg-radius);
            box-shadow: var(--sg-shadow);
        }

        .sg-data-status-main {
            min-width: 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .sg-data-status-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 9px;
            background: var(--sg-teal-100);
            color: var(--sg-teal-700);
            font-size: 17px;
        }

        .sg-data-status-copy {
            min-width: 0;
        }

        .sg-data-status-title {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .sg-data-status-title strong {
            font-size: 13px;
        }

        .sg-data-status-percentage {
            padding: 3px 8px;
            border-radius: 999px;
            background: #eef5f4;
            color: var(--sg-teal-700);
            font-size: 9px;
            font-weight: 700;
        }

        .sg-data-status-copy p {
            margin: 4px 0 9px;
            color: var(--sg-text-muted);
            font-size: 10px;
        }

        .sg-missing-data {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            color: var(--sg-text-muted);
            font-size: 9px;
        }

        .sg-missing-data-label {
            font-weight: 600;
        }

        .sg-missing-data-pill {
            padding: 4px 7px;
            border-radius: 999px;
            background: #f1f3f5;
            color: #65717d;
            font-weight: 600;
        }

        .sg-data-complete-message {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--sg-success);
            font-size: 10px;
            font-weight: 600;
        }

        .sg-data-progress {
            width: 220px;
            flex: 0 0 auto;
        }

        .sg-data-progress-track {
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background: #edf1f4;
        }

        .sg-data-progress-bar {
            height: 100%;
            display: block;
            border-radius: inherit;
            background: var(--sg-teal-600);
            transition: width .25s ease;
        }

        .sg-data-progress small {
            margin-top: 7px;
            display: block;
            color: var(--sg-text-muted);
            font-size: 9px;
            text-align: right;
        }

        @media (max-width: 767.98px) {
            .sg-data-status-card {
                align-items: stretch;
                flex-direction: column;
                gap: 14px;
            }

            .sg-data-progress {
                width: 100%;
            }

            .sg-data-progress small {
                text-align: left;
            }
            #portMap,
            #portMap.leaflet-container {
                height: 330px !important;
                min-height: 330px !important;
            }
        }
    </style>
@endpush

@section('content')
    @php
        /*
         * Negara yang belum memiliki hasil Risk Scoring Engine
         * tidak boleh dianggap otomatis sebagai risiko rendah.
         */
        $hasRisk = $risk !== null && $risk->total_score !== null;

        $riskLevel = $hasRisk
            ? $risk->risk_level
            : null;

        $riskLabel = match ($riskLevel) {
            'High' => 'Risiko Tinggi',
            'Medium' => 'Risiko Sedang',
            'Low' => 'Risiko Rendah',
            default => 'Belum dihitung',
        };

        $riskClass = match ($riskLevel) {
            'High' => 'sg-risk-high',
            'Medium' => 'sg-risk-medium',
            'Low' => 'sg-risk-low',
            default => 'sg-risk-unavailable',
        };

        $riskDotClass = match ($riskLevel) {
            'High' => 'is-high',
            'Medium' => 'is-medium',
            'Low' => 'is-low',
            default => 'is-unavailable',
        };

        $dataStatusLabel = match ($dataStatus ?? 'limited') {
            'complete' => 'Data lengkap',
            'partial' => 'Data sebagian',
            default => 'Data terbatas',
        };

        $dataStatusIcon = match ($dataStatus ?? 'limited') {
            'complete' => 'bi-check-circle',
            'partial' => 'bi-exclamation-circle',
            default => 'bi-info-circle',
        };

        $gdpTrillion = $economyLatest && $economyLatest->gdp
            ? number_format($economyLatest->gdp / 1000000000000, 2, ',', '.')
            : null;
        $currencyChange = $currency && $currency->change_percent !== null
            ? (float) $currency->change_percent
            : null;

        $economyLabels = $economyTrend->pluck('year')->values();
        $gdpValues = $economyTrend->map(function ($row) {
            return $row->gdp ? round($row->gdp / 1000000000000, 2) : null;
        })->values();
        $inflationValues = $economyTrend->pluck('inflation')->values();
        $riskLabels = $riskTrend->pluck('recorded_date')->values();
        $riskValues = $riskTrend->pluck('total_score')->values();
        $currencyLabels = $currencyTrend->pluck('rate_date')->values();
        $currencyValues = $currencyTrend->pluck('rate')->values();
        $portMapData = $ports->map(function ($port) {
            return [
                'id' => $port->id,
                'name' => $port->name,
                'code' => $port->port_code,
                'type' => $port->type,
                'latitude' => $port->latitude !== null ? (float) $port->latitude : null,
                'longitude' => $port->longitude !== null ? (float) $port->longitude : null,
            ];
        })->values();
    @endphp

    <section class="sg-toolbar sg-country-toolbar">
        <div class="sg-toolbar-copy">
            <span class="sg-eyebrow">Negara aktif</span>
            <h2>{{ $country->name ?? 'Belum dipilih' }}</h2>
            <p>
                {{ $country->region ?? 'Wilayah belum tersedia' }}
                @if ($country && $country->capital)
                    · Ibu kota {{ $country->capital }}
                @endif
            </p>
        </div>

        <div class="sg-toolbar-actions sg-country-actions">
            <form method="GET" action="{{ route('dashboard') }}" class="sg-country-select-form">
                <label for="country_id" class="visually-hidden">Pilih negara</label>
                <select name="country_id" id="country_id" class="form-select" onchange="this.form.submit()">
                    @foreach ($countries as $item)
                        <option value="{{ $item->id }}" {{ (int) $selectedCountryId === (int) $item->id ? 'selected' : '' }}>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </form>

            @if ($country)
                @if ($isWatched)
                    <form method="POST" action="{{ route('watchlist.destroyByCountry', $selectedCountryId) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sg-btn-danger-soft">
                            <i class="bi bi-bookmark-x"></i>
                            Hapus pantauan
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('watchlist.store') }}">
                        @csrf
                        <input type="hidden" name="country_id" value="{{ $selectedCountryId }}">
                        <button type="submit" class="sg-btn-primary">
                            <i class="bi bi-bookmark-plus"></i>
                            Tambah pantauan
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </section>

    <section class="sg-data-status-card">
        <div class="sg-data-status-main">
            <span class="sg-data-status-icon">
                <i class="bi {{ $dataStatusIcon }}"></i>
            </span>

            <div class="sg-data-status-copy">
                <div class="sg-data-status-title">
                    <strong>{{ $dataStatusLabel }}</strong>
                    <span class="sg-data-status-percentage">
                        {{ $dataCompletionPercentage ?? 0 }}%
                    </span>
                </div>

                <p>
                    {{ $availableDataCount ?? 0 }} dari
                    {{ $totalDataSources ?? 6 }} sumber data utama
                    tersedia untuk
                    <strong>{{ $country->name ?? 'negara ini' }}</strong>.
                </p>

                @if (($missingData ?? collect())->isNotEmpty())
                    <div class="sg-missing-data">
                        <span class="sg-missing-data-label">
                            Belum tersedia:
                        </span>

                        @foreach ($missingData as $item)
                            <span class="sg-missing-data-pill">
                                {{ $item }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="sg-data-complete-message">
                        <i class="bi bi-check-circle"></i>
                        Semua data utama sudah tersedia.
                    </div>
                @endif
            </div>
        </div>

        <div class="sg-data-progress">
            <div class="sg-data-progress-track">
                <span
                    class="sg-data-progress-bar"
                    style="width: {{ min(100, max(0, $dataCompletionPercentage ?? 0)) }}%"
                ></span>
            </div>

            <small>
                @if (!empty($lastUpdatedAt))
                    Terakhir diperbarui:
                    {{ \Illuminate\Support\Carbon::parse($lastUpdatedAt)->format('d M Y H:i') }}
                @else
                    Belum pernah diperbarui
                @endif
            </small>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="sg-metric-card">
                <div class="sg-metric-top">
                    <span class="sg-metric-label">Skor Risiko</span>
                    <span class="sg-metric-icon"><i class="bi bi-shield-exclamation"></i></span>
                </div>
                <div>
                    @if ($hasRisk)
                        <div class="sg-metric-value">
                            {{ number_format((float) $risk->total_score, 0, ',', '.') }}
                            <small>/100</small>
                        </div>

                        <span class="sg-risk-pill {{ $riskClass }}">
                            <span class="sg-risk-dot {{ $riskDotClass }}"></span>
                            {{ $riskLabel }}
                        </span>
                    @else
                        <div class="sg-metric-value">—</div>

                        <span class="sg-risk-pill sg-risk-unavailable">
                            <span class="sg-risk-dot is-unavailable"></span>
                            Belum dihitung
                        </span>
                    @endif
                </div>
            </article>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <article class="sg-metric-card">
                <div class="sg-metric-top">
                    <span class="sg-metric-label">Produk Domestik Bruto</span>
                    <span class="sg-metric-icon"><i class="bi bi-bank"></i></span>
                </div>
                <div>
                    <div class="sg-metric-value">{{ $gdpTrillion ? '$'.$gdpTrillion : '-' }}</div>
                    <div class="sg-metric-foot">{{ $gdpTrillion ? 'Triliun USD' : 'Data belum tersedia' }} · {{ $economyLatest->year ?? '-' }}</div>
                </div>
            </article>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <article class="sg-metric-card">
                <div class="sg-metric-top">
                    <span class="sg-metric-label">Tingkat Inflasi</span>
                    <span class="sg-metric-icon"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
                <div>
                    <div class="sg-metric-value">
                        {{ $economyLatest && $economyLatest->inflation !== null ? number_format($economyLatest->inflation, 2, ',', '.') : '-' }}<small>%</small>
                    </div>
                    <div class="sg-metric-foot">
                        Populasi {{ $economyLatest && $economyLatest->population ? number_format($economyLatest->population, 0, ',', '.') : '-' }}
                    </div>
                </div>
            </article>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <article class="sg-metric-card">
                <div class="sg-metric-top">
                    <span class="sg-metric-label">Nilai Tukar</span>
                    <span class="sg-metric-icon"><i class="bi bi-currency-exchange"></i></span>
                </div>
                <div>
                    <div class="sg-metric-value sg-metric-value-compact">
                        {{ $currency && $currency->rate !== null ? number_format($currency->rate, 3, ',', '.') : '-' }}
                        <small>{{ $currency->target_currency ?? '' }}</small>
                    </div>
                    <div class="sg-metric-foot">
                        1 {{ $currency->base_currency ?? 'USD' }}
                        @if ($currencyChange !== null)
                            · <span class="{{ $currencyChange > 0 ? 'text-danger' : ($currencyChange < 0 ? 'text-success' : '') }}">
                                {{ $currencyChange > 0 ? '+' : '' }}{{ number_format($currencyChange, 2, ',', '.') }}%
                            </span>
                        @endif
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="sg-section">
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <article class="sg-card">
                    <div class="sg-card-header">
                        <div>
                            <h3>Tren Ekonomi</h3>
                            <p>Perubahan PDB dan inflasi berdasarkan data tahunan.</p>
                        </div>
                        <span class="sg-card-meta">{{ $country->name ?? '-' }}</span>
                    </div>
                    <div class="sg-card-body">
                        <div class="sg-chart-wrap sg-chart-wrap-large">
                            <canvas id="economyChart"></canvas>
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-12 col-xl-4">
                <div class="row g-3 h-100">
                    <div class="col-12 col-md-6 col-xl-12">
                        <article class="sg-card sg-compact-card">
                            <div class="sg-card-header">
                                <div>
                                    <h3>Kondisi Cuaca</h3>
                                    <p>Data cuaca terakhir yang tersimpan.</p>
                                </div>
                                <span class="sg-weather-icon"><i class="bi bi-cloud-sun"></i></span>
                            </div>
                            <div class="sg-card-body">
                                <div class="sg-weather-main">
                                    <strong>{{ $weather && $weather->temperature !== null ? number_format($weather->temperature, 1, ',', '.') : '-' }}°C</strong>
                                    <span>{{ $weather->weather_status ?? 'Status belum tersedia' }}</span>
                                </div>
                                <div class="sg-detail-grid">
                                    <div>
                                        <span>Curah hujan</span>
                                        <strong>{{ $weather && $weather->rainfall !== null ? number_format($weather->rainfall, 1, ',', '.') : '-' }} mm</strong>
                                    </div>
                                    <div>
                                        <span>Kecepatan angin</span>
                                        <strong>{{ $weather && $weather->wind_speed !== null ? number_format($weather->wind_speed, 1, ',', '.') : '-' }} km/jam</strong>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-md-6 col-xl-12">
                        <article class="sg-card sg-compact-card">
                            <div class="sg-card-header">
                                <div>
                                    <h3>Rekomendasi Sistem</h3>
                                    <p>Ringkasan keputusan berdasarkan skor saat ini.</p>
                                </div>
                                <span class="sg-risk-pill {{ $riskClass }}">{{ $riskLabel }}</span>
                            </div>
                            <div class="sg-card-body">
                                <p class="sg-recommendation">
                                    {{ $risk->recommendation ?? 'Rekomendasi belum tersedia. Periksa kembali kelengkapan data indikator.' }}
                                </p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="sg-section">
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <article class="sg-card">
                    <div class="sg-card-header">
                        <div>
                            <h3>Komponen Skor Risiko</h3>
                            <p>Lima indikator utama yang membentuk skor total SupplyGuard.</p>
                        </div>
                        <span class="sg-score-total">{{ $hasRisk ? number_format((float) $risk->total_score, 0, ',', '.').'/100' : '—' }}</span>
                    </div>
                    <div class="sg-card-body">
                        <div class="sg-risk-components" id="riskComponents">
                            @if (!$hasRisk)
                                <div class="sg-empty-state">
                                    <i class="bi bi-calculator"></i>
                                    <strong>Skor risiko belum dihitung</strong>
                                    <span>
                                        Risk Scoring Engine belum memiliki hasil untuk negara ini.
                                    </span>
                                </div>
                            @else
                                @foreach ([
                                ['label' => 'Cuaca', 'weight' => '25%', 'field' => 'weather_score', 'icon' => 'bi-cloud-lightning-rain'],
                                ['label' => 'Inflasi', 'weight' => '25%', 'field' => 'inflation_score', 'icon' => 'bi-graph-up'],
                                ['label' => 'Mata uang', 'weight' => '20%', 'field' => 'currency_score', 'icon' => 'bi-currency-exchange'],
                                ['label' => 'Sentimen berita', 'weight' => '20%', 'field' => 'news_score', 'icon' => 'bi-newspaper'],
                                ['label' => 'Ketersediaan pelabuhan', 'weight' => '10%', 'field' => 'port_score', 'icon' => 'bi-geo-alt'],
                            ] as $component)
                                @php $componentValue = (int) ($risk->{$component['field']} ?? 0); @endphp
                                <div class="sg-risk-row" data-risk-field="{{ $component['field'] }}">
                                    <span class="sg-risk-row-icon"><i class="bi {{ $component['icon'] }}"></i></span>
                                    <div class="sg-risk-row-content">
                                        <div class="sg-risk-row-head">
                                            <span>{{ $component['label'] }} <small>bobot {{ $component['weight'] }}</small></span>
                                            <strong class="sg-risk-value">{{ $componentValue }}/100</strong>
                                        </div>
                                        <div class="sg-progress-track">
                                            <span class="sg-progress-bar" style="width: {{ min(100, max(0, $componentValue)) }}%"></span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-12 col-xl-5">
                <article class="sg-card">
                    <div class="sg-card-header">
                        <div>
                            <h3>Tren Skor Risiko</h3>
                            <p>Riwayat perubahan tingkat risiko negara.</p>
                        </div>
                    </div>
                    <div class="sg-card-body">
                        <div class="sg-chart-wrap">
                            <canvas id="riskChart"></canvas>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="sg-section" id="watchlist">
        <div class="sg-section-head">
            <div>
                <h2>Daftar Pantauan</h2>
                <p>Negara yang disimpan untuk dipantau secara berkala.</p>
            </div>
            <span class="sg-section-count">{{ $watchlists->count() }} negara</span>
        </div>

        @if ($watchlists->isEmpty())
            <article class="sg-card">
                <div class="sg-empty-state">
                    <i class="bi bi-bookmark"></i>
                    <strong>Belum ada negara dalam daftar pantauan</strong>
                    <span>Pilih negara pada bagian atas, kemudian tekan tombol “Tambah pantauan”.</span>
                </div>
            </article>
        @else
            <div class="row g-3">
                @foreach ($watchlists as $item)
                    @php
                        $watchHasRisk =
                            $item->risk_level !== null &&
                            $item->total_score !== null;

                        $watchClass = match ($item->risk_level) {
                            'High' => 'sg-risk-high',
                            'Medium' => 'sg-risk-medium',
                            'Low' => 'sg-risk-low',
                            default => 'sg-risk-unavailable',
                        };

                        $watchLabel = match ($item->risk_level) {
                            'High' => 'Tinggi',
                            'Medium' => 'Sedang',
                            'Low' => 'Rendah',
                            default => 'Belum dihitung',
                        };
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <article class="sg-watch-card">
                            <div class="sg-watch-card-head">
                                <div>
                                    <h3>{{ $item->country_name }}</h3>
                                    <span>{{ $item->currency_code ?? '-' }}</span>
                                </div>
                                <span class="sg-risk-pill {{ $watchClass }}">{{ $watchLabel }}</span>
                            </div>
                            <div class="sg-watch-score">
                                <span>Skor risiko</span>
                                <strong>{{ $watchHasRisk ? number_format((float) $item->total_score, 0, ',', '.').'/100' : '—' }}</strong>
                            </div>
                            <div class="sg-watch-actions">
                                <a href="{{ route('dashboard', ['country_id' => $item->country_id]) }}" class="sg-btn-secondary">
                                    <i class="bi bi-eye"></i>Lihat detail
                                </a>
                                <form method="POST" action="{{ route('watchlist.destroyByCountry', $item->country_id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="sg-icon-button sg-icon-button-danger" title="Hapus dari pantauan">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="sg-section" id="visualisasi">
        <div class="sg-section-head">
            <div>
                <h2>Visualisasi Data</h2>
                <p>Pergerakan nilai tukar dan indikator risiko dalam bentuk grafik.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <article class="sg-card">
                    <div class="sg-card-header">
                        <div>
                            <h3>Riwayat Nilai Tukar</h3>
                            <p>{{ $currency->base_currency ?? 'USD' }} terhadap {{ $country->currency_code ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="sg-card-body">
                        <div class="sg-chart-wrap"><canvas id="currencyChart"></canvas></div>
                    </div>
                </article>
            </div>
            <div class="col-12 col-xl-6">
                <article class="sg-card">
                    <div class="sg-card-header">
                        <div>
                            <h3>Ringkasan Perdagangan</h3>
                            <p>Nilai ekspor dan impor berdasarkan data ekonomi terakhir.</p>
                        </div>
                    </div>
                    <div class="sg-card-body">
                        <div class="sg-trade-summary">
                            <div>
                                <span>Ekspor</span>
                                <strong>
                                    {{ $economyLatest && $economyLatest->exports ? '$'.number_format($economyLatest->exports / 1000000000, 2, ',', '.').' M' : '-' }}
                                </strong>
                            </div>
                            <div>
                                <span>Impor</span>
                                <strong>
                                    {{ $economyLatest && $economyLatest->imports ? '$'.number_format($economyLatest->imports / 1000000000, 2, ',', '.').' M' : '-' }}
                                </strong>
                            </div>
                        </div>
                        <div class="sg-data-note">
                            <i class="bi bi-info-circle"></i>
                            <span>Data perdagangan ditampilkan dari indikator ekonomi terakhir yang tersimpan di database.</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="sg-section" id="pelabuhan">
        <div class="sg-section-head">
            <div>
                <h2>Lokasi Pelabuhan</h2>
                <p>Peta pelabuhan yang terdaftar untuk negara terpilih.</p>
            </div>
            <div class="sg-map-search">
                <i class="bi bi-search"></i>
                <input type="search" id="portSearch" placeholder="Cari nama atau kode pelabuhan">
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <article class="sg-card sg-map-card">
                    <div id="portMap"></div>
                </article>
            </div>
            <div class="col-12 col-xl-4">
                <article class="sg-card sg-port-list-card">
                    <div class="sg-card-header">
                        <div>
                            <h3>Daftar Pelabuhan</h3>
                            <p><span id="portCount">{{ $ports->count() }}</span> lokasi ditemukan.</p>
                        </div>
                    </div>
                    <div class="sg-port-list" id="portList">
                        @forelse ($ports as $port)
                            <button type="button" class="sg-port-item" data-port-id="{{ $port->id }}">
                                <span class="sg-port-item-icon"><i class="bi bi-geo-alt"></i></span>
                                <span class="sg-port-item-copy">
                                    <strong>{{ $port->name }}</strong>
                                    <small>{{ $port->port_code ?? 'Tanpa kode' }} · {{ $port->type ?? 'Pelabuhan' }}</small>
                                </span>
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        @empty
                            <div class="sg-empty-state sg-empty-state-small">
                                <i class="bi bi-geo-alt"></i>
                                <strong>Data pelabuhan belum tersedia</strong>
                            </div>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="sg-section" id="berita">
        <div class="sg-section-head">
            <div>
                <h2>Intelijen Berita</h2>
                <p>Berita ekonomi dan logistik beserta hasil analisis sentimen.</p>
            </div>
            <span class="sg-section-count">{{ $news->count() }} berita</span>
        </div>

        <article class="sg-card">
            <div class="sg-news-list">
                @forelse ($news as $item)
                    @php
                        $sentiment = strtolower($item->sentiment ?? 'neutral');
                        $sentimentClass = match ($sentiment) {
                            'positive' => 'is-positive',
                            'negative' => 'is-negative',
                            default => 'is-neutral',
                        };
                    @endphp
                    <div class="sg-news-item">
                        <div class="sg-news-meta">
                            <span class="sg-category-pill">{{ $item->category ?? 'Umum' }}</span>
                            <span class="sg-sentiment-pill {{ $sentimentClass }}">{{ $item->sentiment ?? 'Neutral' }}</span>
                            <span>{{ $item->published_at ? \Carbon\Carbon::parse($item->published_at)->format('d M Y') : '-' }}</span>
                        </div>
                        <h3>{{ $item->title }}</h3>
                        <p>{{ $item->description ?? 'Deskripsi berita belum tersedia.' }}</p>
                        <div class="sg-news-foot">
                            <span><i class="bi bi-building"></i>{{ $item->source_name ?? 'Sumber tidak diketahui' }}</span>
                            <span>Positif {{ $item->positive_score ?? 0 }} · Negatif {{ $item->negative_score ?? 0 }}</span>
                        </div>
                    </div>
                @empty
                    <div class="sg-empty-state">
                        <i class="bi bi-newspaper"></i>
                        <strong>Belum ada berita untuk negara ini</strong>
                        <span>Data berita akan tampil setelah tersedia di database.</span>
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="sg-section" id="api">
        <div class="sg-section-head">
            <div>
                <h2>REST API Internal</h2>
                <p>Endpoint JSON yang dapat digunakan oleh dashboard dan aplikasi lain.</p>
            </div>
        </div>
        <article class="sg-card">
            <div class="sg-api-grid">
                @foreach ([
                    ['GET', '/api/countries', 'Daftar negara'],
                    ['GET', '/api/risk?country_id='.$selectedCountryId, 'Skor risiko negara'],
                    ['GET', '/api/ports?country_id='.$selectedCountryId, 'Data pelabuhan'],
                    ['GET', '/api/news?country_id='.$selectedCountryId, 'Berita dan sentimen'],
                    ['GET', '/api/currency?country_id='.$selectedCountryId, 'Kurs dan riwayat mata uang'],
                ] as [$method, $endpoint, $description])
                    <div class="sg-api-item">
                        <span class="sg-method-badge">{{ $method }}</span>
                        <code>{{ $endpoint }}</code>
                        <span>{{ $description }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartTextColor = '#6b7785';
            const gridColor = '#ebeff3';
            const tealColor = '#147b72';
            const navyColor = '#263444';
            const warningColor = '#b56c16';

            Chart.defaults.font.family = 'Inter, Arial, sans-serif';
            Chart.defaults.color = chartTextColor;

            const economyLabels = @json($economyLabels);
            const gdpValues = @json($gdpValues);
            const inflationValues = @json($inflationValues);

            new Chart(document.getElementById('economyChart'), {
                type: 'line',
                data: {
                    labels: economyLabels,
                    datasets: [
                        {
                            label: 'PDB (triliun USD)',
                            data: gdpValues,
                            borderColor: tealColor,
                            backgroundColor: 'rgba(20, 123, 114, 0.08)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Inflasi (%)',
                            data: inflationValues,
                            borderColor: warningColor,
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            borderDash: [5, 4],
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, padding: 18 } }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            beginAtZero: false,
                            grid: { color: gridColor },
                            title: { display: true, text: 'PDB' }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Inflasi' }
                        }
                    }
                }
            });

            const riskLabels = @json($riskLabels);
            const riskValues = @json($riskValues);

            new Chart(document.getElementById('riskChart'), {
                type: 'line',
                data: {
                    labels: riskLabels,
                    datasets: [{
                        label: 'Skor risiko',
                        data: riskValues,
                        borderColor: navyColor,
                        backgroundColor: 'rgba(38, 52, 68, 0.06)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, max: 100, grid: { color: gridColor } }
                    }
                }
            });

            const currencyLabels = @json($currencyLabels);
            const currencyValues = @json($currencyValues);

            new Chart(document.getElementById('currencyChart'), {
                type: 'line',
                data: {
                    labels: currencyLabels,
                    datasets: [{
                        label: 'Nilai tukar',
                        data: currencyValues,
                        borderColor: tealColor,
                        backgroundColor: 'rgba(20, 123, 114, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: false, grid: { color: gridColor } }
                    }
                }
            });

            fetch(`/api/risk?country_id={{ $selectedCountryId }}`)
                .then(response => response.ok ? response.json() : Promise.reject(response))
                .then(payload => {
                    const riskData = payload.data;
                    if (!riskData) return;

                    document.querySelectorAll('[data-risk-field]').forEach(row => {
                        const field = row.dataset.riskField;
                        const value = Math.min(100, Math.max(0, Number(riskData[field] ?? 0)));
                        const valueElement = row.querySelector('.sg-risk-value');
                        const barElement = row.querySelector('.sg-progress-bar');
                        if (valueElement) valueElement.textContent = `${value}/100`;
                        if (barElement) barElement.style.width = `${value}%`;
                    });
                })
                .catch(() => {
                    // Data server-side tetap tampil jika endpoint tidak dapat diakses.
                });

            const ports = @json($portMapData);
            const mapElement = document.getElementById('portMap');

            if (mapElement && window.L) {
                const defaultLatitude = Number(@json($country->latitude ?? -2.5489));
                const defaultLongitude = Number(@json($country->longitude ?? 118.0149));
                const map = L.map(mapElement, {
                    scrollWheelZoom: false,
                    zoomControl: true
                }).setView([defaultLatitude, defaultLongitude], 4);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                const markerById = new Map();
                const bounds = [];

                ports.forEach(port => {
                    if (port.latitude === null || port.longitude === null) return;

                    const marker = L.marker([port.latitude, port.longitude])
                        .addTo(map)
                        .bindPopup(`<strong>${escapeHtml(port.name)}</strong><br>${escapeHtml(port.code || 'Tanpa kode')} · ${escapeHtml(port.type || 'Pelabuhan')}`);

                    markerById.set(String(port.id), marker);
                    bounds.push([port.latitude, port.longitude]);
                });

                const refreshMap = () => {
                    map.invalidateSize({ animate: false, pan: false });
                };

                const positionMap = () => {
                    refreshMap();
                    if (bounds.length > 0) {
                        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 8 });
                    }
                };

                requestAnimationFrame(() => {
                    requestAnimationFrame(positionMap);
                });

                window.addEventListener('load', positionMap, { once: true });
                window.setTimeout(positionMap, 300);

                if ('ResizeObserver' in window) {
                    const resizeObserver = new ResizeObserver(() => refreshMap());
                    resizeObserver.observe(mapElement);
                } else {
                    window.addEventListener('resize', refreshMap);
                }

                document.querySelectorAll('.sg-port-item').forEach(item => {
                    item.addEventListener('click', function () {
                        const marker = markerById.get(this.dataset.portId);
                        if (!marker) return;
                        map.setView(marker.getLatLng(), 10);
                        marker.openPopup();
                    });
                });
            }

            const portSearch = document.getElementById('portSearch');
            portSearch?.addEventListener('input', function () {
                const keyword = this.value.trim().toLowerCase();
                let visibleCount = 0;

                document.querySelectorAll('.sg-port-item').forEach(item => {
                    const isMatch = item.textContent.toLowerCase().includes(keyword);
                    item.classList.toggle('d-none', !isMatch);
                    if (isMatch) visibleCount++;
                });

                const countElement = document.getElementById('portCount');
                if (countElement) countElement.textContent = visibleCount;
            });

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }
        });
    </script>
@endpush
