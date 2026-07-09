<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Country Comparison - SupplyGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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

        .section-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .metric-box {
            border-radius: 16px;
            background: white;
            padding: 18px;
            border: 1px solid #e2e8f0;
        }

        .small-label {
            color: #64748b;
            font-size: 13px;
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
    </style>
</head>

<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar p-4">
            <div class="brand mb-4">SupplyGuard</div>
            <div class="small text-secondary mb-4">
                Global Supply Chain Risk Intelligence
            </div>

            <a href="{{ route('dashboard') }}">Dasbor</a>
            <a href="{{ route('comparison') }}">Perbandingan Negara</a>

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
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Mesin Perbandingan Negara</h2>
                <p class="text-muted mb-0">
                    Bandingkan dua negara berdasarkan PDB, inflasi, cuaca, mata uang, dan skor risiko.
                </p>
            </div>

            <div class="card section-card p-3 mb-4">
                <form method="GET" action="{{ route('comparison') }}" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Negara Pertama</label>
                        <select name="country_a" class="form-select">
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" {{ $countryAId == $country->id ? 'selected' : '' }}>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Negara Kedua</label>
                        <select name="country_b" class="form-select">
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" {{ $countryBId == $country->id ? 'selected' : '' }}>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark w-100">
                            Bandingkan
                        </button>
                    </div>
                </form>
            </div>

            @php
                function riskBadgeClass($level) {
                    if ($level === 'Medium') {
                        return 'risk-medium';
                    }

                    if ($level === 'High') {
                        return 'risk-high';
                    }

                    return 'risk-low';
                }
            @endphp

            <div class="row g-4">
                @foreach ([$countryA, $countryB] as $data)
                    @if ($data)
                        @php
                            $country = $data['country'];
                            $economy = $data['economy'];
                            $weather = $data['weather'];
                            $currency = $data['currency'];
                            $risk = $data['risk'];
                            $riskLevel = $risk->risk_level ?? 'Low';
                        @endphp

                        <div class="col-lg-6">
                            <div class="card section-card p-4 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div>
                                        <h3 class="fw-bold mb-1">{{ $country->name }}</h3>
                                        <div class="text-muted">
                                            {{ $country->region }} · {{ $country->currency_code }}
                                        </div>
                                    </div>

                                    <span class="badge {{ riskBadgeClass($riskLevel) }}">
                                        {{ $riskLevel }} Risk
                                    </span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="small-label">PDB</div>
                                            <h5 class="fw-bold mb-0">
                                                {{ $economy && $economy->gdp ? '$' . number_format($economy->gdp / 1000000000000, 2) . ' triliun' : '-' }}
                                            </h5>
                                            <small class="text-muted">
                                                Tahun {{ $economy->year ?? '-' }}
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="small-label">Inflasi</div>
                                            <h5 class="fw-bold mb-0">
                                                {{ $economy->inflation ?? '-' }}%
                                            </h5>
                                            <small class="text-muted">
                                                Populasi: {{ $economy && $economy->population ? number_format($economy->population) : '-' }}
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="small-label">Cuaca</div>
                                            <h5 class="fw-bold mb-0">
                                                {{ $weather->temperature ?? '-' }}°C
                                            </h5>
                                            <small class="text-muted">
                                                {{ $weather->weather_status ?? '-' }}
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="small-label">Kecepatan Angin</div>
                                            <h5 class="fw-bold mb-0">
                                                {{ $weather->wind_speed ?? '-' }} km/jam
                                            </h5>
                                            <small class="text-muted">
                                                Curah hujan: {{ $weather->rainfall ?? '-' }} mm
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="small-label">Kurs</div>
                                            <h5 class="fw-bold mb-0">
                                                {{ $currency->rate ?? '-' }} {{ $currency->target_currency ?? '-' }}
                                            </h5>
                                            <small class="text-muted">
                                                1 {{ $currency->base_currency ?? 'USD' }}
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="metric-box">
                                            <div class="small-label">Skor Risiko</div>
                                            <h5 class="fw-bold mb-0">
                                                {{ $risk->total_score ?? 0 }}/100
                                            </h5>
                                            <small class="text-muted">
                                                {{ $risk->recommendation ?? '-' }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($countryA && $countryB)
                @php
                    $riskA = $countryA['risk']->total_score ?? 0;
                    $riskB = $countryB['risk']->total_score ?? 0;

                    $saferCountry = $countryA['country']->name;

                    if ($riskB < $riskA) {
                        $saferCountry = $countryB['country']->name;
                    }

                    if ($riskA === $riskB) {
                        $saferCountry = 'Kedua negara memiliki risiko yang sama';
                    }
                @endphp

                <div class="card section-card p-4 mt-4">
                    <h5 class="fw-bold">Kesimpulan Perbandingan</h5>

                    @if ($riskA === $riskB)
                        <p class="text-muted mb-0">
                            Kedua negara memiliki skor risiko yang sama. Keputusan impor perlu mempertimbangkan faktor tambahan seperti biaya pengiriman, ketersediaan pelabuhan, dan kebutuhan bisnis.
                        </p>
                    @else
                        <p class="text-muted mb-0">
                            Berdasarkan skor risiko saat ini, negara yang lebih aman untuk dipertimbangkan adalah
                            <strong>{{ $saferCountry }}</strong>.
                            Perbandingan ini dihitung dari kombinasi indikator ekonomi, cuaca, kurs mata uang, berita, dan pelabuhan.
                        </p>
                    @endif
                </div>
            @endif
        </main>
    </div>
</div>
</body>
</html>
