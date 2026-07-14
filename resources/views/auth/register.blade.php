<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daftar - SupplyGuard</title>

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
                <span class="sg-auth-kicker">Buat akun baru</span>
                <h1>Mulai memantau negara yang penting untuk rantai pasok.</h1>
                <p>Setelah mendaftar, kamu dapat membuka dashboard, membandingkan negara, dan menyimpan daftar pantauan.</p>

                <ul class="sg-auth-feature-list">
                    <li><i class="bi bi-check2"></i> Registrasi sederhana dan aman</li>
                    <li><i class="bi bi-check2"></i> Akses dashboard monitoring utama</li>
                    <li><i class="bi bi-check2"></i> Simpan negara ke daftar pantauan</li>
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
                    <h2>Buat akun</h2>
                    <p>Lengkapi data berikut untuk mulai menggunakan SupplyGuard.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger sg-alert align-items-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>Data belum dapat disimpan.</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.process') }}" class="sg-auth-form">
                    @csrf

                    <div>
                        <label for="name" class="form-label">Nama lengkap</label>
                        <div class="sg-input-icon-wrap">
                            <i class="bi bi-person"></i>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" placeholder="Masukkan nama lengkap" required autofocus autocomplete="name">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="form-label">E-mail</label>
                        <div class="sg-input-icon-wrap">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="nama@email.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="sg-auth-two-column">
                        <div>
                            <label for="password" class="form-label">Kata sandi</label>
                            <div class="sg-input-icon-wrap">
                                <i class="bi bi-lock"></i>
                                <input type="password" id="password" name="password" class="form-control sg-password-input" placeholder="Minimal 6 karakter" required autocomplete="new-password">
                                <button type="button" class="sg-password-toggle" data-target="password" aria-label="Tampilkan kata sandi">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="password_confirmation" class="form-label">Konfirmasi</label>
                            <div class="sg-input-icon-wrap">
                                <i class="bi bi-lock-fill"></i>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control sg-password-input" placeholder="Ulangi kata sandi" required autocomplete="new-password">
                                <button type="button" class="sg-password-toggle" data-target="password_confirmation" aria-label="Tampilkan konfirmasi kata sandi">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="sg-btn-primary sg-auth-submit">
                        Daftar
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="sg-auth-switch">
                    <span>Sudah punya akun?</span>
                    <a href="{{ route('login') }}">Masuk di sini</a>
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
