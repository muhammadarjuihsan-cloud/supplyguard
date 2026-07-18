@extends('layouts.supplyguard')

@section('title', 'Lokasi Pelabuhan | SupplyGuard')

@push('styles')
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/port-location.css') }}?v={{ filemtime(public_path('css/port-location.css')) }}"
    >
@endpush

@section('content')
    <section class="sg-port-page">
        <div class="sg-port-heading">
            <div>
                <span class="sg-port-eyebrow">
                    <i class="bi bi-globe-asia-australia"></i>
                    Infrastruktur Logistik Global
                </span>
                <h1>Lokasi Pelabuhan</h1>
                <p>
                    Telusuri data pelabuhan dunia, lihat persebarannya pada peta,
                    dan gunakan filter untuk menemukan pelabuhan tertentu.
                </p>
            </div>

            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali ke Dasbor
            </a>
        </div>

        <div class="sg-port-stat-grid">
            <article class="sg-port-stat-card">
                <span class="sg-port-stat-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <div>
                    <small>Total Pelabuhan</small>
                    <strong>{{ number_format($statistics['total_ports'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-port-stat-card">
                <span class="sg-port-stat-icon">
                    <i class="bi bi-flag-fill"></i>
                </span>
                <div>
                    <small>Negara Terjangkau</small>
                    <strong>{{ number_format($statistics['countries_with_ports'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-port-stat-card">
                <span class="sg-port-stat-icon">
                    <i class="bi bi-funnel-fill"></i>
                </span>
                <div>
                    <small>Hasil Filter</small>
                    <strong>{{ number_format($statistics['filtered_ports'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-port-stat-card">
                <span class="sg-port-stat-icon">
                    <i class="bi bi-map-fill"></i>
                </span>
                <div>
                    <small>Marker pada Peta</small>
                    <strong>{{ number_format($statistics['mapped_ports'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <div class="sg-port-panel">
            <div class="sg-port-panel-header">
                <div>
                    <span class="sg-port-panel-kicker">Pencarian Data</span>
                    <h2>Filter Pelabuhan</h2>
                </div>
            </div>

            <form action="{{ route('ports.index') }}" method="GET" class="sg-port-filter-form">
                <div class="sg-port-field sg-port-field-wide">
                    <label for="q">Nama, kode, atau negara</label>
                    <div class="sg-port-input-wrap">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="{{ $keyword }}"
                            placeholder="Contoh: Shanghai, IDTPP, Indonesia"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="sg-port-field">
                    <label for="country_id">Negara</label>
                    <select id="country_id" name="country_id">
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

                <div class="sg-port-field">
                    <label for="type">Tipe pelabuhan</label>
                    <select id="type" name="type">
                        <option value="">Semua tipe</option>
                        @foreach ($types as $portType)
                            <option value="{{ $portType }}" @selected($type === $portType)>
                                {{ $portType }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-port-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i>
                        Terapkan
                    </button>

                    <a href="{{ route('ports.index') }}" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="sg-port-panel sg-port-map-panel">
            <div class="sg-port-panel-header">
                <div>
                    <span class="sg-port-panel-kicker">Peta Interaktif</span>
                    <h2>Persebaran Pelabuhan</h2>
                </div>

                <span class="sg-port-badge">
                    {{ number_format($statistics['mapped_ports'], 0, ',', '.') }} marker
                </span>
            </div>

            @if ($statistics['mapped_ports'] > 0)
                <div id="portMap" class="sg-port-map" aria-label="Peta lokasi pelabuhan"></div>
                <p class="sg-port-map-note">
                    Marker dikelompokkan otomatis agar peta tetap ringan.
                    Klik kelompok marker untuk memperbesar area.
                </p>
            @else
                <div class="sg-port-empty">
                    <i class="bi bi-map"></i>
                    <h3>Tidak ada marker yang dapat ditampilkan</h3>
                    <p>Ubah filter atau reset pencarian untuk melihat data lainnya.</p>
                </div>
            @endif
        </div>

        <div class="sg-port-panel">
            <div class="sg-port-panel-header">
                <div>
                    <span class="sg-port-panel-kicker">Daftar Data</span>
                    <h2>Pelabuhan Ditemukan</h2>
                </div>

                <span class="sg-port-badge">
                    Halaman {{ $ports->currentPage() }} dari {{ $ports->lastPage() }}
                </span>
            </div>

            @if ($ports->count() > 0)
                <div class="table-responsive">
                    <table class="table sg-port-table align-middle">
                        <thead>
                            <tr>
                                <th>Pelabuhan</th>
                                <th>Kode</th>
                                <th>Negara</th>
                                <th>Tipe</th>
                                <th>Koordinat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ports as $port)
                                <tr>
                                    <td>
                                        <div class="sg-port-name-cell">
                                            <span class="sg-port-table-icon">
                                                <i class="bi bi-geo-alt"></i>
                                            </span>
                                            <div>
                                                <strong>{{ $port->name }}</strong>
                                                @if ($port->description)
                                                    <small>{{ \Illuminate\Support\Str::limit($port->description, 85) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="sg-port-code">
                                            {{ $port->port_code ?: '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ $port->country_name ?: '-' }}</strong>
                                        @if ($port->cca3)
                                            <small class="d-block text-muted">{{ $port->cca3 }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $port->type ?: '-' }}</td>
                                    <td>
                                        @if ($port->latitude !== null && $port->longitude !== null)
                                            <span class="sg-port-coordinate">
                                                {{ number_format((float) $port->latitude, 4) }},
                                                {{ number_format((float) $port->longitude, 4) }}
                                            </span>
                                        @else
                                            <span class="text-muted">Tidak tersedia</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($ports->hasPages())
                    <nav class="sg-port-pagination-wrap" aria-label="Navigasi halaman pelabuhan">
                        <ul class="pagination mb-0">
                            <li class="page-item {{ $ports->onFirstPage() ? 'disabled' : '' }}">
                                <a
                                    class="page-link"
                                    href="{{ $ports->previousPageUrl() ?: '#' }}"
                                    aria-label="Halaman sebelumnya"
                                >
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            @foreach ($ports->getUrlRange(max(1, $ports->currentPage() - 2), min($ports->lastPage(), $ports->currentPage() + 2)) as $page => $url)
                                <li class="page-item {{ $page === $ports->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach

                            <li class="page-item {{ $ports->hasMorePages() ? '' : 'disabled' }}">
                                <a
                                    class="page-link"
                                    href="{{ $ports->nextPageUrl() ?: '#' }}"
                                    aria-label="Halaman berikutnya"
                                >
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                @endif
            @else
                <div class="sg-port-empty">
                    <i class="bi bi-search"></i>
                    <h3>Pelabuhan tidak ditemukan</h3>
                    <p>Tidak ada data yang cocok dengan filter yang dipilih.</p>
                    <a href="{{ route('ports.index') }}" class="btn btn-primary">
                        Tampilkan Semua Pelabuhan
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    @if ($statistics['mapped_ports'] > 0)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const mapElement = document.getElementById('portMap');

                if (!mapElement || typeof L === 'undefined') {
                    return;
                }

                const ports = @json($mapPorts);

                const map = L.map(mapElement, {
                    worldCopyJump: true,
                    minZoom: 2,
                }).setView([15, 10], 2);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const markerCluster = L.markerClusterGroup({
                    showCoverageOnHover: false,
                    spiderfyOnMaxZoom: true,
                    maxClusterRadius: 45,
                });

                const bounds = [];

                function escapeHtml(value) {
                    return String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                ports.forEach(function (port) {
                    const latitude = Number(port.latitude);
                    const longitude = Number(port.longitude);

                    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                        return;
                    }

                    const marker = L.marker([latitude, longitude]);

                    marker.bindPopup(`
                        <div class="sg-port-popup">
                            <strong>${escapeHtml(port.name)}</strong>
                            <span>${escapeHtml(port.country_name)}</span>
                            <dl>
                                <div>
                                    <dt>Kode</dt>
                                    <dd>${escapeHtml(port.port_code || '-')}</dd>
                                </div>
                                <div>
                                    <dt>Tipe</dt>
                                    <dd>${escapeHtml(port.type || '-')}</dd>
                                </div>
                            </dl>
                        </div>
                    `);

                    markerCluster.addLayer(marker);
                    bounds.push([latitude, longitude]);
                });

                map.addLayer(markerCluster);

                if (bounds.length === 1) {
                    map.setView(bounds[0], 9);
                } else if (bounds.length > 1) {
                    map.fitBounds(bounds, {
                        padding: [30, 30],
                        maxZoom: 7,
                    });
                }

                window.setTimeout(function () {
                    map.invalidateSize();
                }, 250);
            });
        </script>
    @endif
@endpush
