@extends('layouts.supplyguard')

@section('title', 'Kelola Pelabuhan - SupplyGuard')
@section('page-title', 'Kelola Pelabuhan')
@section('page-subtitle', 'Tambah, cari, ubah, dan hapus dataset pelabuhan global')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/admin-ports.css') }}?v={{ filemtime(public_path('css/admin-ports.css')) }}"
    >
@endpush

@section('content')
    <section class="sg-ap-page">
        <div class="sg-ap-hero">
            <div>
                <span class="sg-ap-eyebrow">
                    <i class="bi bi-geo-alt-fill"></i>
                    Administrasi Dataset
                </span>
                <h2>Pelabuhan Global</h2>
                <p>
                    Kelola ribuan lokasi pelabuhan secara efisien melalui
                    pencarian, filter, pagination, dan formulir penyuntingan.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-primary sg-ap-add-button"
                data-bs-toggle="modal"
                data-bs-target="#addPortModal"
            >
                <i class="bi bi-plus-lg"></i>
                Tambah Pelabuhan
            </button>
        </div>

        <div class="sg-ap-stat-grid">
            <article class="sg-ap-stat">
                <span><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <small>Total Pelabuhan</small>
                    <strong>{{ number_format($statistics['total'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-ap-stat">
                <span><i class="bi bi-flag-fill"></i></span>
                <div>
                    <small>Negara Terjangkau</small>
                    <strong>{{ number_format($statistics['countries'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-ap-stat">
                <span><i class="bi bi-crosshair"></i></span>
                <div>
                    <small>Memiliki Koordinat</small>
                    <strong>{{ number_format($statistics['with_coordinates'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-ap-stat">
                <span><i class="bi bi-question-circle-fill"></i></span>
                <div>
                    <small>Tanpa Relasi Negara</small>
                    <strong>{{ number_format($statistics['without_country'], 0, ',', '.') }}</strong>
                </div>
            </article>

            <article class="sg-ap-stat">
                <span><i class="bi bi-funnel-fill"></i></span>
                <div>
                    <small>Hasil Filter</small>
                    <strong>{{ number_format($statistics['filtered'], 0, ',', '.') }}</strong>
                </div>
            </article>
        </div>

        <article class="sg-ap-panel">
            <div class="sg-ap-panel-head">
                <div>
                    <span>Filter Dataset</span>
                    <h3>Cari Data Pelabuhan</h3>
                </div>

                @if ($keyword !== '' || $countryId > 0 || $type !== '')
                    <a href="{{ route('admin.ports.index') }}">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Bersihkan filter
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.ports.index') }}" class="sg-ap-filter">
                <div class="sg-ap-field sg-ap-search-field">
                    <label for="q">Nama, kode, negara, atau deskripsi</label>
                    <div class="sg-ap-input-icon">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="{{ $keyword }}"
                            placeholder="Contoh: Tanjung Priok, IDTPP"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="sg-ap-field">
                    <label for="country_id">Negara</label>
                    <select name="country_id" id="country_id">
                        <option value="">Semua negara</option>
                        @foreach ($countries as $country)
                            <option
                                value="{{ $country->id }}"
                                @selected((int) $countryId === (int) $country->id)
                            >
                                {{ $country->name }}
                                @if ($country->cca2)
                                    ({{ $country->cca2 }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-ap-field">
                    <label for="type">Tipe pelabuhan</label>
                    <select name="type" id="type">
                        <option value="">Semua tipe</option>
                        @foreach ($types as $portType)
                            <option value="{{ $portType }}" @selected($type === $portType)>
                                {{ $portType }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sg-ap-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel-fill"></i>
                        Terapkan
                    </button>
                    <a href="{{ route('admin.ports.index') }}" class="btn btn-light">
                        Reset
                    </a>
                </div>
            </form>
        </article>

        <article class="sg-ap-panel">
            <div class="sg-ap-panel-head">
                <div>
                    <span>Dataset Pelabuhan</span>
                    <h3>Daftar Pelabuhan</h3>
                </div>

                <span class="sg-ap-result-badge">
                    Halaman {{ $ports->currentPage() }} dari {{ max(1, $ports->lastPage()) }}
                </span>
            </div>

            @if ($ports->count() > 0)
                <div class="table-responsive">
                    <table class="table sg-ap-table align-middle">
                        <thead>
                            <tr>
                                <th>Pelabuhan</th>
                                <th>Negara</th>
                                <th>Kode</th>
                                <th>Tipe</th>
                                <th>Koordinat</th>
                                <th>Diperbarui</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ports as $port)
                                <tr>
                                    <td>
                                        <div class="sg-ap-name-cell">
                                            <span><i class="bi bi-geo-alt"></i></span>
                                            <div>
                                                <strong>{{ $port->name }}</strong>
                                                <small>
                                                    {{ \Illuminate\Support\Str::limit(
                                                        $port->description ?: 'Tidak ada deskripsi.',
                                                        70
                                                    ) }}
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $port->linked_country ?? $port->country_name ?? '-' }}</strong>
                                        @if ($port->cca3)
                                            <small class="d-block text-muted">{{ $port->cca3 }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <code class="sg-ap-code">{{ $port->port_code ?: '-' }}</code>
                                    </td>
                                    <td>{{ $port->type ?: '-' }}</td>
                                    <td>
                                        @if ($port->latitude !== null && $port->longitude !== null)
                                            <code class="sg-ap-coordinate">
                                                {{ number_format((float) $port->latitude, 4) }},
                                                {{ number_format((float) $port->longitude, 4) }}
                                            </code>
                                        @else
                                            <span class="text-muted">Tidak tersedia</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="sg-ap-date">
                                            {{ $port->updated_at
                                                ? \Carbon\Carbon::parse($port->updated_at)->format('d M Y')
                                                : '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="sg-ap-actions">
                                            <button
                                                type="button"
                                                class="sg-ap-edit-button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editPortModal"
                                                data-id="{{ $port->id }}"
                                                data-name="{{ $port->name }}"
                                                data-country-id="{{ $port->country_id }}"
                                                data-code="{{ $port->port_code }}"
                                                data-type="{{ $port->type }}"
                                                data-latitude="{{ $port->latitude }}"
                                                data-longitude="{{ $port->longitude }}"
                                                data-description="{{ $port->description }}"
                                                title="Ubah pelabuhan"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.ports.destroy', $port->id) }}"
                                                onsubmit="return confirm('Hapus pelabuhan {{ addslashes($port->name) }}?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="sg-ap-delete-button"
                                                    title="Hapus pelabuhan"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($ports->hasPages())
                    <nav class="sg-ap-pagination" aria-label="Navigasi data pelabuhan">
                        <ul class="pagination mb-0">
                            <li class="page-item {{ $ports->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $ports->previousPageUrl() ?: '#' }}">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            @foreach (
                                $ports->getUrlRange(
                                    max(1, $ports->currentPage() - 2),
                                    min($ports->lastPage(), $ports->currentPage() + 2)
                                ) as $page => $url
                            )
                                <li class="page-item {{ $page === $ports->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach

                            <li class="page-item {{ $ports->hasMorePages() ? '' : 'disabled' }}">
                                <a class="page-link" href="{{ $ports->nextPageUrl() ?: '#' }}">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                @endif
            @else
                <div class="sg-ap-empty">
                    <i class="bi bi-search"></i>
                    <h3>Pelabuhan tidak ditemukan</h3>
                    <p>Tidak ada data yang sesuai dengan filter yang dipilih.</p>
                    <a href="{{ route('admin.ports.index') }}" class="btn btn-primary">
                        Tampilkan Semua Pelabuhan
                    </a>
                </div>
            @endif
        </article>
    </section>

    <div class="modal fade" id="addPortModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content sg-ap-modal">
                <div class="modal-header">
                    <div>
                        <span>Dataset Pelabuhan</span>
                        <h5 class="modal-title">Tambah Pelabuhan</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form method="POST" action="{{ route('admin.ports.store') }}">
                    @csrf
                    @include('supplyguard.partials.admin-port-form', [
                        'prefix' => 'add',
                        'submitLabel' => 'Simpan Pelabuhan',
                    ])
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editPortModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content sg-ap-modal">
                <div class="modal-header">
                    <div>
                        <span>Dataset Pelabuhan</span>
                        <h5 class="modal-title">Ubah Pelabuhan</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form
                    method="POST"
                    action=""
                    id="editPortForm"
                    data-action-template="{{ route('admin.ports.update', ['id' => '__ID__']) }}"
                >
                    @csrf
                    @method('PATCH')
                    @include('supplyguard.partials.admin-port-form', [
                        'prefix' => 'edit',
                        'submitLabel' => 'Simpan Perubahan',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('editPortModal');
            const form = document.getElementById('editPortForm');

            if (!modal || !form) {
                return;
            }

            modal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                if (!button) {
                    return;
                }

                form.action = form.dataset.actionTemplate.replace(
                    '__ID__',
                    button.dataset.id
                );

                document.getElementById('edit-name').value = button.dataset.name || '';
                document.getElementById('edit-country-id').value = button.dataset.countryId || '';
                document.getElementById('edit-port-code').value = button.dataset.code || '';
                document.getElementById('edit-type').value = button.dataset.type || '';
                document.getElementById('edit-latitude').value = button.dataset.latitude || '';
                document.getElementById('edit-longitude').value = button.dataset.longitude || '';
                document.getElementById('edit-description').value = button.dataset.description || '';
            });
        });
    </script>
@endpush
