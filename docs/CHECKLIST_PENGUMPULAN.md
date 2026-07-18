# Checklist Pengumpulan SupplyGuard

## Sebelum membuat ZIP final

Pastikan folder berikut tidak ikut:

```text
.env
.git
vendor
node_modules
storage/logs/*.log
storage/framework/views/*
```

Pastikan file berikut tersedia:

```text
.env.example
README.md
database/supplyguard.sql
composer.json
composer.lock
artisan
```

## Pemeriksaan syntax

```powershell
php -l routes\web.php
php -l routes\console.php
php artisan route:list
php artisan schedule:list
php artisan optimize:clear
```

## Pemeriksaan fitur

- Login pengguna
- Login administrator
- Register
- Logout
- Dashboard
- Perbandingan negara
- Watchlist
- Visualisasi
- Lokasi pelabuhan
- News Intelligence
- Monitoring cuaca
- REST API
- Admin Dashboard
- Kelola Pelabuhan
- Log API
- Analisis sentimen
- Perhitungan risiko

## Pemeriksaan API

```text
/api/countries?q=Indonesia
/api/risk?country_id=3
/api/ports?country_id=3&per_page=10
/api/news?country_id=3&per_page=10
/api/currency?country_id=3&history_limit=30
```
