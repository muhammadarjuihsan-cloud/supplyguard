<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - SupplyGuard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/supplyguard.css') }}" rel="stylesheet">
</head>
<body class="sg-auth-body">
    <main class="sg-auth-shell">
        <section class="sg-auth-info">
            <a href="{{ route('login') }}" class="sg-auth-brand">
                <span><i class="bi bi-shield-check"></i></span>
                <div>
                    <strong>SupplyGuard</strong>
                    <small>Risk Intelligence</small>
                </div>
            </a>

            <div class="sg-auth-info-copy">
                <span class="sg-auth-kicker">Platform monitoring</span>
                <h1>Pantau risiko rantai pasok dalam satu dashboard.</h1>
                <p>Analisis indikator ekonomi, cuaca, kurs, berita, dan kondisi pelabuhan secara lebih terstruktur.</p>

                <ul class="sg-auth-feature-list">
                    <li><i class="bi bi-check2"></i> Dashboard negara dan skor risiko</li>
                    <li><i class="bi bi-check2"></i> Grafik, peta pelabuhan, dan perbandingan negara</li>
                    <li><i class="bi bi-check2"></i> Daftar pantauan dan panel administrasi</li>
                </ul>
            </div>

            <small class="sg-auth-footer-copy">SupplyGuard · Project UAS Pemrograman Web</small>
        </section>

        <section class="sg-auth-form-panel">
            <div class="sg-auth-form-wrap">
                <div class="sg-auth-mobile-brand">
                    <span><i class="bi bi-shield-check"></i></span>
                    <strong>SupplyGuard</strong>
                </div>

                <div class="sg-auth-heading">
                    <h2>Masuk ke akun</h2>
                    <p>Gunakan e-mail dan kata sandi yang sudah terdaftar.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger sg-alert" role="alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.process') }}" class="sg-auth-form">
                    @csrf

                    <div>
                        <label for="email" class="form-label">E-mail</label>
                        <div class="sg-input-icon-wrap">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus autocomplete="email">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="form-label">Kata sandi</label>
                        <div class="sg-input-icon-wrap">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password" name="password" class="form-control sg-password-input" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                            <button type="button" class="sg-password-toggle" data-target="password" aria-label="Tampilkan kata sandi">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="sg-btn-primary sg-auth-submit">
                        Masuk
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="sg-auth-switch">
                    <span>Belum punya akun?</span>
                    <a href="{{ route('register') }}">Daftar sekarang</a>
                </div>

                <div class="sg-demo-box">
                    <div class="sg-demo-box-head">
                        <i class="bi bi-info-circle"></i>
                        <strong>Akun demonstrasi pengguna</strong>
                    </div>
                    <div class="sg-demo-account">
                        <span>Pengguna</span>
                        <code>user@supplyguard.test</code>
                        <small>password</small>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.querySelectorAll('.sg-password-toggle').forEach(button => {
            button.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                if (!input) return;
                const showPassword = input.type === 'password';
                input.type = showPassword ? 'text' : 'password';
                this.innerHTML = `<i class="bi ${showPassword ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
            });
        });
    </script>
</body>
</html>
