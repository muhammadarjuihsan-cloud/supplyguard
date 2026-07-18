@extends('layouts.supplyguard')

@section('title', 'Visualisasi Data - SupplyGuard')
@section('page-title', 'Visualisasi Data')
@section('page-subtitle', 'Analisis visual risiko, ekonomi, berita, mata uang, dan kelengkapan data global')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/visualization.css') }}?v={{ filemtime(public_path('css/visualization.css')) }}"
    >
@endpush

@section('content')
    <section class="sg-viz-page">
        <div class="sg-viz-hero">
            <div>
                <span class="sg-viz-eyebrow">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    Global Analytics
                </span>
                <h2>Visualisasi Risiko Rantai Pasok</h2>
                <p>
                    Lihat distribusi risiko global, komponen pembentuk risiko,
                    cakupan data, dan tren indikator negara dalam bentuk grafik interaktif.
                </p>
            </div>

            <form method="GET" action="{{ route('visualization.index') }}" class="sg-viz-country-form">
                <label for="country_id">Analisis negara</label>
                <div>
                    <select name="country_id" id="country_id">
                        @foreach ($countries as $country)
                            <option
                                value="{{ $country->id }}"
                                @selected((int) $selectedCountryId === (int) $country->id)
                            >
                                {{ $country->name }}
                                @if ($country->cca3)
                                    ({{ $country->cca3 }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button type="submit">
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="sg-viz-stat-grid">
            <article class="sg-viz-stat">
                <span class="sg-viz-stat-icon">
                    <i class="bi bi-globe2"></i>
                </span>
                <div>
                    <small>Total Negara</small>
                    <strong>{{ number_format($statistics['total_countries'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-viz-stat">
                <span class="sg-viz-stat-icon">
                    <i class="bi bi-speedometer2"></i>
                </span>
                <div>
                    <small>Rata-rata Risiko</small>
                    <strong>{{ $statistics['average_risk'] }}/100</strong>
                </div>
            </article>

            <article class="sg-viz-stat">
                <span class="sg-viz-stat-icon">
                    <i class="bi bi-database-check"></i>
                </span>
                <div>
                    <small>Data Lengkap</small>
                    <strong>{{ number_format($statistics['complete_data'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-viz-stat">
                <span class="sg-viz-stat-icon">
                    <i class="bi bi-newspaper"></i>
                </span>
                <div>
                    <small>Total Berita</small>
                    <strong>{{ number_format($statistics['total_news'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-viz-stat">
                <span class="sg-viz-stat-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <div>
                    <small>Total Pelabuhan</small>
                    <strong>{{ number_format($statistics['total_ports'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <div class="sg-viz-grid sg-viz-grid-top">
            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Risiko Global</span>
                        <h3>Distribusi Tingkat Risiko</h3>
                    </div>
                </div>
                <div class="sg-viz-chart sg-viz-chart-doughnut">
                    <canvas id="riskDistributionChart"></canvas>
                </div>
            </article>

            <article class="sg-viz-panel sg-viz-panel-wide">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Peringkat Global</span>
                        <h3>10 Negara dengan Risiko Tertinggi</h3>
                    </div>
                    <a href="{{ route('comparison') }}">
                        Buka Perbandingan
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                </div>
                <div class="sg-viz-chart sg-viz-chart-bar">
                    <canvas id="topRiskChart"></canvas>
                </div>
            </article>
        </div>

        <div class="sg-viz-grid sg-viz-grid-middle">
            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Komponen Risiko</span>
                        <h3>Rata-rata Skor Global</h3>
                    </div>
                </div>
                <div class="sg-viz-chart sg-viz-chart-radar">
                    <canvas id="componentChart"></canvas>
                </div>
            </article>

            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Cakupan Data</span>
                        <h3>Negara per Sumber Data</h3>
                    </div>
                </div>
                <div class="sg-viz-chart sg-viz-chart-coverage">
                    <canvas id="coverageChart"></canvas>
                </div>
            </article>

            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Risiko Wilayah</span>
                        <h3>Rata-rata Berdasarkan Region</h3>
                    </div>
                </div>
                <div class="sg-viz-chart sg-viz-chart-region">
                    <canvas id="regionRiskChart"></canvas>
                </div>
            </article>
        </div>

        <div class="sg-viz-country-heading">
            <div>
                <span>Analisis Negara Aktif</span>
                <h3>{{ $selectedCountry->name ?? 'Negara' }}</h3>
            </div>
            <a href="{{ route('dashboard', ['country_id' => $selectedCountryId]) }}">
                <i class="bi bi-eye"></i>
                Buka Dasbor Negara
            </a>
        </div>

        <div class="sg-viz-grid sg-viz-grid-country">
            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Tren Risiko</span>
                        <h3>Perubahan Skor Risiko</h3>
                    </div>
                </div>
                <div class="sg-viz-chart">
                    <canvas id="riskTrendChart"></canvas>
                </div>
                @if ($selectedRiskHistory->isEmpty())
                    <p class="sg-viz-empty-note">Riwayat risiko belum tersedia.</p>
                @endif
            </article>

            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Tren Ekonomi</span>
                        <h3>PDB dan Inflasi</h3>
                    </div>
                </div>
                <div class="sg-viz-chart">
                    <canvas id="economyTrendChart"></canvas>
                </div>
                @if ($selectedEconomyTrend->isEmpty())
                    <p class="sg-viz-empty-note">Riwayat ekonomi belum tersedia.</p>
                @endif
            </article>

            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Sentimen Berita</span>
                        <h3>Komposisi Artikel</h3>
                    </div>
                </div>
                <div class="sg-viz-chart sg-viz-chart-doughnut">
                    <canvas id="sentimentChart"></canvas>
                </div>
            </article>

            <article class="sg-viz-panel">
                <div class="sg-viz-panel-head">
                    <div>
                        <span>Mata Uang</span>
                        <h3>
                            @if ($selectedCurrency)
                                {{ $selectedCurrency->base_currency }}
                                ke
                                {{ $selectedCurrency->target_currency }}
                            @else
                                Riwayat Nilai Tukar
                            @endif
                        </h3>
                    </div>
                </div>
                <div class="sg-viz-chart">
                    <canvas id="currencyTrendChart"></canvas>
                </div>
                @if ($selectedCurrencyTrend->isEmpty())
                    <p class="sg-viz-empty-note">Riwayat mata uang belum tersedia.</p>
                @endif
            </article>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            Chart.defaults.font.family = 'Inter, sans-serif';
            Chart.defaults.color = '#667085';
            Chart.defaults.borderColor = '#e8ecf2';

            new Chart(document.getElementById('riskDistributionChart'), {
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
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 18
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('topRiskChart'), {
                type: 'bar',
                data: {
                    labels: @json($topRiskCountries->pluck('name')),
                    datasets: [{
                        label: 'Skor Risiko',
                        data: @json($topRiskCountries->pluck('total_score')->map(fn ($value) => (int) $value)),
                        backgroundColor: '#2463eb',
                        borderRadius: 7,
                        maxBarThickness: 34
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            new Chart(document.getElementById('componentChart'), {
                type: 'radar',
                data: {
                    labels: ['Cuaca', 'Inflasi', 'Mata Uang', 'Berita', 'Pelabuhan'],
                    datasets: [{
                        label: 'Rata-rata Skor',
                        data: [
                            {{ $componentAverages['weather'] }},
                            {{ $componentAverages['inflation'] }},
                            {{ $componentAverages['currency'] }},
                            {{ $componentAverages['news'] }},
                            {{ $componentAverages['port'] }}
                        ],
                        borderColor: '#2463eb',
                        backgroundColor: 'rgba(36, 99, 235, 0.16)',
                        pointBackgroundColor: '#2463eb',
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20,
                                backdropColor: 'transparent'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            new Chart(document.getElementById('coverageChart'), {
                type: 'bar',
                data: {
                    labels: @json(array_keys($dataCoverage)),
                    datasets: [{
                        label: 'Negara',
                        data: @json(array_values($dataCoverage)),
                        backgroundColor: '#16877b',
                        borderRadius: 7,
                        maxBarThickness: 34
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: {{ max(250, $statistics['total_countries']) }}
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            new Chart(document.getElementById('regionRiskChart'), {
                type: 'bar',
                data: {
                    labels: @json($regionRisk->pluck('region_name')),
                    datasets: [{
                        label: 'Rata-rata Risiko',
                        data: @json($regionRisk->pluck('average_score')->map(fn ($value) => (float) $value)),
                        backgroundColor: '#7c5ce7',
                        borderRadius: 7,
                        maxBarThickness: 34
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            new Chart(document.getElementById('riskTrendChart'), {
                type: 'line',
                data: {
                    labels: @json($selectedRiskHistory->pluck('recorded_date')),
                    datasets: [{
                        label: 'Skor Risiko',
                        data: @json($selectedRiskHistory->pluck('total_score')->map(fn ($value) => (int) $value)),
                        borderColor: '#e24d4d',
                        backgroundColor: 'rgba(226, 77, 77, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            new Chart(document.getElementById('economyTrendChart'), {
                type: 'line',
                data: {
                    labels: @json($selectedEconomyTrend->pluck('year')),
                    datasets: [
                        {
                            label: 'PDB (triliun USD)',
                            data: @json($selectedEconomyTrend->pluck('gdp')->map(
                                fn ($value) => $value !== null ? round(((float) $value) / 1000000000000, 3) : null
                            )),
                            borderColor: '#16877b',
                            backgroundColor: 'rgba(22, 135, 123, 0.1)',
                            yAxisID: 'y',
                            tension: 0.35
                        },
                        {
                            label: 'Inflasi (%)',
                            data: @json($selectedEconomyTrend->pluck('inflation')->map(
                                fn ($value) => $value !== null ? (float) $value : null
                            )),
                            borderColor: '#d48116',
                            backgroundColor: 'rgba(212, 129, 22, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            position: 'left',
                            beginAtZero: false
                        },
                        y1: {
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('sentimentChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Positif', 'Netral', 'Negatif'],
                    datasets: [{
                        data: [
                            {{ $selectedSentiment['positive'] }},
                            {{ $selectedSentiment['neutral'] }},
                            {{ $selectedSentiment['negative'] }}
                        ],
                        backgroundColor: ['#18a875', '#7b8798', '#e24d4d'],
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
                                padding: 16
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('currencyTrendChart'), {
                type: 'line',
                data: {
                    labels: @json($selectedCurrencyTrend->pluck('rate_date')),
                    datasets: [{
                        label: 'Nilai Tukar',
                        data: @json($selectedCurrencyTrend->pluck('rate')->map(fn ($value) => (float) $value)),
                        borderColor: '#7c5ce7',
                        backgroundColor: 'rgba(124, 92, 231, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3
                    }]
                },
                options: {
                    maintainAspectRatio: false,
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
