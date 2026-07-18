# Integritas Database SupplyGuard

Migration ini menambahkan unique constraint dan index agar data tidak mudah
terduplikasi serta pencarian menjadi lebih cepat.

## Constraint yang ditambahkan

- indikator ekonomi: satu data per negara dan tahun;
- cuaca: satu data terbaru per negara;
- kurs terbaru: satu data per negara;
- riwayat kurs: satu data per pasangan mata uang dan tanggal;
- skor risiko: satu skor terbaru per negara;
- riwayat risiko: satu riwayat per negara dan tanggal;
- watchlist: satu negara hanya satu kali untuk setiap pengguna.

## Index pencarian

- wilayah negara;
- negara dan tipe pelabuhan;
- kode pelabuhan;
- negara, sentimen, dan tanggal berita;
- level dan total skor risiko;
- nama API, status, dan waktu request.

Sebelum constraint dipasang, migration membersihkan duplikasi lama dengan
mempertahankan baris yang mempunyai ID paling baru.

## Menjalankan

Pastikan database sudah dicadangkan, lalu:

```powershell
php artisan migrate
php artisan test
```

Jangan memakai `php artisan migrate:fresh`.
