<?php

namespace App\Console\Commands;

use App\Services\RiskScoringService;
use App\Services\SentimentService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SyncNews extends Command
{
    /**
     * Satu request digunakan untuk satu negara.
     *
     * Default hanya 20 negara agar aman untuk akun gratis.
     */
    protected $signature = 'supplyguard:sync-news
                            {--country= : ID, CCA2, CCA3, atau nama negara}
                            {--limit=20 : Jumlah negara yang diproses saat --country tidak digunakan}
                            {--offset=0 : Lewati sejumlah negara dari urutan awal}
                            {--articles=10 : Maksimal artikel per negara, 1 sampai 10}
                            {--days=30 : Ambil berita dari beberapa hari terakhir, 1 sampai 30}
                            {--lang=en : Bahasa artikel, contoh en atau id}
                            {--skip-risk : Jangan hitung ulang risiko setelah berita disimpan}
                            {--insecure : Nonaktifkan verifikasi SSL untuk XAMPP lokal}';

    protected $description = 'Sinkronisasi berita rantai pasok dari GNews, analisis sentimen, dan perbarui skor risiko';

    /**
     * Menjalankan command.
     */
    public function handle(
        SentimentService $sentimentService,
        RiskScoringService $riskScoringService
    ): int {
        $this->newLine();
        $this->components->info(
            'Sinkronisasi berita SupplyGuard dimulai.'
        );

        if (!$this->requiredTablesExist()) {
            return self::FAILURE;
        }

        $apiKey = trim(
            (string) config('services.gnews.key')
        );

        if (
            $apiKey === '' ||
            str_contains(
                strtoupper($apiKey),
                'MASUKKAN_API_KEY'
            ) ||
            str_contains(
                strtoupper($apiKey),
                'API_KEY_KAMU'
            )
        ) {
            $this->components->error(
                'GNEWS_API_KEY belum diisi dengan API key asli pada file .env.'
            );

            $this->line(
                'Isi GNEWS_API_KEY dengan key dari dashboard GNews, lalu jalankan php artisan config:clear.'
            );

            return self::FAILURE;
        }

        $countries = $this->countriesToProcess();

        if ($countries->isEmpty()) {
            $this->components->warn(
                'Negara yang akan diproses tidak ditemukan.'
            );

            return self::SUCCESS;
        }

        $articleLimit = max(
            1,
            min(10, (int) $this->option('articles'))
        );

        $days = max(
            1,
            min(30, (int) $this->option('days'))
        );

        $language = strtolower(
            trim((string) $this->option('lang'))
        );

        if (!preg_match('/^[a-z]{2}$/', $language)) {
            $this->components->error(
                'Opsi --lang harus berupa kode dua huruf, contoh en atau id.'
            );

            return self::FAILURE;
        }

        $skipRisk = (bool) $this->option('skip-risk');

        $this->line(sprintf(
            'Negara: %d | Artikel per negara: %d | Periode: %d hari | Bahasa: %s',
            $countries->count(),
            $articleLimit,
            $days,
            strtoupper($language)
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

        $summary = [
            'countries_processed' => 0,
            'api_success' => 0,
            'api_failed' => 0,
            'countries_without_news' => 0,
            'articles_found' => 0,
            'articles_inserted' => 0,
            'articles_updated' => 0,
            'articles_failed' => 0,
            'risk_updated' => 0,
            'risk_failed' => 0,
            'errors' => [],
        ];

        $lastHttpStatus = null;
        $stopBecauseQuota = false;

        foreach ($countries as $index => $country) {
            $summary['countries_processed']++;

            try {
                $response = $this->requestCountryNews(
                    country: $country,
                    apiKey: $apiKey,
                    language: $language,
                    articleLimit: $articleLimit,
                    days: $days
                );

                $lastHttpStatus = $response->status();

                if ($response->status() === 403) {
                    $summary['api_failed']++;

                    $summary['errors'][] = sprintf(
                        '%s: kuota API habis atau akses ditolak.',
                        $country->name
                    );

                    $stopBecauseQuota = true;
                    $progressBar->advance();

                    break;
                }

                if (!$response->successful()) {
                    $summary['api_failed']++;

                    $summary['errors'][] = sprintf(
                        '%s: HTTP %d - %s',
                        $country->name,
                        $response->status(),
                        $this->apiErrorMessage($response)
                    );

                    $progressBar->advance();

                    $this->waitBeforeNextRequest(
                        hasNext: $index < $countries->count() - 1
                    );

                    continue;
                }

                $summary['api_success']++;

                $payload = $response->json();
                $articles = is_array($payload)
                    && isset($payload['articles'])
                    && is_array($payload['articles'])
                        ? $payload['articles']
                        : [];

                if ($articles === []) {
                    $summary['countries_without_news']++;
                    $progressBar->advance();

                    $this->waitBeforeNextRequest(
                        hasNext: $index < $countries->count() - 1
                    );

                    continue;
                }

                $summary['articles_found'] += count($articles);

                $countryChanged = false;

                foreach ($articles as $article) {
                    if (!is_array($article)) {
                        $summary['articles_failed']++;

                        continue;
                    }

                    try {
                        $saveResult = $this->saveArticle(
                            countryId: (int) $country->id,
                            article: $article,
                            sentimentService: $sentimentService
                        );

                        if ($saveResult === 'inserted') {
                            $summary['articles_inserted']++;
                            $countryChanged = true;
                        } elseif ($saveResult === 'updated') {
                            $summary['articles_updated']++;
                            $countryChanged = true;
                        }
                    } catch (Throwable $exception) {
                        $summary['articles_failed']++;

                        if (count($summary['errors']) < 15) {
                            $summary['errors'][] = sprintf(
                                '%s: artikel gagal disimpan - %s',
                                $country->name,
                                $exception->getMessage()
                            );
                        }
                    }
                }

                if ($countryChanged && !$skipRisk) {
                    try {
                        $riskScoringService->calculateCountry(
                            countryId: (int) $country->id,
                            save: true
                        );

                        $summary['risk_updated']++;
                    } catch (Throwable $exception) {
                        $summary['risk_failed']++;

                        if (count($summary['errors']) < 15) {
                            $summary['errors'][] = sprintf(
                                '%s: risiko gagal diperbarui - %s',
                                $country->name,
                                $exception->getMessage()
                            );
                        }
                    }
                }
            } catch (Throwable $exception) {
                $summary['api_failed']++;

                if (count($summary['errors']) < 15) {
                    $summary['errors'][] = sprintf(
                        '%s: %s',
                        $country->name,
                        $exception->getMessage()
                    );
                }
            }

            $progressBar->advance();

            $this->waitBeforeNextRequest(
                hasNext: $index < $countries->count() - 1
            );
        }

        if (
            $stopBecauseQuota &&
            $summary['countries_processed'] < $countries->count()
        ) {
            $remaining = $countries->count()
                - $summary['countries_processed'];

            $progressBar->advance($remaining);
        }

        $progressBar->finish();
        $this->newLine(2);

        $status = match (true) {
            $summary['api_success'] === 0 => 'failed',
            $summary['api_failed'] > 0
                || $summary['articles_failed'] > 0
                || $summary['risk_failed'] > 0 => 'partial',
            default => 'success',
        };

        $message = sprintf(
            '%d negara diproses, %d request berhasil, %d artikel baru, %d artikel diperbarui, %d risiko diperbarui.',
            $summary['countries_processed'],
            $summary['api_success'],
            $summary['articles_inserted'],
            $summary['articles_updated'],
            $summary['risk_updated']
        );

        $this->recordApiLog(
            status: $status,
            responseCode: $lastHttpStatus,
            message: $message
        );

        if ($summary['api_success'] > 0) {
            $this->components->info(
                'Sinkronisasi berita selesai.'
            );
        } else {
            $this->components->error(
                'Tidak ada request berita yang berhasil.'
            );
        }

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                [
                    'Negara diproses',
                    $summary['countries_processed'],
                ],
                [
                    'Request API berhasil',
                    $summary['api_success'],
                ],
                [
                    'Request API gagal',
                    $summary['api_failed'],
                ],
                [
                    'Negara tanpa berita',
                    $summary['countries_without_news'],
                ],
                [
                    'Artikel ditemukan',
                    $summary['articles_found'],
                ],
                [
                    'Artikel baru',
                    $summary['articles_inserted'],
                ],
                [
                    'Artikel diperbarui',
                    $summary['articles_updated'],
                ],
                [
                    'Artikel gagal',
                    $summary['articles_failed'],
                ],
                [
                    'Risiko diperbarui',
                    $summary['risk_updated'],
                ],
                [
                    'Risiko gagal',
                    $summary['risk_failed'],
                ],
                [
                    'Total news_cache',
                    DB::table('news_cache')->count(),
                ],
            ]
        );

        if ($stopBecauseQuota) {
            $this->newLine();
            $this->components->warn(
                'Proses dihentikan karena kuota API habis atau akses ditolak. Coba lagi setelah kuota harian direset.'
            );
        }

        if ($summary['errors'] !== []) {
            $this->newLine();
            $this->components->warn(
                'Beberapa informasi proses:'
            );

            foreach (
                array_slice(
                    array_unique($summary['errors']),
                    0,
                    10
                ) as $error
            ) {
                $this->line('- ' . $error);
            }
        }

        $this->newLine();
        $this->line(
            'Berita yang tersimpan sudah dianalisis sentimennya secara otomatis.'
        );

        if (!$skipRisk) {
            $this->line(
                'Skor risiko negara yang memperoleh berita juga sudah diperbarui.'
            );
        }

        return $summary['api_success'] > 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Memilih negara berdasarkan opsi command.
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

            return $query->get();
        }

        $limit = max(
            1,
            min(90, (int) $this->option('limit'))
        );

        $offset = max(
            0,
            (int) $this->option('offset')
        );

        return $query
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Melakukan request berita satu negara.
     */
    private function requestCountryNews(
        object $country,
        string $apiKey,
        string $language,
        int $articleLimit,
        int $days
    ): Response {
        $baseUrl = rtrim(
            (string) config(
                'services.gnews.base_url',
                'https://gnews.io/api/v4'
            ),
            '/'
        );

        $safeCountryName = str_replace(
            '"',
            '',
            (string) $country->name
        );

        $query = sprintf(
            '"%s" AND (trade OR shipping OR logistics OR export OR import OR port OR economy)',
            $safeCountryName
        );

        $parameters = [
            'q' => $query,
            'lang' => $language,
            'max' => $articleLimit,
            'in' => 'title,description',
            'nullable' => 'description,image',
            'from' => now()
                ->utc()
                ->subDays($days)
                ->startOfDay()
                ->toIso8601ZuluString(),
            'to' => now()
                ->utc()
                ->toIso8601ZuluString(),
            'sortby' => 'publishedAt',
            'apikey' => $apiKey,
        ];

        $response = $this->httpClient()
            ->get(
                $baseUrl . '/search',
                $parameters
            );

        /*
         * Akun gratis dibatasi satu request per detik.
         * Jika masih terkena 429, tunggu dan coba satu kali lagi.
         */
        if ($response->status() === 429) {
            sleep(2);

            $response = $this->httpClient()
                ->get(
                    $baseUrl . '/search',
                    $parameters
                );
        }

        return $response;
    }

    /**
     * Menyimpan satu artikel dan menghitung sentimennya.
     */
    private function saveArticle(
        int $countryId,
        array $article,
        SentimentService $sentimentService
    ): string {
        $title = trim(
            (string) ($article['title'] ?? '')
        );

        if ($title === '') {
            throw new \RuntimeException(
                'Judul artikel kosong.'
            );
        }

        $description = isset($article['description'])
            ? trim((string) $article['description'])
            : null;

        $url = isset($article['url'])
            ? trim((string) $article['url'])
            : null;

        $imageUrl = isset($article['image'])
            ? trim((string) $article['image'])
            : null;

        $publishedAt = $this->parsePublishedAt(
            $article['publishedAt'] ?? null
        );

        $sourceName = trim(
            (string) data_get(
                $article,
                'source.name',
                'GNews'
            )
        );

        $sentiment = $sentimentService->analyzeNews(
            $title,
            $description
        );

        $storedTitle = $this->limitText(
            $title,
            255
        );

        $storedUrl = $url !== null && $url !== ''
            ? $this->limitText($url, 255)
            : null;

        $existingQuery = DB::table('news_cache')
            ->where('country_id', $countryId);

        if ($storedUrl !== null) {
            $existingQuery->where('url', $storedUrl);
        } else {
            $existingQuery
                ->where('title', $storedTitle)
                ->when(
                    $publishedAt !== null,
                    fn ($query) => $query->where(
                        'published_at',
                        $publishedAt
                    )
                );
        }

        $existingId = $existingQuery->value('id');

        $data = [
            'country_id' => $countryId,
            'title' => $storedTitle,
            'description' => $description !== ''
                ? $description
                : null,
            'source_name' => $sourceName !== ''
                ? $this->limitText($sourceName, 255)
                : 'GNews',
            'url' => $storedUrl,
            'image_url' => $imageUrl !== null
                && $imageUrl !== ''
                    ? $this->limitText($imageUrl, 255)
                    : null,
            'category' => $this->detectCategory(
                $title . ' ' . ($description ?? '')
            ),
            'sentiment' => $sentiment['sentiment'],
            'positive_score' =>
                $sentiment['positive_score'],
            'negative_score' =>
                $sentiment['negative_score'],
            'published_at' => $publishedAt,
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('news_cache')
                ->where('id', $existingId)
                ->update($data);

            return 'updated';
        }

        DB::table('news_cache')->insert(
            array_merge(
                $data,
                ['created_at' => now()]
            )
        );

        return 'inserted';
    }

    /**
     * Kategori sederhana berdasarkan kata kunci.
     */
    private function detectCategory(string $text): string
    {
        $text = mb_strtolower(
            $text,
            'UTF-8'
        );

        return match (true) {
            Str::contains(
                $text,
                [
                    'shipping',
                    'logistics',
                    'port',
                    'freight',
                    'cargo',
                    'container',
                ]
            ) => 'Logistics',

            Str::contains(
                $text,
                [
                    'trade',
                    'export',
                    'import',
                    'tariff',
                    'customs',
                ]
            ) => 'Trade',

            Str::contains(
                $text,
                [
                    'storm',
                    'rain',
                    'flood',
                    'weather',
                    'cyclone',
                    'typhoon',
                ]
            ) => 'Weather',

            Str::contains(
                $text,
                [
                    'economy',
                    'economic',
                    'inflation',
                    'gdp',
                    'currency',
                ]
            ) => 'Economy',

            default => 'Supply Chain',
        };
    }

    /**
     * Mengubah tanggal publikasi ke format database.
     */
    private function parsePublishedAt(
        mixed $publishedAt
    ): ?Carbon {
        if (
            !is_string($publishedAt)
            || trim($publishedAt) === ''
        ) {
            return null;
        }

        try {
            return Carbon::parse($publishedAt)
                ->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Menyiapkan HTTP client.
     */
    private function httpClient(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withUserAgent(
                'SupplyGuard/1.0 Laravel News Sync'
            )
            ->connectTimeout(15)
            ->timeout(45);

        if ((bool) $this->option('insecure')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * Jeda lebih dari satu detik untuk akun gratis GNews.
     */
    private function waitBeforeNextRequest(
        bool $hasNext
    ): void {
        if ($hasNext) {
            usleep(1200000);
        }
    }

    /**
     * Mengambil pesan kesalahan API.
     */
    private function apiErrorMessage(
        Response $response
    ): string {
        $payload = $response->json();
        $errors = is_array($payload)
            ? ($payload['errors'] ?? null)
            : null;

        if (is_string($errors)) {
            return $errors;
        }

        if (is_array($errors)) {
            return collect($errors)
                ->flatten()
                ->filter()
                ->implode('; ');
        }

        return 'Respons API tidak berhasil.';
    }

    /**
     * Membatasi panjang string sesuai kolom database.
     */
    private function limitText(
        string $value,
        int $limit
    ): string {
        return mb_substr(
            $value,
            0,
            $limit,
            'UTF-8'
        );
    }

    /**
     * Memastikan tabel utama tersedia.
     */
    private function requiredTablesExist(): bool
    {
        $requiredTables = [
            'countries',
            'news_cache',
            'positive_words',
            'negative_words',
            'risk_scores',
            'risk_histories',
            'api_logs',
        ];

        $missingTables = collect($requiredTables)
            ->reject(
                static fn (string $table): bool =>
                    Schema::hasTable($table)
            )
            ->values();

        if ($missingTables->isNotEmpty()) {
            $this->components->error(
                'Tabel berikut belum tersedia: ' .
                $missingTables->implode(', ')
            );

            return false;
        }

        return true;
    }

    /**
     * Mencatat ringkasan penggunaan API.
     */
    private function recordApiLog(
        string $status,
        ?int $responseCode,
        string $message
    ): void {
        try {
            DB::table('api_logs')->insert([
                'api_name' => 'GNews API',
                'endpoint' => rtrim(
                    (string) config(
                        'services.gnews.base_url',
                        'https://gnews.io/api/v4'
                    ),
                    '/'
                ) . '/search',
                'status' => $status,
                'response_code' => $responseCode,
                'message' => $message,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            /*
             * Kegagalan log tidak boleh menghentikan sinkronisasi.
             */
        }
    }
}
