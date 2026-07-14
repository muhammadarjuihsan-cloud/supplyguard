<?php

namespace App\Console\Commands;

use App\Services\SentimentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class AnalyzeSentiment extends Command
{
    /**
     * Nama perintah Artisan.
     */
    protected $signature = 'supplyguard:analyze-sentiment
                            {--country= : ID, CCA2, CCA3, atau nama negara}
                            {--news= : Analisis satu berita berdasarkan ID}
                            {--refresh-lexicon : Muat ulang kamus sentimen sebelum analisis}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Menganalisis sentimen berita secara otomatis menggunakan kamus positif dan negatif';

    /**
     * Menjalankan perintah.
     */
    public function handle(
        SentimentService $sentimentService
    ): int {
        $this->newLine();
        $this->components->info(
            'Analisis sentimen SupplyGuard dimulai.'
        );

        if (!Schema::hasTable('news_cache')) {
            $this->components->error(
                'Tabel news_cache belum tersedia.'
            );

            return self::FAILURE;
        }

        if (
            !Schema::hasTable('positive_words') ||
            !Schema::hasTable('negative_words')
        ) {
            $this->components->error(
                'Tabel kamus sentimen belum tersedia.'
            );

            return self::FAILURE;
        }

        $positiveWordCount = DB::table(
            'positive_words'
        )->count();

        $negativeWordCount = DB::table(
            'negative_words'
        )->count();

        if (
            $positiveWordCount === 0 &&
            $negativeWordCount === 0
        ) {
            $this->components->error(
                'Kamus sentimen masih kosong.'
            );

            return self::FAILURE;
        }

        if ((bool) $this->option('refresh-lexicon')) {
            $sentimentService->refreshLexicons();

            $this->components->info(
                'Kamus sentimen berhasil dimuat ulang.'
            );
        }

        $this->line(sprintf(
            'Kamus aktif: %d kata positif dan %d kata negatif.',
            $positiveWordCount,
            $negativeWordCount
        ));

        $newsOption = trim(
            (string) $this->option('news')
        );

        if ($newsOption !== '') {
            return $this->analyzeSingleNews(
                sentimentService: $sentimentService,
                newsOption: $newsOption
            );
        }

        $countryId = $this->resolveCountryId();

        if ($countryId === false) {
            return self::FAILURE;
        }

        $query = DB::table('news_cache');

        if (is_int($countryId)) {
            $query->where('country_id', $countryId);
        }

        $newsCount = $query->count();

        if ($newsCount === 0) {
            $this->components->warn(
                is_int($countryId)
                    ? 'Belum ada berita untuk negara yang dipilih.'
                    : 'Tabel news_cache belum memiliki berita.'
            );

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Berita yang akan dianalisis: %d',
            $newsCount
        ));

        $progressBar = $this->output->createProgressBar(
            $newsCount
        );

        $progressBar->start();

        $summary = [
            'processed' => 0,
            'updated' => 0,
            'failed' => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'errors' => [],
        ];

        DB::table('news_cache')
            ->select(
                'id',
                'country_id',
                'title',
                'description'
            )
            ->when(
                is_int($countryId),
                fn ($query) => $query->where(
                    'country_id',
                    $countryId
                )
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($newsItems) use (
                    $sentimentService,
                    &$summary,
                    $progressBar
                ): void {
                    foreach ($newsItems as $news) {
                        $summary['processed']++;

                        try {
                            $result = $sentimentService
                                ->analyzeNews(
                                    $news->title ?? null,
                                    $news->description ?? null
                                );

                            DB::table('news_cache')
                                ->where('id', $news->id)
                                ->update([
                                    'sentiment' =>
                                        $result['sentiment'],
                                    'positive_score' =>
                                        $result['positive_score'],
                                    'negative_score' =>
                                        $result['negative_score'],
                                    'updated_at' => now(),
                                ]);

                            $summary['updated']++;

                            $sentimentKey = strtolower(
                                $result['sentiment']
                            );

                            if (
                                array_key_exists(
                                    $sentimentKey,
                                    $summary
                                )
                            ) {
                                $summary[$sentimentKey]++;
                            }
                        } catch (Throwable $exception) {
                            $summary['failed']++;

                            if (
                                count($summary['errors']) < 10
                            ) {
                                $summary['errors'][] = sprintf(
                                    'Berita ID %d: %s',
                                    $news->id,
                                    $exception->getMessage()
                                );
                            }
                        }

                        $progressBar->advance();
                    }
                },
                'id'
            );

        $progressBar->finish();
        $this->newLine(2);

        $this->components->info(
            'Analisis sentimen selesai.'
        );

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Berita diproses', $summary['processed']],
                ['Berita diperbarui', $summary['updated']],
                ['Berita gagal', $summary['failed']],
                ['Sentimen positif', $summary['positive']],
                ['Sentimen netral', $summary['neutral']],
                ['Sentimen negatif', $summary['negative']],
            ]
        );

        if ($summary['errors'] !== []) {
            $this->newLine();
            $this->components->warn(
                'Beberapa kesalahan yang ditemukan:'
            );

            foreach ($summary['errors'] as $error) {
                $this->line('- ' . $error);
            }
        }

        $this->newLine();
        $this->line(
            'Buka dashboard untuk melihat hasil sentimen berita.'
        );

        return $summary['failed'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Menganalisis satu berita.
     */
    private function analyzeSingleNews(
        SentimentService $sentimentService,
        string $newsOption
    ): int {
        if (!ctype_digit($newsOption)) {
            $this->components->error(
                'Opsi --news harus berupa ID berita.'
            );

            return self::FAILURE;
        }

        $newsId = (int) $newsOption;

        try {
            $result = $sentimentService
                ->updateNewsById($newsId);
        } catch (InvalidArgumentException $exception) {
            $this->components->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(
                'Analisis berita gagal.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            "Berita ID {$newsId} berhasil dianalisis."
        );

        $this->table(
            ['Keterangan', 'Hasil'],
            [
                ['Sentimen', $result['sentiment']],
                [
                    'Skor positif',
                    $result['positive_score'],
                ],
                [
                    'Skor negatif',
                    $result['negative_score'],
                ],
                [
                    'Skor polaritas',
                    $result['polarity_score'],
                ],
                [
                    'Kata positif',
                    implode(
                        ', ',
                        $result['positive_matches']
                    ) ?: '-',
                ],
                [
                    'Kata negatif',
                    implode(
                        ', ',
                        $result['negative_matches']
                    ) ?: '-',
                ],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Menentukan ID negara dari opsi --country.
     *
     * @return int|null|false
     */
    private function resolveCountryId(): int|null|false
    {
        $countryOption = trim(
            (string) $this->option('country')
        );

        if ($countryOption === '') {
            return null;
        }

        $query = DB::table('countries');

        if (ctype_digit($countryOption)) {
            $query->where(
                'id',
                (int) $countryOption
            );
        } else {
            $upperValue = strtoupper(
                $countryOption
            );

            $query->where(function ($innerQuery) use (
                $upperValue,
                $countryOption
            ): void {
                $innerQuery
                    ->where('cca2', $upperValue)
                    ->orWhere('cca3', $upperValue)
                    ->orWhereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower($countryOption)]
                    );
            });
        }

        $country = $query->first();

        if (!$country) {
            $this->components->error(
                "Negara '{$countryOption}' tidak ditemukan."
            );

            return false;
        }

        $this->line(
            'Negara: ' . $country->name
        );

        return (int) $country->id;
    }
}
