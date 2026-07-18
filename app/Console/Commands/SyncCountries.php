<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
                            {--insecure : Nonaktifkan verifikasi SSL untuk XAMPP lokal}';

    /**
     * Penjelasan command.
     */
    protected $description =
        'Sinkronisasi negara dari REST Countries API v5 ke tabel countries';

    /**
     * Jumlah maksimal negara per request pada paket gratis REST Countries.
     */
    private const PAGE_LIMIT = 100;

    /**
     * Batas aman agar loop pagination tidak berjalan tanpa akhir.
     */
    private const MAX_REQUESTS = 10;

    /**
     * Field yang memang dipakai oleh SupplyGuard.
     */
    private const RESPONSE_FIELDS = [
        'names.common',
        'names.official',
        'codes.alpha_2',
        'codes.alpha_3',
        'capitals',
        'region',
        'subregion',
        'currencies',
        'languages',
        'coordinates',
    ];

    /**
     * Menjalankan command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info(
            'Sinkronisasi negara dari REST Countries API v5 dimulai.'
        );

        if (!Schema::hasTable('countries')) {
            $this->components->error(
                'Tabel countries belum tersedia. Jalankan migration terlebih dahulu.'
            );

            return self::FAILURE;
        }

        $apiKey = trim((string) config('services.rest_countries.key'));
        $baseUrl = rtrim(
            (string) config(
                'services.rest_countries.base_url',
                'https://api.restcountries.com/countries/v5'
            ),
            '/'
        );

        if ($apiKey === '') {
            $this->components->error(
                'REST_COUNTRIES_API_KEY belum diisi pada file .env.'
            );
            $this->line(
                'Isi API key REST Countries terlebih dahulu, lalu jalankan command ini kembali.'
            );

            return self::FAILURE;
        }

        $request = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(90)
            ->retry(3, 1000, null, false);

        if ((bool) $this->option('insecure')) {
            $this->components->warn(
                'Verifikasi SSL dinonaktifkan untuk proses sinkronisasi ini.'
            );

            $request = $request->withoutVerifying();
        }

        try {
            [$countries, $requestCount, $lastStatus] =
                $this->fetchAllCountries($request, $baseUrl);
        } catch (Throwable $exception) {
            $this->recordApiLog(
                endpoint: $baseUrl,
                status: 'failed',
                responseCode: null,
                message: $exception->getMessage()
            );

            $this->components->error(
                'Tidak dapat menyelesaikan request ke REST Countries.'
            );
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if ($countries === []) {
            $message =
                'REST Countries tidak mengembalikan daftar negara yang valid.';

            $this->recordApiLog(
                endpoint: $baseUrl,
                status: 'failed',
                responseCode: $lastStatus,
                message: $message
            );

            $this->components->error($message);

            return self::FAILURE;
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

        $progressBar = $this->output->createProgressBar(
            count($countries)
        );
        $progressBar->start();

        foreach ($countries as $country) {
            if (!is_array($country)) {
                $skipped++;
                $progressBar->advance();

                continue;
            }

            $cca3 = strtoupper(
                trim(
                    (string) (
                        data_get($country, 'codes.alpha_3')
                        ?? data_get($country, 'cca3')
                        ?? ''
                    )
                )
            );

            $cca2 = strtoupper(
                trim(
                    (string) (
                        data_get($country, 'codes.alpha_2')
                        ?? data_get($country, 'cca2')
                        ?? ''
                    )
                )
            );

            /*
             * CCA3 dipakai sebagai identitas sinkronisasi agar data negara
             * dapat diperbarui tanpa membuat baris duplikat.
             */
            if ($cca3 === '') {
                $skipped++;
                $progressBar->advance();

                continue;
            }

            [$currencyCode, $currencyName] =
                $this->extractPrimaryCurrency($country);

            $rows[] = [
                'name' => $this->limitText(
                    data_get($country, 'names.common')
                    ?? data_get($country, 'name.common')
                ) ?? $cca3,

                'official_name' => $this->limitText(
                    data_get($country, 'names.official')
                    ?? data_get($country, 'name.official')
                ),

                'cca2' => $cca2 !== '' ? $cca2 : null,
                'cca3' => $cca3,
                'capital' => $this->limitText(
                    $this->extractCapital($country)
                ),
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
                'language' => $this->limitText(
                    $this->extractLanguages($country)
                ),
                'latitude' => $this->extractCoordinates($country)[0],
                'longitude' => $this->extractCoordinates($country)[1],
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
                endpoint: $baseUrl,
                status: 'failed',
                responseCode: $lastStatus,
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
            endpoint: $baseUrl,
            status: 'success',
            responseCode: $lastStatus,
            message: sprintf(
                '%d objek REST Countries diproses melalui %d request. '
                . '%d baru, %d diperbarui, %d dilewati.',
                count($countries),
                $requestCount,
                $created,
                $updated,
                $skipped
            )
        );

        $this->components->info(
            'Sinkronisasi REST Countries berhasil.'
        );

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Request API berhasil', $requestCount],
                ['Data dari REST Countries', count($countries)],
                ['Negara baru', $created],
                ['Negara diperbarui', $updated],
                ['Data dilewati', $skipped],
                ['Total tabel countries', $totalCountries],
            ]
        );

        $this->newLine();
        $this->line(
            'Sinkronisasi bersifat aman: negara lama tidak dihapus dan relasi data tetap dipertahankan.'
        );

        return self::SUCCESS;
    }

    /**
     * Mengambil seluruh halaman REST Countries.
     */
    private function fetchAllCountries(
        PendingRequest $request,
        string $baseUrl
    ): array {
        $countries = [];
        $offset = 0;
        $requestCount = 0;
        $lastStatus = null;

        do {
            if ($requestCount >= self::MAX_REQUESTS) {
                throw new \RuntimeException(
                    'Pagination dihentikan karena melewati batas request aman.'
                );
            }

            $response = $request->get($baseUrl, [
                'limit' => self::PAGE_LIMIT,
                'offset' => $offset,
                'response_fields' => implode(
                    ',',
                    self::RESPONSE_FIELDS
                ),
            ]);

            $requestCount++;
            $lastStatus = $response->status();

            if (!$response->successful()) {
                $this->throwResponseError(
                    $response,
                    $baseUrl,
                    $offset
                );
            }

            $payload = $response->json();
            $objects = data_get($payload, 'data.objects', []);
            $meta = data_get($payload, 'data.meta', []);

            if (!is_array($objects)) {
                throw new \RuntimeException(
                    'Struktur data.objects dari REST Countries tidak valid.'
                );
            }

            foreach ($objects as $object) {
                if (is_array($object)) {
                    $countries[] = $object;
                }
            }

            $count = count($objects);
            $more = (bool) data_get($meta, 'more', false);

            if ($count === 0) {
                $more = false;
            }

            $offset += $count;
        } while ($more);

        return [
            $this->uniqueCountries($countries),
            $requestCount,
            $lastStatus,
        ];
    }

    /**
     * Mengubah response gagal menjadi pesan yang mudah dipahami.
     */
    private function throwResponseError(
        Response $response,
        string $baseUrl,
        int $offset
    ): never {
        $apiMessage = data_get(
            $response->json(),
            'error.message'
        ) ?? data_get(
            $response->json(),
            'message'
        );

        $message = sprintf(
            'REST Countries memberikan HTTP %d pada offset %d%s',
            $response->status(),
            $offset,
            is_string($apiMessage) && trim($apiMessage) !== ''
                ? ': ' . trim($apiMessage)
                : '.'
        );

        $this->recordApiLog(
            endpoint: $baseUrl . '?offset=' . $offset,
            status: 'failed',
            responseCode: $response->status(),
            message: $message
        );

        throw new \RuntimeException($message);
    }

    /**
     * Menghapus objek negara duplikat berdasarkan kode CCA3.
     */
    private function uniqueCountries(array $countries): array
    {
        $unique = [];

        foreach ($countries as $country) {
            $cca3 = strtoupper(
                trim(
                    (string) (
                        data_get($country, 'codes.alpha_3')
                        ?? data_get($country, 'cca3')
                        ?? ''
                    )
                )
            );

            if ($cca3 === '') {
                continue;
            }

            $unique[$cca3] = $country;
        }

        ksort($unique);

        return array_values($unique);
    }

    /**
     * Mengambil mata uang utama dari struktur REST Countries v5.
     */
    private function extractPrimaryCurrency(array $country): array
    {
        $currencies = data_get($country, 'currencies', []);

        if (!is_array($currencies) || $currencies === []) {
            return [null, null];
        }

        /*
         * Mendukung bentuk object:
         * {"USD": {"name": "United States dollar"}}
         */
        if (!array_is_list($currencies)) {
            $currencyCode = array_key_first($currencies);

            if ($currencyCode === null) {
                return [null, null];
            }

            $currency = $currencies[$currencyCode] ?? [];

            return [
                strtoupper(trim((string) $currencyCode)),
                is_array($currency)
                    ? $this->firstTextValue(
                        $currency,
                        ['name', 'english_name', 'official_name']
                    )
                    : null,
            ];
        }

        /*
         * Mendukung apabila API mengembalikan currencies sebagai array.
         */
        $currency = $currencies[0] ?? null;

        if (is_string($currency)) {
            return [strtoupper(trim($currency)), null];
        }

        if (!is_array($currency)) {
            return [null, null];
        }

        return [
            $this->firstTextValue(
                $currency,
                ['code', 'iso_code', 'alpha_3']
            ),
            $this->firstTextValue(
                $currency,
                ['name', 'english_name', 'official_name']
            ),
        ];
    }

    /**
     * Menggabungkan nama bahasa menjadi satu teks.
     */
    private function extractLanguages(array $country): ?string
    {
        $languages = data_get($country, 'languages', []);

        if (!is_array($languages) || $languages === []) {
            return null;
        }

        $names = [];

        foreach ($languages as $key => $language) {
            if (is_string($language)) {
                $value = trim($language);

                if ($value !== '') {
                    $names[] = $value;
                }

                continue;
            }

            if (is_array($language)) {
                $value = $this->firstTextValue(
                    $language,
                    [
                        'english_name',
                        'name',
                        'native_name',
                        'endonym',
                    ]
                );

                if ($value !== null) {
                    $names[] = $value;
                }

                continue;
            }

            if (is_string($key) && trim($key) !== '') {
                $names[] = trim($key);
            }
        }

        $names = array_values(
            array_unique(
                array_filter(
                    array_map('trim', $names)
                )
            )
        );

        if ($names === []) {
            return null;
        }

        sort($names);

        return implode(', ', $names);
    }

    /**
     * Mengambil ibu kota pertama.
     */
    private function extractCapital(array $country): ?string
    {
        $capitals = data_get(
            $country,
            'capitals',
            data_get($country, 'capital', [])
        );

        if (is_string($capitals)) {
            return trim($capitals) !== ''
                ? trim($capitals)
                : null;
        }

        if (!is_array($capitals) || $capitals === []) {
            return null;
        }

        $capital = reset($capitals);

        if (is_string($capital) && trim($capital) !== '') {
            return trim($capital);
        }

        if (is_array($capital)) {
            return $this->firstTextValue(
                $capital,
                ['name', 'common', 'official']
            );
        }

        return null;
    }

    /**
     * Mengambil koordinat pusat negara.
     */
    private function extractCoordinates(array $country): array
    {
        $latitude = data_get($country, 'coordinates.lat');
        $longitude = data_get($country, 'coordinates.lng');

        if (is_numeric($latitude) && is_numeric($longitude)) {
            return [
                round((float) $latitude, 7),
                round((float) $longitude, 7),
            ];
        }

        $coordinates = data_get($country, 'latlng', []);

        if (!is_array($coordinates) || count($coordinates) < 2) {
            return [null, null];
        }

        return [
            is_numeric($coordinates[0])
                ? round((float) $coordinates[0], 7)
                : null,
            is_numeric($coordinates[1])
                ? round((float) $coordinates[1], 7)
                : null,
        ];
    }

    /**
     * Mengambil teks pertama yang tersedia dari beberapa key.
     */
    private function firstTextValue(
        array $data,
        array $keys
    ): ?string {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Membatasi panjang teks agar sesuai kolom database.
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
     * Menyimpan riwayat pemanggilan REST Countries.
     */
    private function recordApiLog(
        string $endpoint,
        string $status,
        ?int $responseCode,
        string $message
    ): void {
        if (!Schema::hasTable('api_logs')) {
            return;
        }

        try {
            DB::table('api_logs')->insert([
                'api_name' => 'REST Countries',
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
             * proses utama sinkronisasi negara.
             */
        }
    }
}
