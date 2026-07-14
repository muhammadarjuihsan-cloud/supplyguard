<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncCurrency extends Command
{
    /**
     * Nama perintah Artisan.
     */
    protected $signature = 'supplyguard:sync-currency
                            {--country= : ID, CCA2, CCA3, nama negara, atau kode mata uang}
                            {--base=USD : Mata uang dasar}
                            {--insecure : Nonaktifkan verifikasi SSL untuk XAMPP lokal}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Sinkronisasi kurs mata uang negara menggunakan ExchangeRate-API';

    /**
     * Endpoint open access ExchangeRate-API.
     */
    private const BASE_URL = 'https://open.er-api.com/v6/latest';

    /**
     * Menjalankan sinkronisasi.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info(
            'Sinkronisasi kurs SupplyGuard dimulai.'
        );

        if (!Schema::hasTable('countries')) {
            $this->components->error(
                'Tabel countries belum tersedia.'
            );

            return self::FAILURE;
        }

        if (!Schema::hasTable('currency_rates')) {
            $this->components->error(
                'Tabel currency_rates belum tersedia.'
            );

            return self::FAILURE;
        }

        if (!Schema::hasTable('currency_histories')) {
            $this->components->error(
                'Tabel currency_histories belum tersedia.'
            );

            return self::FAILURE;
        }

        $baseCurrency = strtoupper(
            trim((string) $this->option('base'))
        );

        if (
            $baseCurrency === '' ||
            !preg_match('/^[A-Z]{3}$/', $baseCurrency)
        ) {
            $this->components->error(
                'Kode mata uang dasar harus terdiri dari 3 huruf, contoh USD.'
            );

            return self::FAILURE;
        }

        $countries = $this->countriesToProcess();

        if ($countries->isEmpty()) {
            $this->components->warn(
                'Tidak ada negara dengan kode mata uang yang dapat diproses.'
            );

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Negara yang akan diproses: %d | Mata uang dasar: %s',
            $countries->count(),
            $baseCurrency
        ));

        if ((bool) $this->option('insecure')) {
            $this->components->warn(
                'Verifikasi SSL dinonaktifkan untuk proses ini.'
            );
        }

        $endpoint = self::BASE_URL . '/' . $baseCurrency;

        try {
            $response = $this->httpClient()->get($endpoint);
        } catch (Throwable $exception) {
            $this->recordApiLog(
                status: 'failed',
                responseCode: null,
                message: $exception->getMessage(),
                endpoint: $endpoint
            );

            $this->components->error(
                'Tidak dapat menghubungi ExchangeRate-API.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if (!$response->successful()) {
            $message = sprintf(
                'ExchangeRate-API memberikan HTTP status %d.',
                $response->status()
            );

            $this->recordApiLog(
                status: 'failed',
                responseCode: $response->status(),
                message: $message,
                endpoint: $endpoint
            );

            $this->components->error($message);

            return self::FAILURE;
        }

        $payload = $response->json();

        if (
            !is_array($payload) ||
            ($payload['result'] ?? null) !== 'success' ||
            !isset($payload['rates']) ||
            !is_array($payload['rates'])
        ) {
            $message = sprintf(
                'Respons ExchangeRate-API tidak valid: %s',
                (string) ($payload['error-type'] ?? 'unknown-error')
            );

            $this->recordApiLog(
                status: 'failed',
                responseCode: $response->status(),
                message: $message,
                endpoint: $endpoint
            );

            $this->components->error($message);

            return self::FAILURE;
        }

        $rates = collect($payload['rates'])
            ->mapWithKeys(
                static fn (mixed $rate, string $code): array => [
                    strtoupper($code) => is_numeric($rate)
                        ? (float) $rate
                        : null,
                ]
            )
            ->filter(
                static fn (?float $rate): bool =>
                    $rate !== null && $rate > 0
            );

        if ($rates->isEmpty()) {
            $this->components->error(
                'Daftar kurs dari API kosong.'
            );

            return self::FAILURE;
        }

        $providerUpdatedAt = $this->providerUpdatedAt(
            $payload['time_last_update_unix'] ?? null
        );

        $rateDate = $providerUpdatedAt->toDateString();
        $fetchedAt = now();

        $progressBar = $this->output->createProgressBar(
            $countries->count()
        );

        $progressBar->start();

        $successCount = 0;
        $unsupportedCount = 0;
        $failedCount = 0;
        $processedCurrencies = [];

        try {
            DB::transaction(function () use (
                $countries,
                $rates,
                $baseCurrency,
                $rateDate,
                $fetchedAt,
                &$successCount,
                &$unsupportedCount,
                &$failedCount,
                &$processedCurrencies,
                $progressBar
            ): void {
                foreach ($countries as $country) {
                    $targetCurrency = strtoupper(
                        trim((string) $country->currency_code)
                    );

                    if (
                        $targetCurrency === '' ||
                        !$rates->has($targetCurrency)
                    ) {
                        $unsupportedCount++;
                        $progressBar->advance();

                        continue;
                    }

                    $rate = round(
                        (float) $rates->get($targetCurrency),
                        6
                    );

                    if ($rate <= 0) {
                        $failedCount++;
                        $progressBar->advance();

                        continue;
                    }

                    /*
                     * Riwayat disimpan satu kali per mata uang per tanggal,
                     * walaupun satu mata uang dipakai oleh banyak negara.
                     */
                    if (!isset($processedCurrencies[$targetCurrency])) {
                        $previousRate = DB::table(
                            'currency_histories'
                        )
                            ->where(
                                'base_currency',
                                $baseCurrency
                            )
                            ->where(
                                'target_currency',
                                $targetCurrency
                            )
                            ->where(
                                'rate_date',
                                '<',
                                $rateDate
                            )
                            ->orderByDesc('rate_date')
                            ->value('rate');

                        $changePercent = $this->changePercent(
                            currentRate: $rate,
                            previousRate: $previousRate
                        );

                        DB::table(
                            'currency_histories'
                        )->updateOrInsert(
                            [
                                'base_currency' =>
                                    $baseCurrency,
                                'target_currency' =>
                                    $targetCurrency,
                                'rate_date' =>
                                    $rateDate,
                            ],
                            [
                                'rate' => $rate,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );

                        $processedCurrencies[$targetCurrency] = [
                            'change_percent' => $changePercent,
                            'currency_risk' =>
                                $this->currencyRisk(
                                    $changePercent
                                ),
                        ];
                    }

                    $metrics =
                        $processedCurrencies[$targetCurrency];

                    $existingRateId = DB::table(
                        'currency_rates'
                    )
                        ->where(
                            'country_id',
                            $country->id
                        )
                        ->where(
                            'base_currency',
                            $baseCurrency
                        )
                        ->where(
                            'target_currency',
                            $targetCurrency
                        )
                        ->value('id');

                    $rateData = [
                        'rate' => $rate,
                        'change_percent' =>
                            $metrics['change_percent'],
                        'currency_risk' =>
                            $metrics['currency_risk'],
                        'fetched_at' => $fetchedAt,
                        'updated_at' => now(),
                    ];

                    if ($existingRateId) {
                        DB::table('currency_rates')
                            ->where('id', $existingRateId)
                            ->update($rateData);
                    } else {
                        DB::table('currency_rates')->insert(
                            array_merge(
                                [
                                    'country_id' =>
                                        $country->id,
                                    'base_currency' =>
                                        $baseCurrency,
                                    'target_currency' =>
                                        $targetCurrency,
                                    'created_at' => now(),
                                ],
                                $rateData
                            )
                        );
                    }

                    $successCount++;
                    $progressBar->advance();
                }
            });
        } catch (Throwable $exception) {
            $progressBar->finish();
            $this->newLine(2);

            $this->recordApiLog(
                status: 'failed',
                responseCode: $response->status(),
                message: $exception->getMessage(),
                endpoint: $endpoint
            );

            $this->components->error(
                'Data kurs gagal disimpan ke database.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $progressBar->finish();
        $this->newLine(2);

        $status = match (true) {
            $successCount === 0 => 'failed',
            $unsupportedCount > 0 || $failedCount > 0 =>
                'partial',
            default => 'success',
        };

        $message = sprintf(
            '%d negara berhasil, %d mata uang tidak didukung, %d gagal. %d mata uang unik diproses.',
            $successCount,
            $unsupportedCount,
            $failedCount,
            count($processedCurrencies)
        );

        $this->recordApiLog(
            status: $status,
            responseCode: $response->status(),
            message: $message,
            endpoint: $endpoint
        );

        if ($successCount > 0) {
            $this->components->info(
                'Sinkronisasi kurs selesai.'
            );
        } else {
            $this->components->error(
                'Tidak ada data kurs yang berhasil disimpan.'
            );
        }

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Negara diproses', $countries->count()],
                ['Negara berhasil', $successCount],
                [
                    'Mata uang tidak didukung',
                    $unsupportedCount,
                ],
                ['Data gagal', $failedCount],
                [
                    'Mata uang unik tersimpan',
                    count($processedCurrencies),
                ],
                [
                    'Total currency_rates',
                    DB::table('currency_rates')->count(),
                ],
                [
                    'Total currency_histories',
                    DB::table(
                        'currency_histories'
                    )->count(),
                ],
            ]
        );

        $this->newLine();
        $this->line(sprintf(
            'Tanggal kurs provider: %s',
            $rateDate
        ));

        $this->line(
            'Buka dashboard untuk melihat nilai tukar dan risiko mata uang.'
        );

        return $successCount > 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Mengambil negara yang akan diproses.
     */
    private function countriesToProcess(): Collection
    {
        $countryOption = trim(
            (string) $this->option('country')
        );

        $query = DB::table('countries')
            ->select(
                'id',
                'name',
                'cca2',
                'cca3',
                'currency_code'
            )
            ->whereNotNull('currency_code')
            ->where('currency_code', '!=', '')
            ->orderBy('name');

        if ($countryOption !== '') {
            $query->where(function ($innerQuery) use (
                $countryOption
            ): void {
                if (ctype_digit($countryOption)) {
                    $innerQuery->orWhere(
                        'id',
                        (int) $countryOption
                    );
                }

                $upperValue = strtoupper(
                    $countryOption
                );

                $innerQuery
                    ->orWhere('cca2', $upperValue)
                    ->orWhere('cca3', $upperValue)
                    ->orWhere(
                        'currency_code',
                        $upperValue
                    )
                    ->orWhereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower($countryOption)]
                    );
            });
        }

        return $query->get();
    }

    /**
     * Menyiapkan HTTP client.
     */
    private function httpClient(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withUserAgent(
                'SupplyGuard/1.0 Laravel Currency Sync'
            )
            ->connectTimeout(15)
            ->timeout(90)
            ->retry(3, 1000, null, false);

        if ((bool) $this->option('insecure')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * Mengambil tanggal pembaruan dari provider.
     */
    private function providerUpdatedAt(
        mixed $unixTimestamp
    ): Carbon {
        if (is_numeric($unixTimestamp)) {
            return Carbon::createFromTimestampUTC(
                (int) $unixTimestamp
            );
        }

        return now()->utc();
    }

    /**
     * Menghitung perubahan persen dari kurs sebelumnya.
     */
    private function changePercent(
        float $currentRate,
        mixed $previousRate
    ): float {
        if (
            !is_numeric($previousRate) ||
            (float) $previousRate <= 0
        ) {
            return 0.0;
        }

        return round(
            (
                ($currentRate - (float) $previousRate) /
                (float) $previousRate
            ) * 100,
            2
        );
    }

    /**
     * Mengubah volatilitas kurs menjadi skor risiko 0-100.
     */
    private function currencyRisk(
        float $changePercent
    ): int {
        $absoluteChange = abs($changePercent);

        return match (true) {
            $absoluteChange >= 5 => 100,
            $absoluteChange >= 3 => 80,
            $absoluteChange >= 2 => 60,
            $absoluteChange >= 1 => 40,
            $absoluteChange >= 0.5 => 20,
            $absoluteChange > 0 => 10,
            default => 0,
        };
    }

    /**
     * Menyimpan ringkasan pemanggilan API.
     */
    private function recordApiLog(
        string $status,
        ?int $responseCode,
        string $message,
        string $endpoint
    ): void {
        if (!Schema::hasTable('api_logs')) {
            return;
        }

        try {
            DB::table('api_logs')->insert([
                'api_name' => 'ExchangeRate-API',
                'endpoint' => $endpoint,
                'status' => $status,
                'response_code' => $responseCode,
                'message' => $message,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            /*
             * Kegagalan mencatat log tidak boleh menghentikan
             * proses sinkronisasi utama.
             */
        }
    }
}
