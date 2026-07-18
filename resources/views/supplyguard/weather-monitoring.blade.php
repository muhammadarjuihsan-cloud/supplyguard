@extends('layouts.supplyguard')

@section('title', 'Monitoring Cuaca - SupplyGuard')
@section('page-title', 'Monitoring Cuaca')
@section('page-subtitle', 'Pantau kondisi cuaca global yang memengaruhi risiko rantai pasok')

@push('styles')
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/weather-monitoring.css') }}?v={{ filemtime(public_path('css/weather-monitoring.css')) }}"
    >
@endpush

@section('content')
    @php
        $riskLabels = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
        ];

        $weatherIcon = static function (?string $status): string {
            $status = strtolower((string) $status);

            return match (true) {
                str_contains($status, 'badai'),
                str_contains($status, 'petir') => 'bi-cloud-lightning-rain-fill',

                str_contains($status, 'hujan'),
                str_contains($status, 'gerimis') => 'bi-cloud-rain-heavy-fill',

                str_contains($status, 'salju') => 'bi-snow2',

                str_contains($status, 'kabut') => 'bi-cloud-fog2-fill',

                str_contains($status, 'cerah') => 'bi-sun-fill',

                default => 'bi-cloud-sun-fill',
            };
        };

        $riskClass = static function (int $score): string {
            return match (true) {
                $score <= 30 => 'is-low',
                $score <= 60 => 'is-medium',
                default => 'is-high',
            };
        };

        $riskLabel = static function (int $score): string {
            return match (true) {
                $score <= 30 => 'Rendah',
                $score <= 60 => 'Sedang',
                default => 'Tinggi',
            };
        };
    @endphp

    <section class="sg-weather-page">
        <div class="sg-weather-hero">
            <div>
                <span class="sg-weather-eyebrow">
                    <i class="bi bi-cloud-sun-fill"></i>
                    Open-Meteo Monitoring
                </span>
                <h2>Kondisi Cuaca Global</h2>
                <p>
                    Analisis suhu, curah hujan, kecepatan angin, dan skor risiko
                    cuaca untuk mendukung keputusan logistik dan rantai pasok.
                </p>
            </div>

            <div class="sg-weather-update">
                <i class="bi bi-clock-history"></i>
                <div>
                    <small>Pembaruan terakhir</small>
                    <strong>
                        {{ $statistics['last_updated']
                            ? \Carbon\Carbon::parse($statistics['last_updated'])->format('d M Y, H:i')
                            : 'Belum tersedia' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="sg-weather-stat-grid">
            <article class="sg-weather-stat">
                <span class="sg-weather-stat-icon">
                    <i class="bi bi-globe2"></i>
                </span>
                <div>
                    <small>Negara Terpantau</small>
                    <strong>{{ number_format($statistics['total_weather'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-weather-stat">
                <span class="sg-weather-stat-icon">
                    <i class="bi bi-thermometer-half"></i>
                </span>
                <div>
                    <small>Rata-rata Suhu</small>
                    <strong>
                        {{ $statistics['average_temperature'] !== null
                            ? number_format((float) $statistics['average_temperature'], 1, ',', '.').'°C'
                            : '—' }}
                    </strong>
                </div>
            </article>

            <article class="sg-weather-stat">
                <span class="sg-weather-stat-icon">
                    <i class="bi bi-cloud-rain-fill"></i>
                </span>
                <div>
                    <small>Rata-rata Hujan</small>
                    <strong>
                        {{ $statistics['average_rainfall'] !== null
                            ? number_format((float) $statistics['average_rainfall'], 1, ',', '.').' mm'
                            : '—' }}
                    </strong>
                </div>
            </article>

            <article class="sg-weather-stat">
                <span class="sg-weather-stat-icon">
                    <i class="bi bi-wind"></i>
                </span>
                <div>
                    <small>Rata-rata Angin</small>
                    <strong>
                        {{ $statistics['average_wind'] !== null
                            ? number_format((float) $statistics['average_wind'], 1, ',', '.').' km/jam'
                            : '—' }}
                    </strong>
                </div>
            </article>

            <article class="sg-weather-stat">
                <span class="sg-weather-stat-icon">
                    <i class="bi bi-funnel-fill"></i>
                </span>
                <div>
                    <small>Hasil Filter</small>
                    <strong>{{ number_format($statistics['filtered_weather'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <div class="sg-weather-main-grid">
            <article class="sg-weather-panel">
                <div class="sg-weather-panel-head">
                    <div>
                        <span>Pencarian Data</span>
                        <h3>Filter Kondisi Cuaca</h3>
                    </div>

                    @if ($keyword !== '' || $riskRange !== '' || $region !== '')
                        <a href="{{ route('weather.index') }}">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Bersihkan filter
                        </a>
                    @endif
                </div>

                <form method="GET" action="{{ route('weather.index') }}" class="sg-weather-filter">
                    <div class="sg-weather-field sg-weather-field-search">
                        <label for="q">Negara, kode, atau kondisi</label>
                        <div class="sg-weather-input-icon">
                            <i class="bi bi-search"></i>
                            <input
                                type="search"
                                id="q"
                                name="q"
                                value="{{ $keyword }}"
                                placeholder="Contoh: Indonesia, IDN, hujan"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="sg-weather-field">
                        <label for="region">Wilayah</label>
                        <select name="region" id="region">
                            <option value="">Semua wilayah</option>
                            @foreach ($regions as $regionItem)
                                <option value="{{ $regionItem }}" @selected($region === $regionItem)>
                                    {{ $regionItem }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sg-weather-field">
                        <label for="risk">Risiko cuaca</label>
                        <select name="risk" id="risk">
                            <option value="">Semua tingkat</option>
                            @foreach ($riskLabels as $value => $label)
                                <option value="{{ $value }}" @selected($riskRange === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sg-weather-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel-fill"></i>
                            Terapkan
                        </button>
                        <a href="{{ route('weather.index') }}" class="btn btn-light">
                            Reset
                        </a>
                    </div>
                </form>
            </article>

            <article class="sg-weather-panel">
                <div class="sg-weather-panel-head">
                    <div>
                        <span>Distribusi Global</span>
                        <h3>Tingkat Risiko Cuaca</h3>
                    </div>
                </div>

                <div class="sg-weather-risk-content">
                    <div class="sg-weather-chart">
                        <canvas id="weatherRiskChart"></canvas>
                    </div>

                    <div class="sg-weather-risk-legend">
                        <div>
                            <span class="is-low"></span>
                            <small>Rendah</small>
                            <strong>{{ $riskDistribution['low'] }}</strong>
                        </div>
                        <div>
                            <span class="is-medium"></span>
                            <small>Sedang</small>
                            <strong>{{ $riskDistribution['medium'] }}</strong>
                        </div>
                        <div>
                            <span class="is-high"></span>
                            <small>Tinggi</small>
                            <strong>{{ $riskDistribution['high'] }}</strong>
                        </div>
                    </div>
                </div>
            </article>
        </div>


        <article class="sg-weather-panel sg-weather-map-panel">
            <div class="sg-weather-panel-head">
                <div>
                    <span>Peta Cuaca Global</span>
                    <h3>Persebaran Risiko Cuaca Negara</h3>
                </div>

                <span class="sg-weather-result-badge">
                    {{ number_format($weatherMapItems->count(), 0, ',', '.') }}
                    marker
                </span>
            </div>

            <div class="sg-weather-map-toolbar">
                <div class="sg-weather-map-legend">
                    <span>
                        <i class="is-low"></i>
                        Risiko rendah
                    </span>
                    <span>
                        <i class="is-medium"></i>
                        Risiko sedang
                    </span>
                    <span>
                        <i class="is-high"></i>
                        Risiko tinggi
                    </span>
                </div>

                <small>
                    Peta mengikuti hasil pencarian dan filter yang sedang aktif.
                </small>
            </div>

            @if ($weatherMapItems->count() > 0)
                <div
                    id="weatherGlobalMap"
                    class="sg-weather-map"
                    aria-label="Peta persebaran risiko cuaca global"
                ></div>
            @else
                <div class="sg-weather-empty-small">
                    Tidak ada koordinat negara yang sesuai dengan filter.
                </div>
            @endif
        </article>

        <article class="sg-weather-panel">
            <div class="sg-weather-panel-head">
                <div>
                    <span>Peringatan Cuaca</span>
                    <h3>Negara dengan Risiko Cuaca Tertinggi</h3>
                </div>
            </div>

            <div class="sg-weather-alert-grid">
                @forelse ($topRiskCountries as $country)
                    <a
                        href="{{ route('dashboard', ['country_id' => $country->id]) }}"
                        class="sg-weather-alert-card"
                    >
                        <span class="sg-weather-alert-icon">
                            <i class="bi {{ $weatherIcon($country->weather_status) }}"></i>
                        </span>

                        <div>
                            <strong>{{ $country->name }}</strong>
                            <small>{{ $country->weather_status ?: 'Status tidak tersedia' }}</small>
                        </div>

                        <span class="sg-weather-alert-score {{ $riskClass((int) $country->weather_risk) }}">
                            {{ (int) $country->weather_risk }}/100
                        </span>
                    </a>
                @empty
                    <div class="sg-weather-empty-small">
                        Belum ada data peringatan cuaca.
                    </div>
                @endforelse
            </div>
        </article>

        <article class="sg-weather-panel">
            <div class="sg-weather-panel-head">
                <div>
                    <span>Daftar Data</span>
                    <h3>Monitoring Cuaca Negara</h3>
                </div>

                <span class="sg-weather-result-badge">
                    Halaman {{ $weatherItems->currentPage() }}
                    dari {{ max(1, $weatherItems->lastPage()) }}
                </span>
            </div>

            @if ($weatherItems->count() > 0)
                <div class="table-responsive">
                    <table class="table sg-weather-table align-middle">
                        <thead>
                            <tr>
                                <th>Negara</th>
                                <th>Kondisi</th>
                                <th>Suhu</th>
                                <th>Curah Hujan</th>
                                <th>Angin</th>
                                <th>Risiko</th>
                                <th>Pembaruan</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($weatherItems as $item)
                                @php
                                    $score = (int) $item->weather_risk;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="sg-weather-country-cell">
                                            <span>{{ strtoupper($item->cca2 ?: substr($item->country_name, 0, 2)) }}</span>
                                            <div>
                                                <strong>{{ $item->country_name }}</strong>
                                                <small>
                                                    {{ $item->region ?: 'Wilayah tidak tersedia' }}
                                                    @if ($item->cca3)
                                                        · {{ $item->cca3 }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="sg-weather-status">
                                            <i class="bi {{ $weatherIcon($item->weather_status) }}"></i>
                                            {{ $item->weather_status ?: 'Tidak tersedia' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>
                                            {{ $item->temperature !== null
                                                ? number_format((float) $item->temperature, 1, ',', '.').'°C'
                                                : '—' }}
                                        </strong>
                                    </td>
                                    <td>
                                        {{ $item->rainfall !== null
                                            ? number_format((float) $item->rainfall, 1, ',', '.').' mm'
                                            : '—' }}
                                    </td>
                                    <td>
                                        {{ $item->wind_speed !== null
                                            ? number_format((float) $item->wind_speed, 1, ',', '.').' km/jam'
                                            : '—' }}
                                    </td>
                                    <td>
                                        <span class="sg-weather-risk-badge {{ $riskClass($score) }}">
                                            {{ $riskLabel($score) }}
                                            · {{ $score }}/100
                                        </span>
                                    </td>
                                    <td>
                                        <span class="sg-weather-date">
                                            {{ $item->fetched_at
                                                ? \Carbon\Carbon::parse($item->fetched_at)->format('d M Y, H:i')
                                                : '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            href="{{ route('dashboard', ['country_id' => $item->country_id]) }}"
                                            class="sg-weather-detail"
                                            title="Buka analisis negara"
                                        >
                                            <i class="bi bi-arrow-up-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($weatherItems->hasPages())
                    <nav class="sg-weather-pagination" aria-label="Navigasi data cuaca">
                        <ul class="pagination mb-0">
                            <li class="page-item {{ $weatherItems->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $weatherItems->previousPageUrl() ?: '#' }}">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            @foreach (
                                $weatherItems->getUrlRange(
                                    max(1, $weatherItems->currentPage() - 2),
                                    min($weatherItems->lastPage(), $weatherItems->currentPage() + 2)
                                ) as $page => $url
                            )
                                <li class="page-item {{ $page === $weatherItems->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach

                            <li class="page-item {{ $weatherItems->hasMorePages() ? '' : 'disabled' }}">
                                <a class="page-link" href="{{ $weatherItems->nextPageUrl() ?: '#' }}">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                @endif
            @else
                <div class="sg-weather-empty">
                    <i class="bi bi-cloud-slash"></i>
                    <h3>Data cuaca tidak ditemukan</h3>
                    <p>Tidak ada data yang sesuai dengan filter yang dipilih.</p>
                    <a href="{{ route('weather.index') }}" class="btn btn-primary">
                        Tampilkan Semua Data
                    </a>
                </div>
            @endif
        </article>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const mapElement = document.getElementById('weatherGlobalMap');
            const mapItems = @json($weatherMapItems);

            if (
                mapElement
                && typeof L !== 'undefined'
                && Array.isArray(mapItems)
                && mapItems.length > 0
            ) {
                const weatherMap = L.map(mapElement, {
                    worldCopyJump: true,
                    minZoom: 2
                }).setView([15, 15], 2);

                L.tileLayer(
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                        maxZoom: 18,
                        attribution:
                            '&copy; OpenStreetMap contributors'
                    }
                ).addTo(weatherMap);

                const bounds = [];

                const markerColor = function (risk) {
                    if (risk <= 30) {
                        return '#18a875';
                    }

                    if (risk <= 60) {
                        return '#e5a426';
                    }

                    return '#e24d4d';
                };

                const riskLabel = function (risk) {
                    if (risk <= 30) {
                        return 'Rendah';
                    }

                    if (risk <= 60) {
                        return 'Sedang';
                    }

                    return 'Tinggi';
                };

                const formatNumber = function (value, suffix) {
                    if (value === null || value === undefined || value === '') {
                        return 'Tidak tersedia';
                    }

                    return `${Number(value).toLocaleString('id-ID', {
                        maximumFractionDigits: 1
                    })}${suffix}`;
                };

                mapItems.forEach(function (item) {
                    const latitude = Number(item.latitude);
                    const longitude = Number(item.longitude);
                    const risk = Number(item.weather_risk || 0);

                    if (
                        !Number.isFinite(latitude)
                        || !Number.isFinite(longitude)
                    ) {
                        return;
                    }

                    const color = markerColor(risk);
                    const detailUrl =
                        @json(route('dashboard'))
                        + '?country_id='
                        + encodeURIComponent(item.country_id);

                    const marker = L.circleMarker(
                        [latitude, longitude],
                        {
                            radius: risk >= 61 ? 9 : risk >= 31 ? 7 : 6,
                            color: '#ffffff',
                            weight: 2,
                            fillColor: color,
                            fillOpacity: 0.9
                        }
                    ).addTo(weatherMap);

                    marker.bindPopup(`
                        <div class="sg-weather-map-popup">
                            <span>${item.cca3 || item.cca2 || ''}</span>
                            <h4>${item.country_name}</h4>
                            <p>${item.weather_status || 'Status tidak tersedia'}</p>

                            <div>
                                <small>Suhu</small>
                                <strong>${formatNumber(item.temperature, '°C')}</strong>
                            </div>

                            <div>
                                <small>Hujan</small>
                                <strong>${formatNumber(item.rainfall, ' mm')}</strong>
                            </div>

                            <div>
                                <small>Angin</small>
                                <strong>${formatNumber(item.wind_speed, ' km/jam')}</strong>
                            </div>

                            <div>
                                <small>Risiko</small>
                                <strong style="color:${color}">
                                    ${riskLabel(risk)} · ${risk}/100
                                </strong>
                            </div>

                            <a href="${detailUrl}">
                                Buka analisis negara
                            </a>
                        </div>
                    `);

                    bounds.push([latitude, longitude]);
                });

                if (bounds.length > 1) {
                    weatherMap.fitBounds(bounds, {
                        padding: [24, 24],
                        maxZoom: 3
                    });
                }

                const refreshWeatherMapSize = function () {
                    weatherMap.invalidateSize({ pan: false });
                };

                requestAnimationFrame(refreshWeatherMapSize);
                setTimeout(refreshWeatherMapSize, 250);
                setTimeout(refreshWeatherMapSize, 800);
                window.addEventListener('resize', refreshWeatherMapSize);
            }

            const canvas = document.getElementById('weatherRiskChart');

            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Rendah', 'Sedang', 'Tinggi'],
                    datasets: [{
                        data: [
                            {{ $riskDistribution['low'] }},
                            {{ $riskDistribution['medium'] }},
                            {{ $riskDistribution['high'] }}
                        ],
                        backgroundColor: ['#18a875', '#e5a426', '#e24d4d'],
                        borderWidth: 0,
                        hoverOffset: 5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
@endpush
