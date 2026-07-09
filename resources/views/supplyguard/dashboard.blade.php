<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SupplyGuard - Global Supply Chain Risk Intelligence</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
        }

        .sidebar {
            min-height: 100vh;
            background: #0f172a;
            color: white;
            position: sticky;
            top: 0;
        }

        .sidebar .brand {
            font-size: 24px;
            font-weight: 800;
        }

        .sidebar a {
            color: #cbd5e1;
            text-decoration: none;
            display: block;
            padding: 10px 0;
        }

        .sidebar a:hover {
            color: white;
        }

        .stat-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .section-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        #portMap {
            height: 420px;
            border-radius: 16px;
        }

        .risk-low {
            background: #dcfce7;
            color: #166534;
        }

        .risk-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .risk-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .small-label {
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>

<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar p-4">
            <div class="brand mb-4">SupplyGuard</div>
            <div class="small text-secondary mb-4">
                Intelijen Risiko Rantai Pasokan Global
            </div>

            <a href="{{ route('dashboard') }}">Dasbor</a>
            <a href="{{ route('comparison') }}">Perbandingan Negara</a>

            @if (auth()->user()->role === 'admin')
                <a href="{{ route('admin.index') }}">Admin Dashboard</a>
            @endif

            <a href="#watchlist">Daftar Pemantauan Favorit</a>
            <a href="#analytics">Visualisasi Data</a>
            <a href="#ports">Lokasi Pelabuhan</a>
            <a href="#news">Intelijen Berita</a>
            <a href="#api">API REST</a>

            <hr class="border-secondary">

            <div class="small text-secondary mb-2">
                Masuk sebagai:<br>
                <strong class="text-white">{{ auth()->user()->name }}</strong>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm w-100">
                    Keluar
                </button>
            </form>
        </aside>

        <main class="col-md-9 col-lg-10 p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Dasbor Negara Global</h2>
                    <p class="text-muted mb-0">
                        Pemantauan risiko rantai pasok global berbasis data ekonomi, cuaca, kurs, berita, dan pelabuhan.
                    </p>
                </div>

                <div class="mt-3 mt-md-0">
                    <form method="GET" action="{{ route('dashboard') }}" class="mb-2">
                        <select name="country_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($countries as $item)
                                <option value="{{ $item->id }}" {{ $selectedCountryId == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    @if ($country)
                        @if ($isWatched)
                            <form method="POST" action="{{ route('watchlist.destroyByCountry', $country->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    Hapus dari Daftar Pantauan
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('watchlist.store') }}">
                                @csrf
                                <input type="hidden" name="country_id" value="{{ $country->id }}">
                                <button type="submit" class="btn btn-dark btn-sm w-100">
                                    Tambahkan ke Daftar Pantauan
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $riskLevel = $risk->risk_level ?? 'Low';
                $riskClass = 'risk-low';
                $riskLabel = 'Risiko Rendah';

                if ($riskLevel === 'Medium') {
                    $riskClass = 'risk-medium';
                    $riskLabel = 'Risiko Sedang';
                }

                if ($riskLevel === 'High') {
                    $riskClass = 'risk-high';
                    $riskLabel = 'Risiko Tinggi';
                }
            @endphp

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card p-3">
                        <div class="small-label">Negara yang Dipilih</div>
                        <h4 class="fw-bold mb-1">{{ $country->name ?? '-' }}</h4>
                        <div class="text-muted">
                            {{ $country->region ?? '-' }} · {{ $country->currency_code ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card p-3">
                        <div class="small-label">Skor Risiko</div>
                        <h4 class="fw-bold mb-1">{{ $risk->total_score ?? 0 }}/100</h4>
                        <span class="badge {{ $riskClass }}">{{ $riskLabel }}</span>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card p-3">
                        <div class="small-label">PDB</div>
                        <h4 class="fw-bold mb-1">
                            {{ $economyLatest && $economyLatest->gdp ? '$' . number_format($economyLatest->gdp / 1000000000000, 2, ',', '.') . ' triliun' : '-' }}
                        </h4>
                        <div class="text-muted">Tahun {{ $economyLatest->year ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card p-3">
                        <div class="small-label">Inflasi</div>
                        <h4 class="fw-bold mb-1">
                            {{ $economyLatest && $economyLatest->inflation !== null ? number_format($economyLatest->inflation, 2, ',', '.') : '-' }}%
                        </h4>
                        <div class="text-muted">
                            Populasi:
                            {{ $economyLatest && $economyLatest->population ? number_format($economyLatest->population, 0, ',', '.') : '-' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="card section-card p-3 h-100">
                        <h5 class="fw-bold">Pemantauan Cuaca</h5>
                        <div class="mt-3">
                            <div class="small-label">Suhu</div>
                            <h3>
                                {{ $weather && $weather->temperature !== null ? number_format($weather->temperature, 2, ',', '.') : '-' }}°C
                            </h3>
                        </div>

                        <div class="row mt-3">
                            <div class="col-6">
                                <div class="small-label">Curah hujan</div>
                                <strong>{{ $weather && $weather->rainfall !== null ? number_format($weather->rainfall, 2, ',', '.') : '-' }} mm</strong>
                            </div>

                            <div class="col-6">
                                <div class="small-label">Kecepatan Angin</div>
                                <strong>{{ $weather && $weather->wind_speed !== null ? number_format($weather->wind_speed, 2, ',', '.') : '-' }} km/jam</strong>
                            </div>
                        </div>

                        <hr>

                        <div class="small-label">Status</div>
                        <div class="fw-bold">{{ $weather->weather_status ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card section-card p-3 h-100">
                        <h5 class="fw-bold">Dampak Mata Uang</h5>

                        <div class="mt-3">
                            <div class="small-label">Kurs</div>
                            <h3>
                                1 {{ $currency->base_currency ?? 'USD' }}
                                =
                                {{ $currency && $currency->rate !== null ? number_format($currency->rate, 3, ',', '.') : '-' }}
                                {{ $currency->target_currency ?? '-' }}
                            </h3>
                        </div>

                        <div class="small-label">Mengubah</div>
                        <div class="fw-bold">
                            {{ $currency && $currency->change_percent !== null ? number_format($currency->change_percent, 2, ',', '.') : '-' }}%
                        </div>

                        <hr>

                        <div class="small-label">Risiko Mata Uang</div>
                        <div class="fw-bold">{{ $currency->currency_risk ?? 0 }}/100</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card section-card p-3 h-100">
                        <h5 class="fw-bold">Mesin Penilaian Risiko</h5>
                        <div id="riskDetail" class="mt-3">
                            <div class="text-muted">Loading risk detail...</div>
                        </div>
                    </div>
                </div>
            </div>

            <section id="watchlist" class="mb-4">
                <div class="card section-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Daftar Pemantauan Favorit</h5>
                            <div class="text-muted">
                                Daftar negara yang disimpan pengguna untuk pemantauan secara berkala.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        @forelse ($watchlists as $item)
                            @php
                                $badgeClass = 'bg-success';
                                $watchRiskLabel = 'Rendah';

                                if ($item->risk_level === 'Medium') {
                                    $badgeClass = 'bg-warning text-dark';
                                    $watchRiskLabel = 'Sedang';
                                }

                                if ($item->risk_level === 'High') {
                                    $badgeClass = 'bg-danger';
                                    $watchRiskLabel = 'Tinggi';
                                }
                            @endphp

                            <div class="col-md-6 col-xl-3">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1">{{ $item->country_name }}</h6>
                                            <small class="text-muted">{{ $item->currency_code }}</small>
                                        </div>

                                        <span class="badge {{ $badgeClass }}">
                                            {{ $watchRiskLabel }}
                                        </span>
                                    </div>

                                    <div class="small text-muted mb-2">
                                        Skor Risiko:
                                        <strong>{{ $item->total_score ?? 0 }}/100</strong>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <a href="{{ route('dashboard', ['country_id' => $item->country_id]) }}" class="btn btn-sm btn-outline-dark">
                                            Lihat
                                        </a>

                                        <form method="POST" action="{{ route('watchlist.destroyByCountry', $item->country_id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-muted">
                                    Belum ada negara favorit. Pilih negara lalu klik
                                    <strong>Tambahkan ke Daftar Pantauan</strong>.
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section id="analytics" class="mb-4">
                <div class="card section-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Dasbor Visualisasi Data</h5>
                            <div class="text-muted">
                                Tren PDB, tren inflasi, tren mata uang, dan tren risiko.
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <canvas id="gdpChart"></canvas>
                        </div>

                        <div class="col-lg-6">
                            <canvas id="inflationChart"></canvas>
                        </div>

                        <div class="col-lg-6">
                            <canvas id="currencyChart"></canvas>
                        </div>

                        <div class="col-lg-6">
                            <canvas id="riskChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <section id="ports" class="mb-4">
                <div class="card section-card p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Dasbor Lokasi Pelabuhan</h5>
                            <div class="text-muted">
                                Peta lokasi pelabuhan dengan marker interaktif.
                            </div>
                        </div>

                        <div class="d-flex mt-3 mt-md-0">
                            <input type="text" id="portSearch" class="form-control me-2" placeholder="Cari pelabuhan...">
                            <button class="btn btn-dark" onclick="searchPorts()">Cari</button>
                        </div>
                    </div>

                    <div id="portMap"></div>

                    <div class="mt-3" id="portList"></div>
                </div>
            </section>

            <section id="news" class="mb-4">
                <div class="card section-card p-3">
                    <h5 class="fw-bold mb-3">Intelijen Berita & Analisis Sentimen</h5>

                    <div class="row g-3">
                        @forelse ($news as $item)
                            <div class="col-lg-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge bg-secondary">{{ $item->category }}</span>
                                        <span class="badge bg-dark">{{ $item->sentiment }}</span>
                                    </div>

                                    <h6 class="fw-bold">{{ $item->title }}</h6>

                                    <p class="text-muted mb-2">
                                        {{ $item->description }}
                                    </p>

                                    <small>
                                        Positive: {{ $item->positive_score }} ·
                                        Negative: {{ $item->negative_score }} ·
                                        {{ $item->source_name }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-muted">
                                Belum ada berita.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section id="api" class="mb-4">
                <div class="card section-card p-3">
                    <h5 class="fw-bold">API REST Internal</h5>
                    <p class="text-muted mb-2">Endpoint awal yang sudah aktif:</p>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <code>/api/countries</code>
                        </div>

                        <div class="col-md-4">
                            <code>/api/risk?country_id={{ $selectedCountryId }}</code>
                        </div>

                        <div class="col-md-4">
                            <code>/api/ports?country_id={{ $selectedCountryId }}</code>
                        </div>

                        <div class="col-md-4">
                            <code>/api/news?country_id={{ $selectedCountryId }}</code>
                        </div>

                        <div class="col-md-4">
                            <code>/api/currency?country_id={{ $selectedCountryId }}</code>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    const selectedCountryId = @json($selectedCountryId);

    const economyYears = @json($economyTrend->pluck('year'));
    const gdpData = @json($economyTrend->pluck('gdp'));
    const inflationData = @json($economyTrend->pluck('inflation'));

    const currencyDates = @json($currencyTrend->pluck('rate_date'));
    const currencyRates = @json($currencyTrend->pluck('rate'));

    const riskDates = @json($riskTrend->pluck('recorded_date'));
    const riskScores = @json($riskTrend->pluck('total_score'));

    const initialPorts = @json($ports);
    const countryLatitude = @json((float) ($country->latitude ?? 0));
    const countryLongitude = @json((float) ($country->longitude ?? 0));

    new Chart(document.getElementById('gdpChart'), {
        type: 'line',
        data: {
            labels: economyYears,
            datasets: [{
                label: 'GDP',
                data: gdpData,
                tension: 0.3
            }]
        }
    });

    new Chart(document.getElementById('inflationChart'), {
        type: 'bar',
        data: {
            labels: economyYears,
            datasets: [{
                label: 'Inflation (%)',
                data: inflationData
            }]
        }
    });

    new Chart(document.getElementById('currencyChart'), {
        type: 'line',
        data: {
            labels: currencyDates,
            datasets: [{
                label: 'Currency Rate',
                data: currencyRates,
                tension: 0.3
            }]
        }
    });

    new Chart(document.getElementById('riskChart'), {
        type: 'line',
        data: {
            labels: riskDates,
            datasets: [{
                label: 'Risk Score',
                data: riskScores,
                tension: 0.3
            }]
        },
        options: {
            scales: {
                y: {
                    min: 0,
                    max: 100
                }
            }
        }
    });

    const map = L.map('portMap').setView([countryLatitude, countryLongitude], 4);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const markerLayer = L.layerGroup().addTo(map);

    function renderPorts(ports) {
        markerLayer.clearLayers();

        const list = document.getElementById('portList');

        if (!ports || ports.length === 0) {
            list.innerHTML = '<div class="text-muted">Data pelabuhan tidak ditemukan.</div>';
            return;
        }

        let html = '<div class="row g-2">';

        ports.forEach(port => {
            if (port.latitude && port.longitude) {
                L.marker([port.latitude, port.longitude])
                    .addTo(markerLayer)
                    .bindPopup(`<strong>${port.name}</strong><br>${port.country_name}<br>${port.port_code ?? ''}`);
            }

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded-3 p-3">
                        <strong>${port.name}</strong><br>
                        <small>${port.country_name ?? '-'} · ${port.port_code ?? '-'}</small><br>
                        <small>${port.description ?? ''}</small>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        list.innerHTML = html;
    }

    function searchPorts() {
        const keyword = document.getElementById('portSearch').value;

        fetch(`/api/ports?country_id=${selectedCountryId}&q=${encodeURIComponent(keyword)}`)
            .then(response => response.json())
            .then(result => {
                renderPorts(result.data);
            });
    }

    function loadRiskDetail() {
        fetch(`/api/risk?country_id=${selectedCountryId}`)
            .then(response => response.json())
            .then(result => {
                const risk = result.data;

                if (!risk) {
                    document.getElementById('riskDetail').innerHTML = '<div class="text-muted">Risk score belum tersedia.</div>';
                    return;
                }

                document.getElementById('riskDetail').innerHTML = `
                    <div class="mb-2">Risiko Cuaca: <strong>${risk.weather_score}/100</strong></div>
                    <div class="mb-2">Risiko Inflasi: <strong>${risk.inflation_score}/100</strong></div>
                    <div class="mb-2">Risiko Mata Uang: <strong>${risk.currency_score}/100</strong></div>
                    <div class="mb-2">Risiko Berita: <strong>${risk.news_score}/100</strong></div>
                    <div class="mb-2">Risiko Pelabuhan: <strong>${risk.port_score}/100</strong></div>
                    <hr>
                    <div class="fw-bold">Rekomendasi</div>
                    <div class="text-muted">${risk.recommendation ?? '-'}</div>
                `;
            });
    }

    renderPorts(initialPorts);
    loadRiskDetail();
</script>
</body>
</html>
