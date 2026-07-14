<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncCountries extends Command
{
    /**
     * Nama command yang dijalankan dari terminal.
     */
    protected $signature = 'supplyguard:sync-countries
                            {--independent : Hanya mengambil negara berdaulat}
                            {--insecure : Nonaktifkan verifikasi SSL untuk XAMPP lokal}';

    /**
     * Penjelasan command.
     */
    protected $description = 'Sinkronisasi seluruh negara ISO ke tabel countries SupplyGuard';

    /**
     * Sumber data negara.
     */
    private const SOURCE_URL =
        'https://raw.githubusercontent.com/mledoze/countries/refs/heads/master/countries.json';

    /**
     * Menjalankan command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Sinkronisasi negara SupplyGuard dimulai.');

        if (!Schema::hasTable('countries')) {
            $this->components->error(
                'Tabel countries belum tersedia. Jalankan migration terlebih dahulu.'
            );

            return self::FAILURE;
        }

        try {
            $request = Http::acceptJson()
                ->timeout(90)
                ->retry(3, 1000, null, false);

            /*
             * Opsi ini hanya dipakai apabila XAMPP lokal mengalami
             * masalah sertifikat SSL.
             */
            if ((bool) $this->option('insecure')) {
                $this->components->warn(
                    'Verifikasi SSL dinonaktifkan untuk proses sinkronisasi ini.'
                );

                $request = $request->withoutVerifying();
            }

            $response = $request->get(self::SOURCE_URL);
        } catch (Throwable $exception) {
            $this->recordApiLog(
                status: 'failed',
                responseCode: null,
                message: $exception->getMessage()
            );

            $this->components->error(
                'Tidak dapat menghubungi sumber data negara.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if (!$response->successful()) {
            $message = sprintf(
                'Sumber data memberikan HTTP status %d.',
                $response->status()
            );

            $this->recordApiLog(
                status: 'failed',
                responseCode: $response->status(),
                message: $message
            );

            $this->components->error($message);

            return self::FAILURE;
        }

        $countries = $response->json();

        if (!is_array($countries) || $countries === []) {
            $message = 'Respons sumber data tidak berisi daftar negara yang valid.';

            $this->recordApiLog(
                status: 'failed',
                responseCode: $response->status(),
                message: $message
            );

            $this->components->error($message);

            return self::FAILURE;
        }

        /*
         * Secara default semua entitas ISO dimasukkan.
         * Gunakan --independent jika hanya ingin negara berdaulat.
         */
        if ((bool) $this->option('independent')) {
            $countries = array_values(
                array_filter(
                    $countries,
                    static fn (array $country): bool =>
                        ($country['independent'] ?? false) === true
                )
            );
        }

        $existingCountryCodes = DB::table('countries')
            ->whereNotNull('cca3')
            ->pluck('id', 'cca3')
            ->all();

        $rows = [];
        $skipped = 0;
        $created = 0;
        $updated = 0;
        $now = now();

        $progressBar = $this->output->createProgressBar(count($countries));
        $progressBar->start();

        foreach ($countries as $country) {
            $cca3 = strtoupper(
                trim((string) data_get($country, 'cca3', ''))
            );

            $cca2 = strtoupper(
                trim((string) data_get($country, 'cca2', ''))
            );

            /*
             * CCA3 menjadi identitas utama sinkronisasi.
             */
            if ($cca3 === '') {
                $skipped++;
                $progressBar->advance();

                continue;
            }

            [$currencyCode, $currencyName] =
                $this->extractPrimaryCurrency($country);

            $languages = $this->extractLanguages($country);
            $capital = $this->extractCapital($country);
            [$latitude, $longitude] = $this->extractCoordinates($country);

            $rows[] = [
                'name' => $this->limitText(
                    data_get($country, 'name.common')
                ) ?? $cca3,

                'official_name' => $this->limitText(
                    data_get($country, 'name.official')
                ),

                'cca2' => $cca2 !== '' ? $cca2 : null,
                'cca3' => $cca3,
                'capital' => $this->limitText($capital),
                'region' => $this->limitText(
                    data_get($country, 'region')
                ),
                'subregion' => $this->limitText(
                    data_get($country, 'subregion')
                ),
                'currency_code' => $this->limitText(
                    $currencyCode,
                    10
                ),
                'currency_name' => $this->limitText(
                    $currencyName
                ),
                'language' => $this->limitText($languages),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (array_key_exists($cca3, $existingCountryCodes)) {
                $updated++;
            } else {
                $created++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($rows === []) {
            $this->components->error(
                'Tidak ada negara yang dapat disimpan.'
            );

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($rows): void {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('countries')->upsert(
                        $chunk,
                        ['cca3'],
                        [
                            'name',
                            'official_name',
                            'cca2',
                            'capital',
                            'region',
                            'subregion',
                            'currency_code',
                            'currency_name',
                            'language',
                            'latitude',
                            'longitude',
                            'updated_at',
                        ]
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->recordApiLog(
                status: 'failed',
                responseCode: $response->status(),
                message: $exception->getMessage()
            );

            $this->components->error(
                'Data negara gagal disimpan ke database.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $totalCountries = DB::table('countries')->count();

        $this->recordApiLog(
            status: 'success',
            responseCode: $response->status(),
            message: sprintf(
                '%d negara diproses. %d baru, %d diperbarui, %d dilewati.',
                count($rows),
                $created,
                $updated,
                $skipped
            )
        );

        $this->components->info('Sinkronisasi negara berhasil.');

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Data dari sumber', count($countries)],
                ['Negara baru', $created],
                ['Negara diperbarui', $updated],
                ['Data dilewati', $skipped],
                ['Total tabel countries', $totalCountries],
            ]
        );

        $this->newLine();
        $this->line(
            'Seluruh negara sekarang dapat digunakan pada dashboard, comparison, dan watchlist.'
        );

        return self::SUCCESS;
    }

    /**
     * Mengambil mata uang pertama karena struktur tabel countries
     * saat ini hanya memiliki satu currency_code dan currency_name.
     */
    private function extractPrimaryCurrency(array $country): array
    {
        $currencies = data_get($country, 'currencies', []);

        if (!is_array($currencies) || $currencies === []) {
            return [null, null];
        }

        $currencyCode = array_key_first($currencies);

        if ($currencyCode === null) {
            return [null, null];
        }

        $currency = $currencies[$currencyCode] ?? [];

        $currencyName = is_array($currency)
            ? ($currency['name'] ?? null)
            : null;

        return [
            strtoupper((string) $currencyCode),
            is_string($currencyName) ? $currencyName : null,
        ];
    }

    /**
     * Menggabungkan seluruh bahasa negara menjadi satu teks.
     */
    private function extractLanguages(array $country): ?string
    {
        $languages = data_get($country, 'languages', []);

        if (!is_array($languages) || $languages === []) {
            return null;
        }

        $languages = array_filter(
            array_map(
                static fn (mixed $language): string =>
                    trim((string) $language),
                array_values($languages)
            )
        );

        if ($languages === []) {
            return null;
        }

        sort($languages);

        return implode(', ', array_unique($languages));
    }

    /**
     * Mengambil ibu kota pertama.
     */
    private function extractCapital(array $country): ?string
    {
        $capitals = data_get($country, 'capital', []);

        if (!is_array($capitals) || $capitals === []) {
            return null;
        }

        $capital = reset($capitals);

        return is_string($capital) && trim($capital) !== ''
            ? trim($capital)
            : null;
    }

    /**
     * Mengambil koordinat pusat negara.
     */
    private function extractCoordinates(array $country): array
    {
        $coordinates = data_get($country, 'latlng', []);

        if (!is_array($coordinates) || count($coordinates) < 2) {
            return [null, null];
        }

        $latitude = is_numeric($coordinates[0])
            ? round((float) $coordinates[0], 7)
            : null;

        $longitude = is_numeric($coordinates[1])
            ? round((float) $coordinates[1], 7)
            : null;

        return [$latitude, $longitude];
    }

    /**
     * Membatasi panjang teks agar sesuai kolom string database.
     */
    private function limitText(
        mixed $value,
        int $maximumLength = 255
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maximumLength);
    }

    /**
     * Menyimpan riwayat pemanggilan sumber data.
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
                'api_name' => 'Countries ISO Dataset',
                'endpoint' => self::SOURCE_URL,
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
             * proses utama sinkronisasi negara.
             */
        }
    }
}
