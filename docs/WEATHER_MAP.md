# Peta Cuaca Global

Update ini menambahkan peta Leaflet pada halaman Monitoring Cuaca.

## Fitur

- marker untuk negara yang memiliki koordinat;
- warna hijau untuk risiko rendah;
- warna kuning untuk risiko sedang;
- warna merah untuk risiko tinggi;
- popup suhu, hujan, angin, status, dan skor risiko;
- tautan menuju analisis negara;
- peta mengikuti pencarian dan filter aktif;
- tampilan responsif.

## Pemeriksaan

```powershell
php -l app\Http\Controllers\WeatherController.php
php artisan optimize:clear
php artisan test
```

Buka:

```text
http://127.0.0.1:8000/weather-monitoring
```
