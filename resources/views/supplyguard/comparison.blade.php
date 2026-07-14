@extends('layouts.supplyguard')

@section('title', 'Perbandingan Negara - SupplyGuard')
@section('page-title', 'Perbandingan Negara')
@section('page-subtitle', 'Bandingkan indikator ekonomi, cuaca, kurs, dan risiko dua negara')

@section('content')
    @php
        $scoreA = (int) data_get($countryA, 'risk.total_score', 0);
        $scoreB = (int) data_get($countryB, 'risk.total_score', 0);
        $nameA = data_get($countryA, 'country.name', 'Negara pertama');
        $nameB = data_get($countryB, 'country.name', 'Negara kedua');

        if ($scoreA < $scoreB) {
            $saferCountry = $nameA;
            $comparisonText = $nameA . ' memiliki skor risiko lebih rendah dan lebih aman untuk dipertimbangkan saat ini.';
        } elseif ($scoreB < $scoreA) {
            $saferCountry = $nameB;
            $comparisonText = $nameB . ' memiliki skor risiko lebih rendah dan lebih aman untuk dipertimbangkan saat ini.';
        } else {
            $saferCountry = 'Seimbang';
            $comparisonText = 'Kedua negara memiliki skor risiko yang sama. Pertimbangkan indikator ekonomi, cuaca, dan kurs sebelum mengambil keputusan.';
        }

        $riskGap = abs($scoreA - $scoreB);
        $sameCountry = (int) $countryAId === (int) $countryBId;
    @endphp

    <section class="sg-toolbar sg-comparison-toolbar">
        <div class="sg-toolbar-copy">
            <span class="sg-eyebrow">Mesin perbandingan</span>
            <h2>Pilih dua negara</h2>
            <p>Data diambil dari informasi terbaru yang tersimpan pada database SupplyGuard.</p>
        </div>

        <form method="GET" action="{{ route('comparison') }}" class="sg-comparison-form">
            <div class="sg-comparison-field">
                <label for="country_a" class="form-label">Negara pertama</label>
                <select name="country_a" id="country_a" class="form-select">
                    @foreach ($countries as $item)
                        <option value="{{ $item->id }}" {{ (int) $countryAId === (int) $item->id ? 'selected' : '' }}>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <span class="sg-comparison-swap" aria-hidden="true">
                <i class="bi bi-arrow-left-right"></i>
            </span>

            <div class="sg-comparison-field">
                <label for="country_b" class="form-label">Negara kedua</label>
                <select name="country_b" id="country_b" class="form-select">
                    @foreach ($countries as $item)
                        <option value="{{ $item->id }}" {{ (int) $countryBId === (int) $item->id ? 'selected' : '' }}>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="sg-btn-primary sg-comparison-submit">
                <i class="bi bi-columns-gap"></i>
                Bandingkan
            </button>
        </form>
    </section>

    @if ($sameCountry)
        <div class="alert alert-warning sg-alert" role="alert">
            <i class="bi bi-info-circle-fill"></i>
            <span>Kamu memilih negara yang sama. Pilih dua negara berbeda agar hasil perbandingan lebih berguna.</span>
        </div>
    @endif

    <section class="sg-comparison-summary">
        <article class="sg-card sg-summary-card">
            <span class="sg-summary-icon"><i class="bi bi-shield-check"></i></span>
            <div>
                <span class="sg-summary-label">Risiko lebih rendah</span>
                <strong>{{ $saferCountry }}</strong>
            </div>
        </article>

        <article class="sg-card sg-summary-card">
            <span class="sg-summary-icon"><i class="bi bi-activity"></i></span>
            <div>
                <span class="sg-summary-label">Selisih skor</span>
                <strong>{{ $riskGap }} poin</strong>
            </div>
        </article>

        <article class="sg-card sg-summary-card">
            <span class="sg-summary-icon"><i class="bi bi-calendar3"></i></span>
            <div>
                <span class="sg-summary-label">Data ekonomi</span>
                <strong>
                    {{ data_get($countryA, 'economy.year', '-') }} / {{ data_get($countryB, 'economy.year', '-') }}
                </strong>
            </div>
        </article>
    </section>

    <section class="sg-section">
        <div class="sg-section-head">
            <div>
                <h2>Hasil Perbandingan</h2>
                <p>Ringkasan indikator utama untuk membantu analisis keputusan.</p>
            </div>
        </div>

        <div class="row g-3">
            @foreach ([['data' => $countryA, 'label' => 'Negara pertama'], ['data' => $countryB, 'label' => 'Negara kedua']] as $comparison)
                @php
                    $data = $comparison['data'];
                    $country = data_get($data, 'country');
                    $economy = data_get($data, 'economy');
                    $weather = data_get($data, 'weather');
                    $currency = data_get($data, 'currency');
                    $risk = data_get($data, 'risk');
                    $riskLevel = $risk->risk_level ?? 'Low';
                    $riskClass = match ($riskLevel) {
                        'High' => 'sg-risk-high',
                        'Medium' => 'sg-risk-medium',
                        default => 'sg-risk-low',
                    };
                    $riskLabel = match ($riskLevel) {
                        'High' => 'Risiko Tinggi',
                        'Medium' => 'Risiko Sedang',
                        default => 'Risiko Rendah',
                    };
                @endphp

                <div class="col-12 col-xl-6">
                    <article class="sg-card sg-country-compare-card">
                        <div class="sg-country-compare-head">
                            <div>
                                <span class="sg-eyebrow">{{ $comparison['label'] }}</span>
                                <h3>{{ $country->name ?? 'Data negara tidak tersedia' }}</h3>
                                <p>
                                    {{ $country->region ?? 'Wilayah belum tersedia' }}
                                    @if (!empty($country?->currency_code))
                                        · {{ $country->currency_code }}
                                    @endif
                                </p>
                            </div>
                            <span class="sg-risk-pill {{ $riskClass }}">
                                <span class="sg-risk-dot"></span>
                                {{ $riskLabel }}
                            </span>
                        </div>

                        <div class="sg-compare-metric-grid">
                            <div class="sg-compare-metric">
                                <span class="sg-compare-icon"><i class="bi bi-bank"></i></span>
                                <div>
                                    <span>PDB</span>
                                    <strong>
                                        {{ $economy && $economy->gdp ? '$'.number_format($economy->gdp / 1000000000000, 2, ',', '.').' triliun' : '-' }}
                                    </strong>
                                    <small>Tahun {{ $economy->year ?? '-' }}</small>
                                </div>
                            </div>

                            <div class="sg-compare-metric">
                                <span class="sg-compare-icon"><i class="bi bi-graph-up-arrow"></i></span>
                                <div>
                                    <span>Inflasi</span>
                                    <strong>{{ $economy && $economy->inflation !== null ? number_format($economy->inflation, 2, ',', '.').'%' : '-' }}</strong>
                                    <small>Populasi {{ $economy && $economy->population ? number_format($economy->population, 0, ',', '.') : '-' }}</small>
                                </div>
                            </div>

                            <div class="sg-compare-metric">
                                <span class="sg-compare-icon"><i class="bi bi-cloud-sun"></i></span>
                                <div>
                                    <span>Cuaca</span>
                                    <strong>{{ $weather && $weather->temperature !== null ? number_format($weather->temperature, 1, ',', '.').'°C' : '-' }}</strong>
                                    <small>{{ $weather->weather_status ?? 'Status belum tersedia' }}</small>
                                </div>
                            </div>

                            <div class="sg-compare-metric">
                                <span class="sg-compare-icon"><i class="bi bi-wind"></i></span>
                                <div>
                                    <span>Angin & hujan</span>
                                    <strong>{{ $weather && $weather->wind_speed !== null ? number_format($weather->wind_speed, 1, ',', '.').' km/jam' : '-' }}</strong>
                                    <small>Curah hujan {{ $weather && $weather->rainfall !== null ? number_format($weather->rainfall, 1, ',', '.').' mm' : '-' }}</small>
                                </div>
                            </div>

                            <div class="sg-compare-metric">
                                <span class="sg-compare-icon"><i class="bi bi-currency-exchange"></i></span>
                                <div>
                                    <span>Nilai tukar</span>
                                    <strong>
                                        {{ $currency && $currency->rate !== null ? number_format($currency->rate, 6, ',', '.') : '-' }}
                                        {{ $currency->target_currency ?? '' }}
                                    </strong>
                                    <small>Basis {{ $currency->base_currency ?? 'USD' }}</small>
                                </div>
                            </div>

                            <div class="sg-compare-metric sg-compare-risk-metric">
                                <span class="sg-compare-icon"><i class="bi bi-shield-exclamation"></i></span>
                                <div>
                                    <span>Skor risiko</span>
                                    <strong>{{ $risk->total_score ?? 0 }}/100</strong>
                                    <small>{{ $risk->recommendation ?? 'Rekomendasi belum tersedia.' }}</small>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    <section class="sg-section">
        <article class="sg-card sg-comparison-conclusion">
            <span class="sg-conclusion-icon"><i class="bi bi-lightbulb"></i></span>
            <div>
                <span class="sg-eyebrow">Kesimpulan sistem</span>
                <h3>{{ $saferCountry === 'Seimbang' ? 'Hasil perbandingan seimbang' : $saferCountry.' lebih direkomendasikan' }}</h3>
                <p>{{ $comparisonText }}</p>
                <small>
                    Kesimpulan ini berdasarkan skor risiko yang tersimpan dan bukan satu-satunya dasar pengambilan keputusan.
                </small>
            </div>
        </article>
    </section>
@endsection
