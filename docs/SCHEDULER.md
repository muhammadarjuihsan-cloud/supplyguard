# Scheduler SupplyGuard

## Tujuan

Scheduler menjalankan pembaruan data secara otomatis tanpa perlu mengetik
setiap command secara manual.

## Jadwal aktif

| Waktu WIB | Frekuensi | Command |
|---|---|---|
| 05.30 | Setiap hari | `supplyguard:sync-weather` |
| 06.00 | Setiap hari | `supplyguard:sync-currency` |
| 06.30 | Setiap hari | `supplyguard:analyze-sentiment --refresh-lexicon` |
| 06.45 | Setiap hari | `supplyguard:calculate-risk` |
| Senin 07.15 | Setiap minggu | `supplyguard:sync-economy` |
| Tanggal 1, 08.00 | Setiap bulan | `supplyguard:sync-countries` |
| Tanggal 1, 08.30 | Setiap bulan | `supplyguard:sync-ports` |

Sinkronisasi GNews global sengaja tidak dijadwalkan karena kuota API terbatas.
Proses tersebut tetap dilanjutkan secara manual menggunakan `--offset` dan
`--limit`.

## Menjalankan saat pengembangan

Buka terminal kedua pada folder project:

```powershell
php artisan schedule:work
```

Biarkan terminal tersebut tetap terbuka selama web digunakan.

## Melihat seluruh jadwal

```powershell
php artisan schedule:list
```

## Menjalankan pemeriksaan satu kali

```powershell
php artisan schedule:run
```

Perintah ini hanya menjalankan tugas yang memang jatuh tempo pada menit
tersebut.

## Log scheduler

Output command tersimpan di:

```text
storage/logs/supplyguard-scheduler.log
```

## Windows Task Scheduler

Untuk penggunaan tanpa membuka `schedule:work`, buat task Windows yang berjalan
setiap satu menit dengan program:

```text
C:\xampp\php\php.exe
```

Argument:

```text
C:\Users\ASUS\supplyguard\artisan schedule:run
```

Start in:

```text
C:\Users\ASUS\supplyguard
```
