# Halaman Data Negara

Halaman ini menampilkan seluruh dataset negara yang digunakan SupplyGuard.

## Fitur

- total 250 negara;
- pencarian nama, kode, ibu kota, dan mata uang;
- filter wilayah;
- filter subwilayah;
- filter level risiko;
- pengurutan berdasarkan nama, risiko, pelabuhan, dan berita;
- jumlah pelabuhan setiap negara;
- jumlah berita setiap negara;
- waktu pembaruan data negara terakhir;
- skor risiko terbaru;
- tautan menuju analisis dashboard dan perbandingan negara;
- pagination 25 data per halaman.

## Route

```text
GET /data-negara
```

## Pemeriksaan

```powershell
php -l app\Http\Controllers\CountryDataController.php
php -l routes\web.php
php artisan optimize:clear
php artisan route:list --path=data-negara
php artisan test
```


## Optimasi

Jumlah pelabuhan dan berita dihitung melalui subquery agregasi satu kali agar
halaman tetap ringan walaupun jumlah data bertambah. Perbandingan juga selalu
memilih dua negara yang berbeda.
