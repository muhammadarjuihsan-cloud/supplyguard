<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SupplyGuard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card border-0 shadow-lg" style="max-width: 430px; width: 100%; border-radius: 20px;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h3 class="fw-bold">SupplyGuard</h3>
                <p class="text-muted mb-0">Global Supply Chain Risk Intelligence</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.process') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-dark w-100">Login</button>
            </form>

            <hr>

            <div class="text-center">
                <small class="text-muted">Belum punya akun?</small><br>
                <a href="{{ route('register') }}">Register di sini</a>
            </div>

            <div class="mt-4 p-3 bg-light rounded">
                <small class="text-muted d-block">Akun demo:</small>
                <small>Admin: admin@supplyguard.test / password</small><br>
                <small>User: user@supplyguard.test / password</small>
            </div>
        </div>
    </div>
</div>
</body>
</html>
