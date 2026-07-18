# Integrasi REST Countries API v5

SupplyGuard memakai REST Countries API v5 sebagai sumber resmi data negara.

## Konfigurasi

Tambahkan konfigurasi berikut pada file `.env` lokal:

```env
REST_COUNTRIES_API_KEY=masukkan_api_key_milik_anda
REST_COUNTRIES_BASE_URL=https://api.restcountries.com/countries/v5
```

Jangan memasukkan API key ke GitHub dan jangan membagikan file `.env`.

## Menjalankan sinkronisasi

```powershell
php artisan optimize:clear
php artisan supplyguard:sync-countries
```

Apabila XAMPP lokal mengalami masalah sertifikat SSL:

```powershell
php artisan supplyguard:sync-countries --insecure
```

Opsi `--insecure` hanya digunakan untuk pengembangan lokal.

## Keamanan data

Command memakai `upsert` berdasarkan kode `cca3`.

- Negara yang sudah ada diperbarui.
- Negara yang belum ada ditambahkan.
- Negara lama tidak dihapus.
- Foreign key pada ekonomi, cuaca, kurs, berita, pelabuhan, risiko, dan watchlist tetap aman.
- API key hanya dibaca dari `.env`.

## Pagination API

REST Countries v5 membatasi jumlah data per request pada paket gratis.
Command mengambil data bertahap sebanyak maksimal 100 negara per request sampai
seluruh halaman selesai.
