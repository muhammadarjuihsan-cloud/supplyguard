<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| SupplyGuard Scheduler
|--------------------------------------------------------------------------
|
| Jadwal ini memakai zona waktu Indonesia Barat. Sinkronisasi GNews global
| tidak dijalankan otomatis karena kuota API terbatas dan proses global masih
| dilakukan bertahap menggunakan opsi offset dan limit.
|
*/

// Cuaca berubah cepat, sehingga diperbarui setiap pagi.
Schedule::command('supplyguard:sync-weather')
    ->dailyAt('05:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));

// Kurs diperbarui setelah data cuaca.
Schedule::command('supplyguard:sync-currency')
    ->dailyAt('06:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));

// Analisis ulang seluruh berita yang sudah tersimpan.
Schedule::command('supplyguard:analyze-sentiment --refresh-lexicon')
    ->dailyAt('06:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));

// Skor risiko dihitung setelah seluruh pembaruan harian selesai.
Schedule::command('supplyguard:calculate-risk')
    ->dailyAt('06:45')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));

// Data ekonomi tidak berubah setiap hari, cukup diperbarui mingguan.
Schedule::command('supplyguard:sync-economy')
    ->weeklyOn(1, '07:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));

// Negara dan pelabuhan bersifat relatif stabil, cukup diperbarui bulanan.
Schedule::command('supplyguard:sync-countries')
    ->monthlyOn(1, '08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));

Schedule::command('supplyguard:sync-ports')
    ->monthlyOn(1, '08:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(360)
    ->appendOutputTo(storage_path('logs/supplyguard-scheduler.log'));
