<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncWeather extends Command
{
    /**
     * Nama perintah Artisan.
     */
    protected $signature = 'supplyguard:sync-weather
                            {--country= : ID, CCA2, CCA3, atau nama negara}
                            {--limit=0 : Batasi jumlah negara yang diproses}
                            {--batch=25 : Jumlah koordinat dalam satu permintaan}
                            {--insecure : Nonaktifkan verifikasi SSL untuk XAMPP lokal}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Sinkronisasi cuaca terkini seluruh negara menggunakan Open-Meteo';

    /**
     * Endpoint resmi Open-Meteo.
     */
    private const ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Menjalankan sinkronisasi.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Sinkronisasi cuaca SupplyGuard dimulai.');

        if (!Schema::hasTable('countries')) {
            $this->components->error('Tabel countries belum tersedia.');

            return self::FAILURE;
        }

        if (!Schema::hasTable('weather_cache')) {
            $this->components->error('Tabel weather_cache belum tersedia.');

            return self::FAILURE;
        }

        $countries = $this->countriesToProcess();

        if ($countries->isEmpty()) {
            $this->components->warn(
                'Tidak ada negara dengan koordinat valid yang dapat diproses.'
            );

            return self::SUCCESS;
        }

        $batchSize = max(
            1,
            min(50, (int) $this->option('batch'))
        );

        $this->line(sprintf(
            'Negara yang akan diproses: %d | Batch: %d',
            $countries->count(),
            $batchSize
        ));

        if ((bool) $this->option('insecure')) {
            $this->components->warn(
                'Verifikasi SSL dinonaktifkan untuk proses ini.'
            );
        }

        $progressBar = $this->output->createProgressBar(
            $countries->count()
        );

        $progressBar->start();

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $lastHttpStatus = null;
        $errorMessages = [];

        foreach ($countries->chunk($batchSize) as $batch) {
            try {
                $response = $this->httpClient()->get(
                    self::ENDPOINT,
                    $this->buildQuery($batch)
                );

                $lastHttpStatus = $response->status();

                if (!$response->successful()) {
                    $failedCount += $batch->count();

                    $errorMessages[] = sprintf(
                        'HTTP %d untuk batch yang memuat %d negara.',
                        $response->status(),
                        $batch->count()
                    );

                    $progressBar->advance($batch->count());

                    continue;
                }

                $payload = $response->json();
                $weatherItems = $this->normaliseResponse($payload);

                foreach ($batch->values() as $index => $country) {
                    $weather = $weatherItems[$index] ?? null;

                    if (!is_array($weather)) {
                        $failedCount++;
                        $progressBar->advance();

                        continue;
                    }

                    $current = data_get($weather, 'current');

                    if (!is_array($current)) {
                        $failedCount++;
                        $progressBar->advance();

                        continue;
                    }

                    $temperature = $this->nullableNumber(
                        data_get($current, 'temperature_2m')
                    );

                    $rainfall = $this->nullableNumber(
                        data_get($current, 'precipitation')
                    );

                    $windSpeed = $this->nullableNumber(
                        data_get($current, 'wind_speed_10m')
                    );

                    $weatherCode = $this->nullableInteger(
                        data_get($current, 'weather_code')
                    );

                    if (
                        $temperature === null &&
                        $rainfall === null &&
                        $windSpeed === null &&
                        $weatherCode === null
                    ) {
                        $skippedCount++;
                        $progressBar->advance();

                        continue;
                    }

                    DB::table('weather_cache')->updateOrInsert(
                        [
                            'country_id' => $country->id,
                        ],
                        [
                            'temperature' => $temperature,
                            'rainfall' => $rainfall,
                            'wind_speed' => $windSpeed,
                            'weather_status' => $this->weatherStatus(
                                $weatherCode
                            ),
                            'weather_risk' => $this->weatherRisk(
                                weatherCode: $weatherCode,
                                rainfall: $rainfall,
                                windSpeed: $windSpeed
                            ),
                            'fetched_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $successCount++;
                    $progressBar->advance();
                }
            } catch (Throwable $exception) {
                $failedCount += $batch->count();

                $errorMessages[] = $exception->getMessage();

                $progressBar->advance($batch->count());
            }

            /*
             * Jeda ringan agar tidak membebani layanan API.
             */
            usleep(250000);
        }

        $progressBar->finish();
        $this->newLine(2);

        $status = match (true) {
            $successCount === 0 => 'failed',
            $failedCount > 0 || $skippedCount > 0 => 'partial',
            default => 'success',
        };

        $message = sprintf(
            '%d berhasil, %d gagal, %d dilewati dari %d negara.',
            $successCount,
            $failedCount,
            $skippedCount,
            $countries->count()
        );

        $this->recordApiLog(
            status: $status,
            responseCode: $lastHttpStatus,
            message: $message
        );

        if ($successCount > 0) {
            $this->components->info(
                'Sinkronisasi cuaca selesai.'
            );
        } else {
            $this->components->error(
                'Tidak ada data cuaca yang berhasil disimpan.'
            );
        }

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Negara diproses', $countries->count()],
                ['Berhasil', $successCount],
                ['Gagal', $failedCount],
                ['Dilewati', $skippedCount],
                [
                    'Total weather_cache',
                    DB::table('weather_cache')->count(),
                ],
            ]
        );

        if ($errorMessages !== []) {
            $this->newLine();
            $this->components->warn(
                'Beberapa pesan kesalahan:'
            );

            foreach (
                array_slice(array_unique($errorMessages), 0, 5)
                as $errorMessage
            ) {
                $this->line('- ' . $errorMessage);
            }
        }

        $this->newLine();
        $this->line(
            'Buka dashboard dan pilih negara untuk melihat cuaca terbaru.'
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

        $limit = max(
            0,
            (int) $this->option('limit')
        );

        $query = DB::table('countries')
            ->select(
                'id',
                'name',
                'cca2',
                'cca3',
                'latitude',
                'longitude'
            )
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
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

                $upperValue = strtoupper($countryOption);

                $innerQuery
                    ->orWhere('cca2', $upperValue)
                    ->orWhere('cca3', $upperValue)
                    ->orWhereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower($countryOption)]
                    );
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Menyiapkan HTTP client Laravel.
     */
    private function httpClient(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withUserAgent(
                'SupplyGuard/1.0 Laravel Weather Sync'
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
     * Membuat parameter Open-Meteo.
     */
    private function buildQuery(Collection $batch): array
    {
        return [
            'latitude' => $batch
                ->pluck('latitude')
                ->map(
                    fn ($value): string =>
                        (string) (float) $value
                )
                ->implode(','),

            'longitude' => $batch
                ->pluck('longitude')
                ->map(
                    fn ($value): string =>
                        (string) (float) $value
                )
                ->implode(','),

            'current' => implode(',', [
                'temperature_2m',
                'precipitation',
                'weather_code',
                'wind_speed_10m',
            ]),

            'temperature_unit' => 'celsius',
            'wind_speed_unit' => 'kmh',
            'precipitation_unit' => 'mm',
            'timezone' => 'auto',
            'forecast_days' => 1,
        ];
    }

    /**
     * Respons satu lokasi berupa objek, sedangkan banyak lokasi
     * berupa daftar objek. Method ini menyamakan bentuknya.
     */
    private function normaliseResponse(mixed $payload): array
    {
        if (!is_array($payload) || $payload === []) {
            return [];
        }

        return array_is_list($payload)
            ? $payload
            : [$payload];
    }

    /**
     * Mengubah nilai menjadi float nullable.
     */
    private function nullableNumber(mixed $value): ?float
    {
        return is_numeric($value)
            ? round((float) $value, 2)
            : null;
    }

    /**
     * Mengubah nilai menjadi integer nullable.
     */
    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value)
            ? (int) $value
            : null;
    }

    /**
     * Mengubah weather code menjadi teks Indonesia.
     */
    private function weatherStatus(?int $code): string
    {
        return match ($code) {
            0 => 'Cerah',
            1 => 'Cerah berawan',
            2 => 'Berawan sebagian',
            3 => 'Berawan',
            45, 48 => 'Berkabut',
            51, 53, 55 => 'Gerimis',
            56, 57 => 'Gerimis beku',
            61, 63, 65 => 'Hujan',
            66, 67 => 'Hujan beku',
            71, 73, 75 => 'Salju',
            77 => 'Butiran salju',
            80, 81, 82 => 'Hujan lokal',
            85, 86 => 'Salju lokal',
            95 => 'Badai petir',
            96, 99 => 'Badai petir dengan hujan es',
            default => 'Status tidak diketahui',
        };
    }

    /**
     * Menghitung risiko cuaca 0-100 berdasarkan kode cuaca,
     * curah hujan saat ini, dan kecepatan angin.
     */
    private function weatherRisk(
        ?int $weatherCode,
        ?float $rainfall,
        ?float $windSpeed
    ): int {
        $codeRisk = match ($weatherCode) {
            95 => 85,
            96, 99 => 95,
            80, 81, 82, 85, 86 => 60,
            65, 67, 75 => 55,
            61, 63, 66, 71, 73, 77 => 45,
            51, 53, 55, 56, 57 => 30,
            45, 48 => 25,
            0, 1, 2, 3 => 10,
            default => 20,
        };

        $rainRisk = match (true) {
            $rainfall === null => 0,
            $rainfall >= 30 => 100,
            $rainfall >= 20 => 90,
            $rainfall >= 10 => 75,
            $rainfall >= 5 => 55,
            $rainfall >= 1 => 30,
            default => 10,
        };

        $windRisk = match (true) {
            $windSpeed === null => 0,
            $windSpeed >= 90 => 100,
            $windSpeed >= 75 => 90,
            $windSpeed >= 60 => 80,
            $windSpeed >= 45 => 65,
            $windSpeed >= 30 => 40,
            default => 10,
        };

        return min(
            100,
            max($codeRisk, $rainRisk, $windRisk)
        );
    }

    /**
     * Menyimpan ringkasan pemanggilan API.
     */
    private function recordApiLog(
        string $status,
        ?int $responseCode,
        string $message
    ): void {
        if (!Schema::hasTable('api_logs')) {
            return;
        }

        try {
            DB::table('api_logs')->insert([
                'api_name' => 'Open-Meteo',
                'endpoint' => self::ENDPOINT,
                'status' => $status,
                'response_code' => $responseCode,
                'message' => $message,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            /*
             * Gagal mencatat log tidak boleh menghentikan
             * proses sinkronisasi utama.
             */
        }
    }
}
