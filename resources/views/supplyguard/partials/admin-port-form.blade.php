<div class="modal-body">
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <label for="{{ $prefix }}-name" class="form-label">Nama pelabuhan</label>
            <input
                type="text"
                id="{{ $prefix }}-name"
                name="name"
                class="form-control"
                maxlength="255"
                placeholder="Contoh: Port of Tanjung Priok"
                required
            >
        </div>

        <div class="col-12 col-lg-5">
            <label for="{{ $prefix }}-country-id" class="form-label">Negara</label>
            <select
                id="{{ $prefix }}-country-id"
                name="country_id"
                class="form-select"
            >
                <option value="">Tanpa relasi negara</option>
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

        <div class="col-12 col-md-6">
            <label for="{{ $prefix }}-port-code" class="form-label">Kode pelabuhan</label>
            <input
                type="text"
                id="{{ $prefix }}-port-code"
                name="port_code"
                class="form-control"
                maxlength="50"
                placeholder="Contoh: IDTPP"
            >
        </div>

        <div class="col-12 col-md-6">
            <label for="{{ $prefix }}-type" class="form-label">Tipe</label>
            <input
                type="text"
                id="{{ $prefix }}-type"
                name="type"
                class="form-control"
                maxlength="100"
                placeholder="Contoh: Seaport"
            >
        </div>

        <div class="col-12 col-md-6">
            <label for="{{ $prefix }}-latitude" class="form-label">Lintang</label>
            <input
                type="number"
                step="any"
                id="{{ $prefix }}-latitude"
                name="latitude"
                class="form-control"
                min="-90"
                max="90"
                placeholder="-6.1045"
            >
        </div>

        <div class="col-12 col-md-6">
            <label for="{{ $prefix }}-longitude" class="form-label">Bujur</label>
            <input
                type="number"
                step="any"
                id="{{ $prefix }}-longitude"
                name="longitude"
                class="form-control"
                min="-180"
                max="180"
                placeholder="106.8808"
            >
        </div>

        <div class="col-12">
            <label for="{{ $prefix }}-description" class="form-label">Deskripsi</label>
            <textarea
                id="{{ $prefix }}-description"
                name="description"
                class="form-control"
                rows="3"
                maxlength="1000"
                placeholder="Keterangan singkat pelabuhan"
            ></textarea>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
        Batal
    </button>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i>
        {{ $submitLabel }}
    </button>
</div>
