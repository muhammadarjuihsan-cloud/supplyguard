# SupplyGuard

**Global Supply Chain Risk Intelligence Platform**

SupplyGuard adalah aplikasi berbasis Laravel yang digunakan untuk memantau dan
menganalisis risiko rantai pasok global berdasarkan data negara, ekonomi,
cuaca, nilai tukar, berita, sentimen, dan pelabuhan.

Aplikasi mengambil data dari beberapa API eksternal, menyimpannya ke MySQL,
mengolahnya dengan analisis sentimen dan Weighted Risk Model, lalu
menampilkannya melalui dashboard, grafik, peta, perbandingan negara, watchlist,
halaman admin, dan REST API internal.

---

## Identitas Project

- Mata kuliah: Pemrograman Web
- Jenis tugas: Project UAS
- Nama mahasiswa: **muhammad arju ihsan**
- NIM: **240180113**
- Kelas: **A3**
- Dosen pengampu: **muhammad ikhwani S.Pd.i., m.sc**

---

## Tujuan Sistem

SupplyGuard membantu pengguna memahami kondisi risiko rantai pasok suatu negara
dengan menggabungkan beberapa indikator:

- kondisi cuaca;
- inflasi;
- perubahan nilai tukar;
- sentimen berita;
- ketersediaan dan lokasi pelabuhan.

Hasil akhirnya berupa skor risiko `0–100`, kategori risiko, rekomendasi, grafik,
dan data pendukung lainnya.

---

## Teknologi

- PHP 8.2+
- Laravel 12
- MySQL
- Bootstrap 5
- JavaScript
- Chart.js
- Leaflet
- OpenStreetMap
- Composer
- Git
- GitHub

---

## Sumber API Eksternal

| API | Fungsi |
|---|---|
| REST Countries API v5 | Data negara, kode negara, wilayah, mata uang, bahasa, dan koordinat |
| Open-Meteo | Data suhu, curah hujan, dan kecepatan angin |
| World Bank API | GDP, inflasi, populasi, dan indikator ekonomi |
| Exchange Rate API | Nilai tukar mata uang |
| GNews API | Berita global berdasarkan negara |
| World Port Index | Data dan koordinat pelabuhan |
| OpenStreetMap | Peta digital pada Leaflet |

API key disimpan pada file `.env` dan tidak boleh dimasukkan ke GitHub.

---

## Fitur Utama

### Pengguna

1. Autentikasi pengguna
2. Dashboard negara
3. Perbandingan negara
4. Favorite Monitoring List
5. Visualisasi data
6. Lokasi pelabuhan
7. News Intelligence
8. Monitoring cuaca
9. Analisis nilai tukar
10. Risk Scoring
11. REST API Documentation
12. Peta Leaflet
13. Grafik Chart.js
14. Riwayat risiko

### Administrator

1. Dashboard administrator
2. Manajemen pengguna
3. Manajemen dataset pelabuhan
4. Manajemen artikel
5. Manajemen kamus sentimen
6. Log request API
7. Menjalankan sinkronisasi data
8. Menjalankan ulang analisis sentimen
9. Menjalankan ulang perhitungan risiko

---

## Data Saat Ini

Hasil sinkronisasi terakhir:

- 250 negara
- 3.641 pelabuhan
- 1.107 berita
- 250 skor risiko
- 128 negara berisiko rendah
- 121 negara berisiko sedang
- 1 negara berisiko tinggi
- 115 berita positif
- 846 berita netral
- 146 berita negatif

Jumlah data dapat berubah setelah sinkronisasi berikutnya.

---

## Weighted Risk Model

SupplyGuard menggunakan lima komponen risiko:

| Komponen | Bobot |
|---|---:|
| Cuaca | 25% |
| Inflasi | 25% |
| Nilai tukar | 20% |
| Sentimen berita | 20% |
| Pelabuhan | 10% |
| **Total** | **100%** |

Rumus umum:

```text
Total Risk =
(Weather × 25%) +
(Inflation × 25%) +
(Currency × 20%) +
(News × 20%) +
(Port × 10%)
```

Kategori risiko:

| Skor | Level |
|---:|---|
| 0–30 | Low |
| 31–60 | Medium |
| 61–100 | High |

Apabila suatu komponen belum memiliki data, sistem menggunakan nilai netral agar
perhitungan tetap dapat dilakukan dan kelengkapan data tetap ditampilkan.

---

## Analisis Sentimen

Analisis sentimen memakai pendekatan lexicon-based dengan kamus kata positif dan
negatif yang dikelola melalui halaman administrator.

Alur analisis:

```text
Judul dan deskripsi berita
        ↓
Normalisasi teks
        ↓
Pencocokan kamus positif dan negatif
        ↓
Perhitungan skor kata
        ↓
Positive / Neutral / Negative
```

Command:

```powershell
php artisan supplyguard:analyze-sentiment --refresh-lexicon
```

---

## Instalasi

### 1. Persyaratan

Pastikan perangkat sudah memiliki:

- PHP 8.2 atau lebih baru
- Composer
- MySQL atau XAMPP
- Git
- koneksi internet untuk API eksternal

### 2. Clone repository

```powershell
git clone https://github.com/muhammadarjuihsan-cloud/supplyguard.git
cd supplyguard
```

### 3. Instal dependency

```powershell
composer install
```

### 4. Buat file environment

```powershell
copy .env.example .env
php artisan key:generate
```

### 5. Konfigurasi database

Contoh:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=supplyguard
DB_USERNAME=root
DB_PASSWORD=
```

### 6. Konfigurasi API

Tambahkan API key pada `.env`:

```env
REST_COUNTRIES_API_KEY=
REST_COUNTRIES_BASE_URL=https://api.restcountries.com/countries/v5

GNEWS_API_KEY=
```

Tambahkan konfigurasi API lain sesuai isi `.env.example`.

### 7. Jalankan migration dan seeder

```powershell
php artisan migrate
php artisan db:seed
```

> Jangan menjalankan `php artisan migrate:fresh` pada database yang sudah
> berisi data karena perintah tersebut menghapus seluruh tabel.

### 8. Jalankan aplikasi

```powershell
php artisan optimize:clear
php artisan serve
```

Buka:

```text
http://127.0.0.1:8000
```

---

## Command Sinkronisasi

```powershell
php artisan supplyguard:sync-countries
php artisan supplyguard:sync-weather
php artisan supplyguard:sync-currency
php artisan supplyguard:sync-economy
php artisan supplyguard:sync-ports
php artisan supplyguard:sync-news --offset=0 --limit=10
php artisan supplyguard:analyze-sentiment --refresh-lexicon
php artisan supplyguard:calculate-risk
```

Apabila XAMPP lokal mengalami masalah sertifikat SSL, beberapa command mendukung
opsi:

```powershell
--insecure
```

Opsi tersebut hanya digunakan untuk pengembangan lokal.

---

## Scheduler

Jadwal otomatis tersedia pada `routes/console.php`.

Lihat jadwal:

```powershell
php artisan schedule:list
```

Jalankan scheduler saat pengembangan:

```powershell
php artisan schedule:work
```

Log scheduler:

```text
storage/logs/supplyguard-scheduler.log
```

GNews global tidak dijadwalkan otomatis karena kuota API terbatas.

---

## REST API Internal

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/countries` | Daftar negara |
| GET | `/api/risk` | Skor risiko negara |
| GET | `/api/ports` | Data pelabuhan |
| GET | `/api/news` | Berita dan sentimen |
| GET | `/api/currency` | Kurs dan riwayat mata uang |

Contoh:

```text
/api/countries?q=Indonesia
/api/risk?country_id=3
/api/ports?country_id=3&per_page=10
/api/news?country_id=3&per_page=10
/api/currency?country_id=3&history_limit=30
```

Dokumentasi interaktif tersedia pada:

```text
/rest-api
```

---

## Struktur Alur Sistem

```text
API Eksternal
      ↓
Laravel Sync Commands
      ↓
Database MySQL
      ↓
Analisis Sentimen
      ↓
Weighted Risk Model
      ↓
risk_scores dan risk_histories
      ↓
Dashboard, Grafik, Peta, Admin, dan REST API
```

---

## Keamanan

- `.env` tidak boleh dikirim ke GitHub.
- API key tidak ditulis langsung di source code.
- Halaman administrator dilindungi berdasarkan role.
- Validasi dilakukan pada form dan endpoint.
- Data negara disinkronkan menggunakan `upsert`.
- Pagination digunakan untuk mencegah pemuatan ribuan data sekaligus.

---

## Struktur Folder Penting

```text
app/Console/Commands
app/Http/Controllers
app/Services
database/migrations
database/seeders
resources/views
public/css
routes/web.php
routes/console.php
tests
docs
```

---

## Catatan Pengumpulan

Sebelum project dikumpulkan:

1. hapus `.env` dari ZIP;
2. hapus `vendor`, `node_modules`, `.git`, dan file log dari ZIP source;
3. pastikan `.env.example` tersedia;
4. sertakan export database `database/supplyguard.sql`;
5. jalankan `php artisan optimize:clear`;
6. lakukan commit dan push GitHub;
7. uji login admin, login pengguna, dashboard, semua menu, CRUD, dan REST API.

---

## Lisensi

Project ini dibuat untuk kebutuhan akademik UAS Pemrograman Web.
