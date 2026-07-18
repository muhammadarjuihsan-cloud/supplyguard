@extends('layouts.supplyguard')

@section('title', 'Admin Dashboard - SupplyGuard')
@section('page-title', 'Admin Dashboard')
@section('page-subtitle', 'Kelola pengguna, data pelabuhan, artikel, dan kamus sentimen')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-full-crud.css') }}?v={{ filemtime(public_path('css/admin-full-crud.css')) }}">
@endpush

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
                                        <button
                                            type="button"
                                            class="sg-icon-edit js-edit-user"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUserModal"
                                            data-id="{{ $user->id }}"
                                            data-name="{{ $user->name }}"
                                            data-email="{{ $user->email }}"
                                            data-role="{{ $user->role }}"
                                            data-self="{{ (int) $user->id === (int) auth()->id() ? '1' : '0' }}"
                                            title="Ubah pengguna"
                                            aria-label="Ubah {{ $user->name }}"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

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
                <p>Kelola seluruh dataset pelabuhan melalui halaman khusus yang memakai pencarian dan pagination.</p>
            </div>
            <span class="sg-section-count">
                {{ number_format($stats['ports'], 0, ',', '.') }} lokasi
            </span>
        </div>

        <article class="sg-card sg-admin-card">
            <div class="sg-admin-card-head">
                <div>
                    <h3>Pengelolaan pelabuhan dipisahkan</h3>
                    <p>
                        Halaman Admin Dashboard tidak lagi memuat ribuan baris pelabuhan sekaligus,
                        sehingga proses membuka halaman menjadi lebih cepat dan ringan.
                    </p>
                </div>
                <span class="sg-admin-card-badge">
                    <i class="bi bi-speedometer2"></i>
                    Dioptimalkan
                </span>
            </div>

            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 p-3">
                <div class="text-secondary">
                    Cari, filter, tambah, ubah, dan hapus
                    {{ number_format($stats['ports'], 0, ',', '.') }}
                    data pelabuhan dari halaman khusus.
                </div>

                <a href="{{ route('admin.ports.index') }}" class="sg-btn-primary text-decoration-none">
                    <i class="bi bi-geo-alt-fill"></i>
                    Kelola Semua Pelabuhan
                </a>
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
                                            <button
                                                type="button"
                                                class="sg-icon-edit js-edit-article"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editArticleModal"
                                                data-id="{{ $article->id }}"
                                                data-title="{{ $article->title }}"
                                                data-category="{{ $article->category }}"
                                                data-content="{{ $article->content }}"
                                                data-published="{{ $article->is_published ? '1' : '0' }}"
                                                title="Ubah artikel"
                                                aria-label="Ubah {{ $article->title }}"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

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
                            <div class="sg-word-chip-group">
                                <button
                                    type="button"
                                    class="sg-word-chip is-positive js-edit-word"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editWordModal"
                                    data-kind="positive"
                                    data-id="{{ $word->id }}"
                                    data-word="{{ $word->word }}"
                                    title="Ubah kata {{ $word->word }}"
                                >
                                    {{ $word->word }}
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <form method="POST" action="{{ route('admin.positiveWords.destroy', $word->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="sg-word-chip-delete is-positive"
                                        title="Hapus kata {{ $word->word }}"
                                        aria-label="Hapus kata {{ $word->word }}"
                                    >
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
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
                            <div class="sg-word-chip-group">
                                <button
                                    type="button"
                                    class="sg-word-chip is-negative js-edit-word"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editWordModal"
                                    data-kind="negative"
                                    data-id="{{ $word->id }}"
                                    data-word="{{ $word->word }}"
                                    title="Ubah kata {{ $word->word }}"
                                >
                                    {{ $word->word }}
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <form method="POST" action="{{ route('admin.negativeWords.destroy', $word->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="sg-word-chip-delete is-negative"
                                        title="Hapus kata {{ $word->word }}"
                                        aria-label="Hapus kata {{ $word->word }}"
                                    >
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <span class="sg-word-empty">Belum ada kata negatif.</span>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>
    </section>


    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content sg-crud-modal">
                <div class="modal-header">
                    <div>
                        <span class="sg-modal-eyebrow">Manajemen Pengguna</span>
                        <h5 class="modal-title">Ubah Data Pengguna</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form
                    method="POST"
                    id="editUserForm"
                    action=""
                    data-action-template="{{ route('admin.users.update', ['id' => '__ID__']) }}"
                >
                    @csrf
                    @method('PATCH')

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="edit-user-name" class="form-label">Nama</label>
                                <input type="text" id="edit-user-name" name="name" class="form-control" maxlength="255" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="edit-user-email" class="form-label">Email</label>
                                <input type="email" id="edit-user-email" name="email" class="form-control" maxlength="255" required>
                            </div>

                            <div class="col-12">
                                <label for="edit-user-role" class="form-label">Peran</label>
                                <select id="edit-user-role" name="role" class="form-select">
                                    <option value="user">Pengguna</option>
                                    <option value="admin">Administrator</option>
                                </select>
                                <small id="edit-user-role-note" class="sg-form-note d-none">
                                    Peran akun yang sedang digunakan tidak dapat diubah.
                                </small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="edit-user-password" class="form-label">Kata sandi baru</label>
                                <input
                                    type="password"
                                    id="edit-user-password"
                                    name="password"
                                    class="form-control"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Kosongkan jika tidak diubah"
                                >
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="edit-user-password-confirmation" class="form-label">Konfirmasi kata sandi</label>
                                <input
                                    type="password"
                                    id="edit-user-password-confirmation"
                                    name="password_confirmation"
                                    class="form-control"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Ulangi kata sandi baru"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editArticleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content sg-crud-modal">
                <div class="modal-header">
                    <div>
                        <span class="sg-modal-eyebrow">Artikel Internal</span>
                        <h5 class="modal-title">Ubah Artikel</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form
                    method="POST"
                    id="editArticleForm"
                    action=""
                    data-action-template="{{ route('admin.articles.update', ['id' => '__ID__']) }}"
                >
                    @csrf
                    @method('PATCH')

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 col-lg-8">
                                <label for="edit-article-title" class="form-label">Judul artikel</label>
                                <input type="text" id="edit-article-title" name="title" class="form-control" maxlength="255" required>
                            </div>

                            <div class="col-12 col-lg-4">
                                <label for="edit-article-category" class="form-label">Kategori</label>
                                <input type="text" id="edit-article-category" name="category" class="form-control" maxlength="100">
                            </div>

                            <div class="col-12">
                                <label for="edit-article-content" class="form-label">Isi artikel</label>
                                <textarea id="edit-article-content" name="content" class="form-control" rows="9" required></textarea>
                            </div>

                            <div class="col-12">
                                <label class="sg-switch-row" for="edit-article-published">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="is_published"
                                        id="edit-article-published"
                                        value="1"
                                    >
                                    <span>Publikasikan artikel</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editWordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content sg-crud-modal">
                <div class="modal-header">
                    <div>
                        <span class="sg-modal-eyebrow">Kamus Sentimen</span>
                        <h5 class="modal-title" id="editWordTitle">Ubah Kata</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form
                    method="POST"
                    id="editWordForm"
                    action=""
                    data-positive-template="{{ route('admin.positiveWords.update', ['id' => '__ID__']) }}"
                    data-negative-template="{{ route('admin.negativeWords.update', ['id' => '__ID__']) }}"
                >
                    @csrf
                    @method('PATCH')

                    <div class="modal-body">
                        <label for="edit-word-input" class="form-label">Kata</label>
                        <input
                            type="text"
                            id="edit-word-input"
                            name="word"
                            class="form-control"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const replaceId = (template, id) => template.replace('__ID__', id);

            const userModal = document.getElementById('editUserModal');
            const userForm = document.getElementById('editUserForm');

            if (userModal && userForm) {
                userModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const isSelf = button.dataset.self === '1';
                    const role = document.getElementById('edit-user-role');
                    const roleNote = document.getElementById('edit-user-role-note');

                    userForm.action = replaceId(
                        userForm.dataset.actionTemplate,
                        button.dataset.id
                    );

                    document.getElementById('edit-user-name').value =
                        button.dataset.name || '';

                    document.getElementById('edit-user-email').value =
                        button.dataset.email || '';

                    role.value = button.dataset.role || 'user';
                    role.disabled = isSelf;
                    roleNote.classList.toggle('d-none', !isSelf);

                    document.getElementById('edit-user-password').value = '';
                    document.getElementById('edit-user-password-confirmation').value = '';
                });
            }

            const articleModal = document.getElementById('editArticleModal');
            const articleForm = document.getElementById('editArticleForm');

            if (articleModal && articleForm) {
                articleModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    articleForm.action = replaceId(
                        articleForm.dataset.actionTemplate,
                        button.dataset.id
                    );

                    document.getElementById('edit-article-title').value =
                        button.dataset.title || '';

                    document.getElementById('edit-article-category').value =
                        button.dataset.category || '';

                    document.getElementById('edit-article-content').value =
                        button.dataset.content || '';

                    document.getElementById('edit-article-published').checked =
                        button.dataset.published === '1';
                });
            }

            const wordModal = document.getElementById('editWordModal');
            const wordForm = document.getElementById('editWordForm');

            if (wordModal && wordForm) {
                wordModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const kind = button.dataset.kind;
                    const template = kind === 'positive'
                        ? wordForm.dataset.positiveTemplate
                        : wordForm.dataset.negativeTemplate;

                    wordForm.action = replaceId(template, button.dataset.id);

                    document.getElementById('edit-word-input').value =
                        button.dataset.word || '';

                    document.getElementById('editWordTitle').textContent =
                        kind === 'positive'
                            ? 'Ubah Kata Positif'
                            : 'Ubah Kata Negatif';
                });
            }
        });
    </script>
@endpush
