@extends('layouts.supplyguard')

@section('title', 'REST API - SupplyGuard')
@section('page-title', 'REST API')
@section('page-subtitle', 'Dokumentasi endpoint dan pengujian data SupplyGuard')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/api-documentation.css') }}?v={{ filemtime(public_path('css/api-documentation.css')) }}"
    >
@endpush

@section('content')
    <section class="sg-api-page">
        <div class="sg-api-hero">
            <div>
                <span class="sg-api-eyebrow">
                    <i class="bi bi-braces-asterisk"></i>
                    Developer Center
                </span>
                <h2>SupplyGuard REST API</h2>
                <p>
                    Gunakan endpoint baca-saja untuk mengakses data negara,
                    skor risiko, pelabuhan, berita, dan nilai tukar dalam format JSON.
                </p>
            </div>

            <div class="sg-api-base-url">
                <small>Base URL lokal</small>
                <div>
                    <code id="apiBaseUrl">{{ url('/') }}</code>
                    <button type="button" data-copy-target="apiBaseUrl" title="Salin base URL">
                        <i class="bi bi-copy"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="sg-api-stat-grid">
            <article class="sg-api-stat">
                <span><i class="bi bi-globe2"></i></span>
                <div>
                    <small>Negara</small>
                    <strong>{{ number_format($statistics['countries'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-api-stat">
                <span><i class="bi bi-shield-exclamation"></i></span>
                <div>
                    <small>Skor Risiko</small>
                    <strong>{{ number_format($statistics['risk_scores'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-api-stat">
                <span><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <small>Pelabuhan</small>
                    <strong>{{ number_format($statistics['ports'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-api-stat">
                <span><i class="bi bi-newspaper"></i></span>
                <div>
                    <small>Berita</small>
                    <strong>{{ number_format($statistics['news'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-api-stat">
                <span><i class="bi bi-currency-exchange"></i></span>
                <div>
                    <small>Data Mata Uang</small>
                    <strong>{{ number_format($statistics['currencies'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <div class="sg-api-grid">
            <div class="sg-api-endpoint-list">
                @foreach ($endpoints as $endpoint)
                    <article class="sg-api-panel sg-api-endpoint-card">
                        <div class="sg-api-endpoint-head">
                            <span class="sg-api-method">{{ $endpoint['method'] }}</span>
                            <code>{{ $endpoint['path'] }}</code>
                            <button
                                type="button"
                                class="sg-api-try-button"
                                data-endpoint-key="{{ $endpoint['key'] }}"
                            >
                                <i class="bi bi-play-fill"></i>
                                Uji endpoint
                            </button>
                        </div>

                        <div class="sg-api-endpoint-body">
                            <h3>{{ $endpoint['title'] }}</h3>
                            <p>{{ $endpoint['description'] }}</p>

                            @if ($endpoint['parameters'] !== [])
                                <div class="table-responsive">
                                    <table class="table sg-api-parameter-table">
                                        <thead>
                                            <tr>
                                                <th>Parameter</th>
                                                <th>Tipe</th>
                                                <th>Status</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($endpoint['parameters'] as $parameter)
                                                <tr>
                                                    <td><code>{{ $parameter['name'] }}</code></td>
                                                    <td>{{ $parameter['type'] }}</td>
                                                    <td>
                                                        <span class="{{ $parameter['required'] ? 'is-required' : 'is-optional' }}">
                                                            {{ $parameter['required'] ? 'Wajib' : 'Opsional' }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $parameter['description'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sg-api-no-parameter">
                                    Endpoint ini tidak membutuhkan parameter.
                                </div>
                            @endif

                            <div class="sg-api-example">
                                <div>
                                    <small>Contoh request</small>
                                    <code id="example-{{ $endpoint['key'] }}">{{ url($endpoint['example']) }}</code>
                                </div>
                                <button
                                    type="button"
                                    data-copy-target="example-{{ $endpoint['key'] }}"
                                    title="Salin contoh request"
                                >
                                    <i class="bi bi-copy"></i>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="sg-api-sidebar">
                <article class="sg-api-panel sg-api-tester">
                    <div class="sg-api-panel-head">
                        <div>
                            <span>Live Tester</span>
                            <h3>Uji Endpoint</h3>
                        </div>
                    </div>

                    <form id="apiTesterForm" class="sg-api-tester-form">
                        <div class="sg-api-field">
                            <label for="apiEndpoint">Endpoint</label>
                            <select id="apiEndpoint">
                                @foreach ($endpoints as $endpoint)
                                    <option value="{{ $endpoint['key'] }}">
                                        {{ $endpoint['title'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sg-api-field" id="countryField" hidden>
                            <label for="apiCountry">Negara</label>
                            <select id="apiCountry">
                                <option value="">Semua negara</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">
                                        {{ $country->name }}
                                        @if ($country->cca3)
                                            ({{ $country->cca3 }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sg-api-field" id="keywordField" hidden>
                            <label for="apiKeyword">Kata kunci pelabuhan</label>
                            <input
                                type="text"
                                id="apiKeyword"
                                placeholder="Contoh: Tanjung"
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i>
                            Kirim Request
                        </button>
                    </form>

                    <div class="sg-api-request-preview">
                        <small>URL request</small>
                        <code id="apiRequestPreview">{{ url('/api/countries') }}</code>
                    </div>
                </article>

                <article class="sg-api-panel sg-api-response-panel">
                    <div class="sg-api-panel-head">
                        <div>
                            <span>Response</span>
                            <h3>Hasil JSON</h3>
                        </div>

                        <span id="apiStatusBadge" class="sg-api-status is-idle">
                            Belum diuji
                        </span>
                    </div>

                    <div class="sg-api-response-meta">
                        <span>
                            <i class="bi bi-clock"></i>
                            <strong id="apiResponseTime">—</strong>
                        </span>
                        <button type="button" data-copy-target="apiResponseOutput">
                            <i class="bi bi-copy"></i>
                            Salin JSON
                        </button>
                    </div>

                    <pre id="apiResponseOutput">{
  "message": "Pilih endpoint lalu tekan Kirim Request."
}</pre>
                </article>

                <article class="sg-api-panel sg-api-note">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <h3>Catatan penggunaan</h3>
                        <p>
                            Endpoint ini bersifat baca-saja dan mengembalikan
                            response JSON. Untuk produksi, tambahkan autentikasi,
                            rate limiting, pagination, dan versi API.
                        </p>
                    </div>
                </article>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const baseUrl = @json(url('/'));
            const form = document.getElementById('apiTesterForm');
            const endpointSelect = document.getElementById('apiEndpoint');
            const countryField = document.getElementById('countryField');
            const countrySelect = document.getElementById('apiCountry');
            const keywordField = document.getElementById('keywordField');
            const keywordInput = document.getElementById('apiKeyword');
            const preview = document.getElementById('apiRequestPreview');
            const output = document.getElementById('apiResponseOutput');
            const statusBadge = document.getElementById('apiStatusBadge');
            const responseTime = document.getElementById('apiResponseTime');

            const endpointConfig = {
                countries: {
                    path: '/api/countries',
                    country: false,
                    keyword: false
                },
                risk: {
                    path: '/api/risk',
                    country: true,
                    keyword: false
                },
                ports: {
                    path: '/api/ports',
                    country: true,
                    keyword: true
                },
                news: {
                    path: '/api/news',
                    country: true,
                    keyword: false
                },
                currency: {
                    path: '/api/currency',
                    country: true,
                    keyword: false,
                    countryRequired: true
                }
            };

            function buildRequestUrl() {
                const key = endpointSelect.value;
                const config = endpointConfig[key];
                const url = new URL(config.path, baseUrl);

                if (config.country && countrySelect.value) {
                    url.searchParams.set('country_id', countrySelect.value);
                }

                if (config.keyword && keywordInput.value.trim()) {
                    url.searchParams.set('q', keywordInput.value.trim());
                }

                return url.toString();
            }

            function refreshFields() {
                const config = endpointConfig[endpointSelect.value];

                countryField.hidden = !config.country;
                keywordField.hidden = !config.keyword;

                const firstCountryOption = countrySelect.options[0];

                if (firstCountryOption) {
                    firstCountryOption.textContent = config.countryRequired
                        ? 'Pilih negara'
                        : 'Semua negara';
                }

                countrySelect.required = Boolean(config.countryRequired);
                preview.textContent = buildRequestUrl();
            }

            async function copyElementText(targetId, button) {
                const target = document.getElementById(targetId);

                if (!target) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(target.textContent.trim());
                    const oldHtml = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-check-lg"></i>';

                    window.setTimeout(function () {
                        button.innerHTML = oldHtml;
                    }, 1200);
                } catch (error) {
                    console.error('Gagal menyalin teks:', error);
                }
            }

            document.querySelectorAll('[data-copy-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    copyElementText(button.dataset.copyTarget, button);
                });
            });

            document.querySelectorAll('[data-endpoint-key]').forEach(function (button) {
                button.addEventListener('click', function () {
                    endpointSelect.value = button.dataset.endpointKey;
                    refreshFields();
                    form.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                });
            });

            endpointSelect.addEventListener('change', refreshFields);
            countrySelect.addEventListener('change', refreshFields);
            keywordInput.addEventListener('input', refreshFields);

            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!form.reportValidity()) {
                    return;
                }

                const requestUrl = buildRequestUrl();
                const startedAt = performance.now();

                preview.textContent = requestUrl;
                output.textContent = 'Memuat response...';
                statusBadge.className = 'sg-api-status is-loading';
                statusBadge.textContent = 'Memuat';
                responseTime.textContent = '—';

                try {
                    const response = await fetch(requestUrl, {
                        headers: {
                            Accept: 'application/json'
                        }
                    });

                    const elapsed = Math.round(performance.now() - startedAt);
                    const text = await response.text();

                    let formattedResponse = text;

                    try {
                        formattedResponse = JSON.stringify(
                            JSON.parse(text),
                            null,
                            2
                        );
                    } catch (jsonError) {
                        // Pertahankan response teks ketika bukan JSON.
                    }

                    output.textContent = formattedResponse;
                    responseTime.textContent = elapsed + ' ms';
                    statusBadge.textContent = response.status + ' ' + response.statusText;
                    statusBadge.className = response.ok
                        ? 'sg-api-status is-success'
                        : 'sg-api-status is-error';
                } catch (error) {
                    output.textContent = JSON.stringify({
                        status: 'error',
                        message: error.message
                    }, null, 2);

                    statusBadge.textContent = 'Request gagal';
                    statusBadge.className = 'sg-api-status is-error';
                }
            });

            refreshFields();
        });
    </script>
@endpush
