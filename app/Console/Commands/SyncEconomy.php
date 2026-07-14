<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncEconomy extends Command
{
    /**
     * Nama perintah Artisan.
     */
    protected $signature = 'supplyguard:sync-economy
                            {--country= : ID, CCA2, CCA3, atau nama negara}
                            {--from= : Tahun awal, contoh 2021}
                            {--to= : Tahun akhir, contoh 2025}
                            {--insecure : Nonaktifkan verifikasi SSL untuk XAMPP lokal}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Sinkronisasi data ekonomi negara menggunakan World Bank Indicators API';

    /**
     * Endpoint dasar World Bank Indicators API.
     */
    private const BASE_URL = 'https://api.worldbank.org/v2';

    /**
     * Indikator yang disimpan ke economic_indicators.
     */
    private const INDICATORS = [
        'NY.GDP.MKTP.CD' => [
            'field' => 'gdp',
            'label' => 'PDB',
        ],
        'FP.CPI.TOTL.ZG' => [
            'field' => 'inflation',
            'label' => 'Inflasi',
        ],
        'SP.POP.TOTL' => [
            'field' => 'population',
            'label' => 'Populasi',
        ],
        'NE.EXP.GNFS.CD' => [
            'field' => 'exports',
            'label' => 'Ekspor',
        ],
        'NE.IMP.GNFS.CD' => [
            'field' => 'imports',
            'label' => 'Impor',
        ],
    ];

    /**
     * Menjalankan sinkronisasi ekonomi.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info(
            'Sinkronisasi ekonomi SupplyGuard dimulai.'
        );

        if (!Schema::hasTable('countries')) {
            $this->components->error(
                'Tabel countries belum tersedia.'
            );

            return self::FAILURE;
        }

        if (!Schema::hasTable('economic_indicators')) {
            $this->components->error(
                'Tabel economic_indicators belum tersedia.'
            );

            return self::FAILURE;
        }

        [$fromYear, $toYear] = $this->resolveYearRange();

        if ($fromYear > $toYear) {
            $this->components->error(
                'Tahun awal tidak boleh lebih besar dari tahun akhir.'
            );

            return self::FAILURE;
        }

        $countries = $this->countriesToProcess();

        if ($countries->isEmpty()) {
            $this->components->error(
                'Negara yang akan diproses tidak ditemukan.'
            );

            return self::FAILURE;
        }

        $countryOption = trim(
            (string) $this->option('country')
        );

        $countryScope = $countryOption !== ''
            ? (string) $countries->first()->cca3
            : 'all';

        $countryMap = $countries
            ->filter(
                fn ($country): bool =>
                    !empty($country->cca3)
            )
            ->keyBy(
                fn ($country): string =>
                    strtoupper((string) $country->cca3)
            );

        if ($countryMap->isEmpty()) {
            $this->components->error(
                'Tidak ada kode CCA3 yang valid untuk diproses.'
            );

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Cakupan negara: %s | Periode: %d-%d',
            $countryOption !== ''
                ? $countries->first()->name
                : $countryMap->count() . ' negara lokal',
            $fromYear,
            $toYear
        ));

        if ((bool) $this->option('insecure')) {
            $this->components->warn(
                'Verifikasi SSL dinonaktifkan untuk proses ini.'
            );
        }

        /*
         * Struktur records:
         * [
         *   country_id => [
         *      year => [
         *          gdp => ...,
         *          inflation => ...,
         *          population => ...,
         *          exports => ...,
         *          imports => ...,
         *      ]
         *   ]
         * ]
         */
        $records = [];
        $indicatorSuccess = 0;
        $indicatorFailed = 0;
        $lastHttpStatus = null;
        $errorMessages = [];

        $progressBar = $this->output->createProgressBar(
            count(self::INDICATORS)
        );

        $progressBar->start();

        foreach (self::INDICATORS as $indicatorCode => $config) {
            try {
                $url = sprintf(
                    '%s/country/%s/indicator/%s',
                    self::BASE_URL,
                    rawurlencode($countryScope),
                    rawurlencode($indicatorCode)
                );

                $response = $this->httpClient()->get(
                    $url,
                    [
                        'format' => 'json',
                        'source' => 2,
                        'date' => $fromYear . ':' . $toYear,
                        'per_page' => 20000,
                    ]
                );

                $lastHttpStatus = $response->status();

                if (!$response->successful()) {
                    $indicatorFailed++;

                    $errorMessages[] = sprintf(
                        '%s gagal dengan HTTP %d.',
                        $config['label'],
                        $response->status()
                    );

                    $progressBar->advance();

                    continue;
                }

                $items = $this->extractItems(
                    $response->json()
                );

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $cca3 = strtoupper(
                        trim(
                            (string) (
                                $item['countryiso3code'] ?? ''
                            )
                        )
                    );

                    if (
                        $cca3 === '' ||
                        !$countryMap->has($cca3)
                    ) {
                        continue;
                    }

                    $year = filter_var(
                        $item['date'] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    if (
                        $year === false ||
                        $year < $fromYear ||
                        $year > $toYear
                    ) {
                        continue;
                    }

                    $value = $this->normaliseValue(
                        field: $config['field'],
                        value: $item['value'] ?? null
                    );

                    if ($value === null) {
                        continue;
                    }

                    $countryId = (int) $countryMap
                        ->get($cca3)
                        ->id;

                    $records[$countryId][$year][
                        $config['field']
                    ] = $value;
                }

                $indicatorSuccess++;
            } catch (Throwable $exception) {
                $indicatorFailed++;
                $errorMessages[] = sprintf(
                    '%s: %s',
                    $config['label'],
                    $exception->getMessage()
                );
            }

            $progressBar->advance();

            /*
             * Jeda ringan agar tidak membebani API.
             */
            usleep(250000);
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($indicatorSuccess === 0) {
            $message = 'Semua indikator ekonomi gagal diambil.';

            $this->recordApiLog(
                status: 'failed',
                responseCode: $lastHttpStatus,
                message: $message
            );

            $this->components->error($message);

            foreach (
                array_slice(array_unique($errorMessages), 0, 5)
                as $errorMessage
            ) {
                $this->line('- ' . $errorMessage);
            }

            return self::FAILURE;
        }

        $savedRows = 0;
        $countriesWithData = [];

        try {
            DB::transaction(
                function () use (
                    $records,
                    &$savedRows,
                    &$countriesWithData
                ): void {
                    foreach ($records as $countryId => $years) {
                        foreach ($years as $year => $values) {
                            $dataToSave = [];

                            foreach (
                                [
                                    'gdp',
                                    'inflation',
                                    'population',
                                    'exports',
                                    'imports',
                                ] as $field
                            ) {
                                if (
                                    array_key_exists(
                                        $field,
                                        $values
                                    ) &&
                                    $values[$field] !== null
                                ) {
                                    $dataToSave[$field] =
                                        $values[$field];
                                }
                            }

                            if ($dataToSave === []) {
                                continue;
                            }

                            $existing = DB::table(
                                'economic_indicators'
                            )
                                ->where(
                                    'country_id',
                                    $countryId
                                )
                                ->where('year', $year)
                                ->exists();

                            if ($existing) {
                                DB::table(
                                    'economic_indicators'
                                )
                                    ->where(
                                        'country_id',
                                        $countryId
                                    )
                                    ->where('year', $year)
                                    ->update(
                                        array_merge(
                                            $dataToSave,
                                            [
                                                'updated_at' => now(),
                                            ]
                                        )
                                    );
                            } else {
                                DB::table(
                                    'economic_indicators'
                                )->insert(
                                    array_merge(
                                        [
                                            'country_id' =>
                                                $countryId,
                                            'year' => $year,
                                        ],
                                        $dataToSave,
                                        [
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ]
                                    )
                                );
                            }

                            $savedRows++;
                            $countriesWithData[$countryId] = true;
                        }
                    }
                }
            );
        } catch (Throwable $exception) {
            $this->recordApiLog(
                status: 'failed',
                responseCode: $lastHttpStatus,
                message: $exception->getMessage()
            );

            $this->components->error(
                'Data ekonomi gagal disimpan ke database.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $status = match (true) {
            $savedRows === 0 => 'failed',
            $indicatorFailed > 0 => 'partial',
            default => 'success',
        };

        $message = sprintf(
            '%d indikator berhasil, %d indikator gagal, %d baris disimpan untuk %d negara.',
            $indicatorSuccess,
            $indicatorFailed,
            $savedRows,
            count($countriesWithData)
        );

        $this->recordApiLog(
            status: $status,
            responseCode: $lastHttpStatus,
            message: $message
        );

        if ($savedRows > 0) {
            $this->components->info(
                'Sinkronisasi ekonomi selesai.'
            );
        } else {
            $this->components->warn(
                'API berhasil dihubungi, tetapi tidak ada data yang dapat disimpan.'
            );
        }

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                [
                    'Negara lokal dalam cakupan',
                    $countryMap->count(),
                ],
                [
                    'Negara memiliki data',
                    count($countriesWithData),
                ],
                [
                    'Indikator berhasil',
                    $indicatorSuccess,
                ],
                [
                    'Indikator gagal',
                    $indicatorFailed,
                ],
                [
                    'Baris disimpan/diperbarui',
                    $savedRows,
                ],
                [
                    'Total economic_indicators',
                    DB::table(
                        'economic_indicators'
                    )->count(),
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
            'Buka dashboard untuk melihat PDB, inflasi, populasi, ekspor, dan impor.'
        );

        return $savedRows > 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Menentukan rentang tahun.
     */
    private function resolveYearRange(): array
    {
        $defaultToYear = (int) date('Y') - 1;
        $defaultFromYear = $defaultToYear - 4;

        $fromOption = trim(
            (string) $this->option('from')
        );

        $toOption = trim(
            (string) $this->option('to')
        );

        $fromYear = $fromOption !== ''
            ? (int) $fromOption
            : $defaultFromYear;

        $toYear = $toOption !== ''
            ? (int) $toOption
            : $defaultToYear;

        return [
            max(1960, $fromYear),
            min((int) date('Y'), $toYear),
        ];
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
                'cca3'
            )
            ->whereNotNull('cca3')
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
                    ->orWhereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower($countryOption)]
                    );
            });
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
                'SupplyGuard/1.0 Laravel Economy Sync'
            )
            ->connectTimeout(20)
            ->timeout(120)
            ->retry(3, 1500, null, false);

        if ((bool) $this->option('insecure')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * Mengambil daftar data dari respons World Bank.
     *
     * Format respons:
     * [
     *   0 => metadata,
     *   1 => daftar data
     * ]
     */
    private function extractItems(mixed $payload): array
    {
        if (
            !is_array($payload) ||
            !isset($payload[1]) ||
            !is_array($payload[1])
        ) {
            return [];
        }

        return $payload[1];
    }

    /**
     * Menyesuaikan nilai dengan tipe kolom database.
     */
    private function normaliseValue(
        string $field,
        mixed $value
    ): int|float|null {
        if (!is_numeric($value)) {
            return null;
        }

        if ($field === 'population') {
            return max(
                0,
                (int) round((float) $value)
            );
        }

        if ($field === 'inflation') {
            return round((float) $value, 2);
        }

        return round((float) $value, 2);
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
                'api_name' => 'World Bank Indicators API',
                'endpoint' => self::BASE_URL,
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
