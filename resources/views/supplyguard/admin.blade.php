<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - SupplyGuard</title>
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

        .stat-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .small-label {
            color: #64748b;
            font-size: 13px;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }
    </style>
</head>

<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar p-4">
            <div class="brand mb-4">SupplyGuard</div>
            <div class="small text-secondary mb-4">
                Admin Control Center
            </div>

            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('comparison') }}">Perbandingan Negara</a>
            <a href="{{ route('admin.index') }}">Admin Dashboard</a>

            <hr class="border-secondary">

            <div class="small text-secondary mb-2">
                Login as:<br>
                <strong class="text-white">{{ auth()->user()->name }}</strong>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm w-100">
                    Logout
                </button>
            </form>
        </aside>

        <main class="col-md-9 col-lg-10 p-4">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Admin Dashboard</h2>
                <p class="text-muted mb-0">
                    Kelola user, dataset pelabuhan, artikel analisis, dan kamus sentiment analysis.
                </p>
            </div>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Terjadi kesalahan:</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4 col-xl-2">
                    <div class="card stat-card p-3">
                        <div class="small-label">Users</div>
                        <h4 class="fw-bold mb-0">{{ $stats['users'] }}</h4>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card stat-card p-3">
                        <div class="small-label">Countries</div>
                        <h4 class="fw-bold mb-0">{{ $stats['countries'] }}</h4>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card stat-card p-3">
                        <div class="small-label">Ports</div>
                        <h4 class="fw-bold mb-0">{{ $stats['ports'] }}</h4>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card stat-card p-3">
                        <div class="small-label">Articles</div>
                        <h4 class="fw-bold mb-0">{{ $stats['articles'] }}</h4>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card stat-card p-3">
                        <div class="small-label">Positive Words</div>
                        <h4 class="fw-bold mb-0">{{ $stats['positive_words'] }}</h4>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card stat-card p-3">
                        <div class="small-label">Negative Words</div>
                        <h4 class="fw-bold mb-0">{{ $stats['negative_words'] }}</h4>
                    </div>
                </div>
            </div>

            <section class="mb-4">
                <div class="card section-card p-3">
                    <h5 class="fw-bold mb-3">Kelola User</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Dibuat</th>
                                <th width="220">Aksi</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge {{ $user->role === 'admin' ? 'bg-dark' : 'bg-secondary' }}">
                                            {{ $user->role }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <form method="POST" action="{{ route('admin.users.role', $user->id) }}">
                                                @csrf
                                                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>user</option>
                                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>admin</option>
                                                </select>
                                            </form>

                                            @if ($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Hapus
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="card section-card p-3">
                    <h5 class="fw-bold mb-3">Kelola Dataset Pelabuhan</h5>

                    <form method="POST" action="{{ route('admin.ports.store') }}" class="row g-3 mb-4">
                        @csrf

                        <div class="col-md-4">
                            <label class="form-label">Nama Pelabuhan</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Negara</label>
                            <select name="country_id" class="form-select">
                                <option value="">Pilih negara</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Kode</label>
                            <input type="text" name="port_code" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tipe</label>
                            <input type="text" name="type" class="form-control" placeholder="Seaport">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Latitude</label>
                            <input type="number" step="any" name="latitude" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Longitude</label>
                            <input type="number" step="any" name="longitude" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Deskripsi</label>
                            <input type="text" name="description" class="form-control">
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-dark w-100">
                                Tambah
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Pelabuhan</th>
                                <th>Negara</th>
                                <th>Kode</th>
                                <th>Tipe</th>
                                <th>Koordinat</th>
                                <th width="100">Aksi</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach ($ports as $port)
                                <tr>
                                    <td>{{ $port->name }}</td>
                                    <td>{{ $port->country_name ?? $port->linked_country ?? '-' }}</td>
                                    <td>{{ $port->port_code ?? '-' }}</td>
                                    <td>{{ $port->type ?? '-' }}</td>
                                    <td>{{ $port->latitude }}, {{ $port->longitude }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.ports.destroy', $port->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="card section-card p-3">
                    <h5 class="fw-bold mb-3">Kelola Artikel Analisis</h5>

                    <form method="POST" action="{{ route('admin.articles.store') }}" class="row g-3 mb-4">
                        @csrf

                        <div class="col-md-5">
                            <label class="form-label">Judul Artikel</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Kategori</label>
                            <input type="text" name="category" class="form-control" placeholder="Risk Analysis">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_published" id="is_published">
                                <label class="form-check-label" for="is_published">
                                    Published
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Isi Artikel</label>
                            <textarea name="content" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-dark w-100">
                                Tambah Artikel
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th width="100">Aksi</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach ($articles as $article)
                                <tr>
                                    <td>
                                        <strong>{{ $article->title }}</strong><br>
                                        <small class="text-muted">
                                            {{ \Illuminate\Support\Str::limit($article->content, 90) }}
                                        </small>
                                    </td>
                                    <td>{{ $article->category ?? '-' }}</td>
                                    <td>{{ $article->author_name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $article->is_published ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $article->is_published ? 'Published' : 'Draft' }}
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.articles.destroy', $article->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="card section-card p-3">
                    <h5 class="fw-bold mb-3">Kelola Kamus Sentiment Analysis</h5>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="fw-bold">Positive Words</h6>

                                <form method="POST" action="{{ route('admin.positiveWords.store') }}" class="d-flex gap-2 mb-3">
                                    @csrf
                                    <input type="text" name="word" class="form-control" placeholder="growth" required>
                                    <button class="btn btn-dark" type="submit">
                                        Tambah
                                    </button>
                                </form>

                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($positiveWords as $word)
                                        <form method="POST" action="{{ route('admin.positiveWords.destroy', $word->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                {{ $word->word }} ×
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="fw-bold">Negative Words</h6>

                                <form method="POST" action="{{ route('admin.negativeWords.store') }}" class="d-flex gap-2 mb-3">
                                    @csrf
                                    <input type="text" name="word" class="form-control" placeholder="delay" required>
                                    <button class="btn btn-dark" type="submit">
                                        Tambah
                                    </button>
                                </form>

                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($negativeWords as $word)
                                        <form method="POST" action="{{ route('admin.negativeWords.destroy', $word->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                {{ $word->word }} ×
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
