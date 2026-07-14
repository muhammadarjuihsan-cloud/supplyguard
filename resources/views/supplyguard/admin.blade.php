@extends('layouts.supplyguard')

@section('title', 'Admin Dashboard - SupplyGuard')
@section('page-title', 'Admin Dashboard')
@section('page-subtitle', 'Kelola pengguna, data pelabuhan, artikel, dan kamus sentimen')

@section('content')
    @php
        $statCards = [
            ['label' => 'Pengguna', 'value' => $stats['users'], 'icon' => 'bi-people'],
            ['label' => 'Negara', 'value' => $stats['countries'], 'icon' => 'bi-globe-asia-australia'],
            ['label' => 'Pelabuhan', 'value' => $stats['ports'], 'icon' => 'bi-geo-alt'],
            ['label' => 'Artikel', 'value' => $stats['articles'], 'icon' => 'bi-file-earmark-text'],
            ['label' => 'Kata positif', 'value' => $stats['positive_words'], 'icon' => 'bi-emoji-smile'],
            ['label' => 'Kata negatif', 'value' => $stats['negative_words'], 'icon' => 'bi-emoji-frown'],
        ];
    @endphp

    <section class="sg-toolbar sg-admin-toolbar">
        <div class="sg-toolbar-copy">
            <span class="sg-eyebrow">Pusat administrasi</span>
            <h2>Kontrol data SupplyGuard</h2>
            <p>Perubahan pada halaman ini langsung memengaruhi data yang ditampilkan pada dashboard.</p>
        </div>
        <div class="sg-admin-toolbar-note">
            <i class="bi bi-shield-lock"></i>
            <span>Hanya administrator yang dapat mengakses halaman ini.</span>
        </div>
    </section>

    @include('supplyguard.partials.admin-automation')

    <section class="sg-admin-stats">
        @foreach ($statCards as $card)
            <article class="sg-card sg-admin-stat-card">
                <span class="sg-admin-stat-icon"><i class="bi {{ $card['icon'] }}"></i></span>
                <div>
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ number_format($card['value'], 0, ',', '.') }}</strong>
                </div>
            </article>
        @endforeach
    </section>

    <section class="sg-section" id="pengguna">
        <div class="sg-section-head">
            <div>
                <h2>Kelola Pengguna</h2>
                <p>Atur peran akun dan hapus pengguna yang sudah tidak digunakan.</p>
            </div>
            <span class="sg-section-count">{{ $users->count() }} akun</span>
        </div>

        <article class="sg-card sg-admin-card">
            <div class="table-responsive">
                <table class="table sg-admin-table align-middle">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>E-mail</th>
                            <th>Peran saat ini</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <div class="sg-table-user">
                                        <span class="sg-table-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $user->name }}</strong>
                                            @if ((int) $user->id === (int) auth()->id())
                                                <small>Akun kamu</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="sg-role-pill {{ $user->role === 'admin' ? 'is-admin' : 'is-user' }}">
                                        {{ $user->role === 'admin' ? 'Administrator' : 'Pengguna' }}
                                    </span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($user->created_at)->format('d M Y, H:i') }}</td>
                                <td>
                                    <div class="sg-table-actions justify-content-end">
                                        @if ((int) $user->id === (int) auth()->id())
                                            <button type="button" class="sg-btn-secondary" disabled title="Peran akun sendiri tidak dapat diubah">
                                                <i class="bi bi-lock"></i>
                                                Akun aktif
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.role', $user->id) }}" class="sg-role-form">
                                                @csrf
                                                <select name="role" class="form-select" aria-label="Ubah peran {{ $user->name }}">
                                                    <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>Pengguna</option>
                                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Administrator</option>
                                                </select>
                                                <button type="submit" class="sg-btn-secondary" title="Simpan perubahan peran">
                                                    <i class="bi bi-check2"></i>
                                                    Simpan
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('admin.users.destroy', $user->id) }}"
                                                  onsubmit="return confirm('Hapus akun {{ addslashes($user->name) }}? Tindakan ini tidak dapat dibatalkan.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sg-icon-danger" title="Hapus pengguna" aria-label="Hapus {{ $user->name }}">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="sg-empty-state">
                                        <i class="bi bi-people"></i>
                                        <strong>Belum ada pengguna</strong>
                                        <span>Data pengguna akan muncul di sini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="sg-section" id="pelabuhan-admin">
        <div class="sg-section-head">
            <div>
                <h2>Dataset Pelabuhan</h2>
                <p>Tambahkan lokasi pelabuhan untuk ditampilkan pada peta monitoring.</p>
            </div>
            <span class="sg-section-count">{{ $ports->count() }} lokasi</span>
        </div>

        <article class="sg-card sg-admin-card">
            <div class="sg-admin-card-head">
                <div>
                    <h3>Tambah pelabuhan</h3>
                    <p>Gunakan koordinat desimal, misalnya -6.104 dan 106.886.</p>
                </div>
                <span class="sg-admin-card-badge"><i class="bi bi-geo-alt"></i> Data peta</span>
            </div>

            <form method="POST" action="{{ route('admin.ports.store') }}" class="sg-admin-form">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label for="port_name" class="form-label">Nama pelabuhan</label>
                        <input type="text" id="port_name" name="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: Tanjung Priok" required>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label for="port_country" class="form-label">Negara</label>
                        <select name="country_id" id="port_country" class="form-select">
                            <option value="">Pilih negara</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" {{ (string) old('country_id') === (string) $country->id ? 'selected' : '' }}>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="port_code" class="form-label">Kode</label>
                        <input type="text" id="port_code" name="port_code" class="form-control" value="{{ old('port_code') }}" placeholder="IDTPP">
                    </div>
                    <div class="col-6 col-md-3 col-lg-3">
                        <label for="port_type" class="form-label">Tipe</label>
                        <input type="text" id="port_type" name="type" class="form-control" value="{{ old('type') }}" placeholder="Pelabuhan laut">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="port_latitude" class="form-label">Lintang</label>
                        <input type="number" step="any" id="port_latitude" name="latitude" class="form-control" value="{{ old('latitude') }}" placeholder="-6.104">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="port_longitude" class="form-label">Bujur</label>
                        <input type="number" step="any" id="port_longitude" name="longitude" class="form-control" value="{{ old('longitude') }}" placeholder="106.886">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="port_description" class="form-label">Deskripsi</label>
                        <input type="text" id="port_description" name="description" class="form-control" value="{{ old('description') }}" placeholder="Keterangan singkat pelabuhan">
                    </div>
                    <div class="col-12 col-lg-2 d-flex align-items-end">
                        <button type="submit" class="sg-btn-primary w-100">
                            <i class="bi bi-plus-lg"></i>
                            Tambah
                        </button>
                    </div>
                </div>
            </form>

            <div class="sg-admin-table-block">
                <div class="table-responsive">
                    <table class="table sg-admin-table align-middle">
                        <thead>
                            <tr>
                                <th>Pelabuhan</th>
                                <th>Negara</th>
                                <th>Kode</th>
                                <th>Tipe</th>
                                <th>Koordinat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ports as $port)
                                <tr>
                                    <td><strong>{{ $port->name }}</strong></td>
                                    <td>{{ $port->linked_country ?? $port->country_name ?? '-' }}</td>
                                    <td><span class="sg-code-pill">{{ $port->port_code ?? '-' }}</span></td>
                                    <td>{{ $port->type ?? '-' }}</td>
                                    <td>
                                        @if ($port->latitude !== null && $port->longitude !== null)
                                            {{ number_format($port->latitude, 5, ',', '.') }}, {{ number_format($port->longitude, 5, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <div class="sg-table-actions justify-content-end">
                                            <form method="POST"
                                                  action="{{ route('admin.ports.destroy', $port->id) }}"
                                                  onsubmit="return confirm('Hapus pelabuhan {{ addslashes($port->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sg-icon-danger" title="Hapus pelabuhan" aria-label="Hapus {{ $port->name }}">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="sg-empty-state">
                                            <i class="bi bi-geo-alt"></i>
                                            <strong>Belum ada data pelabuhan</strong>
                                            <span>Tambahkan lokasi melalui formulir di atas.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </section>

    <section class="sg-section" id="artikel-admin">
        <div class="sg-section-head">
            <div>
                <h2>Artikel Analisis</h2>
                <p>Kelola artikel internal yang digunakan untuk informasi dan analisis.</p>
            </div>
            <span class="sg-section-count">{{ $articles->count() }} artikel</span>
        </div>

        <article class="sg-card sg-admin-card">
            <div class="sg-admin-card-head">
                <div>
                    <h3>Tulis artikel baru</h3>
                    <p>Gunakan judul singkat dan isi yang mudah dipahami.</p>
                </div>
                <span class="sg-admin-card-badge"><i class="bi bi-file-earmark-text"></i> Konten</span>
            </div>

            <form method="POST" action="{{ route('admin.articles.store') }}" class="sg-admin-form">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <label for="article_title" class="form-label">Judul artikel</label>
                        <input type="text" id="article_title" name="title" class="form-control" value="{{ old('title') }}" placeholder="Masukkan judul artikel" required>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label for="article_category" class="form-label">Kategori</label>
                        <input type="text" id="article_category" name="category" class="form-control" value="{{ old('category') }}" placeholder="Ekonomi, logistik, cuaca">
                    </div>
                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label d-block">Status</label>
                        <label class="sg-switch-row" for="is_published">
                            <input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}>
                            <span>Publikasikan</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <label for="article_content" class="form-label">Isi artikel</label>
                        <textarea name="content" id="article_content" class="form-control" rows="5" placeholder="Tulis isi artikel di sini..." required>{{ old('content') }}</textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="sg-btn-primary">
                            <i class="bi bi-plus-lg"></i>
                            Simpan artikel
                        </button>
                    </div>
                </div>
            </form>

            <div class="sg-admin-table-block">
                <div class="table-responsive">
                    <table class="table sg-admin-table align-middle">
                        <thead>
                            <tr>
                                <th>Artikel</th>
                                <th>Kategori</th>
                                <th>Penulis</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($articles as $article)
                                <tr>
                                    <td class="sg-article-cell">
                                        <strong>{{ $article->title }}</strong>
                                        <small>{{ \Illuminate\Support\Str::limit($article->content, 100) }}</small>
                                    </td>
                                    <td>{{ $article->category ?? '-' }}</td>
                                    <td>{{ $article->author_name ?? '-' }}</td>
                                    <td>
                                        <span class="sg-publish-pill {{ $article->is_published ? 'is-published' : 'is-draft' }}">
                                            {{ $article->is_published ? 'Dipublikasikan' : 'Draf' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="sg-table-actions justify-content-end">
                                            <form method="POST"
                                                  action="{{ route('admin.articles.destroy', $article->id) }}"
                                                  onsubmit="return confirm('Hapus artikel {{ addslashes($article->title) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sg-icon-danger" title="Hapus artikel" aria-label="Hapus {{ $article->title }}">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="sg-empty-state">
                                            <i class="bi bi-file-earmark-text"></i>
                                            <strong>Belum ada artikel</strong>
                                            <span>Artikel yang disimpan akan tampil di sini.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </section>

    <section class="sg-section" id="kamus-sentimen">
        <div class="sg-section-head">
            <div>
                <h2>Kamus Sentimen</h2>
                <p>Kata-kata ini akan digunakan oleh proses sentiment analysis otomatis pada tahap backend berikutnya.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <article class="sg-card sg-word-card is-positive">
                    <div class="sg-word-card-head">
                        <span class="sg-word-icon"><i class="bi bi-emoji-smile"></i></span>
                        <div>
                            <h3>Kata positif</h3>
                            <p>{{ $positiveWords->count() }} kata tersimpan</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.positiveWords.store') }}" class="sg-word-form">
                        @csrf
                        <input type="text" name="word" class="form-control" placeholder="Contoh: stabil" required>
                        <button type="submit" class="sg-btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button>
                    </form>
                    <div class="sg-word-list">
                        @forelse ($positiveWords as $word)
                            <form method="POST" action="{{ route('admin.positiveWords.destroy', $word->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sg-word-chip is-positive" title="Hapus kata {{ $word->word }}">
                                    {{ $word->word }} <i class="bi bi-x"></i>
                                </button>
                            </form>
                        @empty
                            <span class="sg-word-empty">Belum ada kata positif.</span>
                        @endforelse
                    </div>
                </article>
            </div>

            <div class="col-12 col-xl-6">
                <article class="sg-card sg-word-card is-negative">
                    <div class="sg-word-card-head">
                        <span class="sg-word-icon"><i class="bi bi-emoji-frown"></i></span>
                        <div>
                            <h3>Kata negatif</h3>
                            <p>{{ $negativeWords->count() }} kata tersimpan</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.negativeWords.store') }}" class="sg-word-form">
                        @csrf
                        <input type="text" name="word" class="form-control" placeholder="Contoh: krisis" required>
                        <button type="submit" class="sg-btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button>
                    </form>
                    <div class="sg-word-list">
                        @forelse ($negativeWords as $word)
                            <form method="POST" action="{{ route('admin.negativeWords.destroy', $word->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sg-word-chip is-negative" title="Hapus kata {{ $word->word }}">
                                    {{ $word->word }} <i class="bi bi-x"></i>
                                </button>
                            </form>
                        @empty
                            <span class="sg-word-empty">Belum ada kata negatif.</span>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>
    </section>
@endsection
