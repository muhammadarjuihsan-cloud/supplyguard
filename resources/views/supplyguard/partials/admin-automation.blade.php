<section class="sg-section" id="otomasi-analisis" style="scroll-margin-top: 96px;">
    <div class="sg-section-head">
        <div>
            <h2>Otomasi Analisis</h2>
            <p>Jalankan ulang sentiment analysis dan Risk Scoring Engine langsung dari halaman admin.</p>
        </div>
        <span class="sg-section-count">
            <i class="bi bi-cpu"></i>
            Backend otomatis
        </span>
    </div>

    @if (session('success'))
        <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-check-circle-fill mt-1"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    @if (session('admin_process_summary'))
        @php($process = session('admin_process_summary'))

        <article class="sg-card sg-admin-card mb-3">
            <div class="sg-admin-card-head">
                <div>
                    <h3>Ringkasan proses terakhir</h3>
                    <p>
                        {{ $process['type'] === 'risk'
                            ? 'Perhitungan risiko seluruh negara'
                            : 'Analisis sentimen seluruh berita' }}
                    </p>
                </div>
                <span class="sg-admin-card-badge">
                    <i class="bi bi-stopwatch"></i>
                    {{ number_format($process['duration'], 2, ',', '.') }} detik
                </span>
            </div>

            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100">
                        <small class="text-muted d-block">Diproses</small>
                        <strong class="fs-4">{{ $process['processed'] }}</strong>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100">
                        <small class="text-muted d-block">Berhasil</small>
                        <strong class="fs-4">{{ $process['success'] }}</strong>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100">
                        <small class="text-muted d-block">Gagal</small>
                        <strong class="fs-4">{{ $process['failed'] }}</strong>
                    </div>
                </div>

                @if ($process['type'] === 'risk')
                    <div class="col-6 col-lg-3">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Rendah / Sedang / Tinggi</small>
                            <strong class="fs-5">
                                {{ $process['low'] }} /
                                {{ $process['medium'] }} /
                                {{ $process['high'] }}
                            </strong>
                        </div>
                    </div>
                @else
                    <div class="col-6 col-lg-3">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Positif / Netral / Negatif</small>
                            <strong class="fs-5">
                                {{ $process['positive'] }} /
                                {{ $process['neutral'] }} /
                                {{ $process['negative'] }}
                            </strong>
                        </div>
                    </div>
                @endif
            </div>
        </article>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <article class="sg-card sg-admin-card h-100">
                <div class="sg-admin-card-head">
                    <div>
                        <h3>Sentiment Analysis</h3>
                        <p>Analisis ulang judul dan deskripsi seluruh berita menggunakan kamus terbaru.</p>
                    </div>
                    <span class="sg-admin-card-badge">
                        <i class="bi bi-chat-square-text"></i>
                        Berita
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Sudah dianalisis</small>
                            <strong class="fs-4">
                                {{ number_format($automation['news_analyzed'], 0, ',', '.') }}
                            </strong>
                            <span class="text-muted">
                                / {{ number_format($automation['news_total'], 0, ',', '.') }} berita
                            </span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Pembaruan terakhir</small>
                            <strong class="d-block mt-1">
                                @if ($automation['sentiment_last_updated'])
                                    {{ \Carbon\Carbon::parse($automation['sentiment_last_updated'])->format('d M Y, H:i') }}
                                @else
                                    Belum pernah
                                @endif
                            </strong>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border">
                    Setelah kamus positif atau negatif diubah, jalankan proses ini terlebih dahulu.
                    Sesudah itu, hitung ulang risiko agar skor berita terbaru masuk ke Risk Scoring Engine.
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.sentiment.reanalyze') }}"
                    class="js-admin-process-form"
                    data-confirm="Analisis ulang seluruh berita menggunakan kamus sentimen terbaru?"
                >
                    @csrf
                    <button type="submit" class="sg-btn-secondary w-100">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <i class="bi bi-arrow-repeat"></i>
                        <span class="js-button-label">Analisis Ulang Sentimen</span>
                    </button>
                </form>
            </article>
        </div>

        <div class="col-12 col-xl-6">
            <article class="sg-card sg-admin-card h-100">
                <div class="sg-admin-card-head">
                    <div>
                        <h3>Risk Scoring Engine</h3>
                        <p>Hitung ulang risiko seluruh negara berdasarkan cuaca, inflasi, kurs, berita, dan pelabuhan.</p>
                    </div>
                    <span class="sg-admin-card-badge">
                        <i class="bi bi-speedometer2"></i>
                        Risiko
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Negara memiliki skor</small>
                            <strong class="fs-4">
                                {{ number_format($automation['risk_country_count'], 0, ',', '.') }}
                            </strong>
                            <span class="text-muted">
                                / {{ number_format($stats['countries'], 0, ',', '.') }} negara
                            </span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Perhitungan terakhir</small>
                            <strong class="d-block mt-1">
                                @if ($automation['risk_last_updated'])
                                    {{ \Carbon\Carbon::parse($automation['risk_last_updated'])->format('d M Y, H:i') }}
                                @else
                                    Belum pernah
                                @endif
                            </strong>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border">
                    Proses ini memperbarui <code>risk_scores</code> dan riwayat harian
                    <code>risk_histories</code> untuk seluruh negara.
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.risk.recalculate') }}"
                    class="js-admin-process-form"
                    data-confirm="Hitung ulang skor risiko untuk seluruh negara sekarang?"
                >
                    @csrf
                    <button type="submit" class="sg-btn-primary w-100">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <i class="bi bi-calculator"></i>
                        <span class="js-button-label">Hitung Ulang Semua Risiko</span>
                    </button>
                </form>
            </article>
        </div>
    </div>
</section>

<script>
    if (window.location.hash === '#otomasi-analisis') {
        window.history.replaceState(
            null,
            document.title,
            window.location.pathname + window.location.search
        );
    }

    document.querySelectorAll('.js-admin-process-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const confirmationMessage = form.dataset.confirm || '';

            if (confirmationMessage && !window.confirm(confirmationMessage)) {
                event.preventDefault();
                return;
            }

            const button = form.querySelector('button[type="submit"]');
            const spinner = button?.querySelector('.spinner-border');
            const icon = button?.querySelector('.bi');
            const label = button?.querySelector('.js-button-label');

            if (button) {
                button.disabled = true;
            }

            if (spinner) {
                spinner.classList.remove('d-none');
            }

            if (icon) {
                icon.classList.add('d-none');
            }

            if (label) {
                label.textContent = 'Sedang memproses...';
            }
        });
    });
</script>
