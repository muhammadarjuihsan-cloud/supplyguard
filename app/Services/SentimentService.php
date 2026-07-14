<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class SentimentService
{
    /**
     * Kamus kata disimpan sementara selama satu request.
     */
    private ?array $lexicons = null;

    /**
     * Menganalisis sentimen sebuah teks menggunakan kamus
     * positive_words dan negative_words.
     *
     * @return array{
     *     sentiment: string,
     *     positive_score: int,
     *     negative_score: int,
     *     neutral_score: int,
     *     positive_percentage: float,
     *     negative_percentage: float,
     *     neutral_percentage: float,
     *     polarity_score: float,
     *     total_words: int,
     *     matched_words: int,
     *     positive_matches: array<int, string>,
     *     negative_matches: array<int, string>
     * }
     */
    public function analyze(?string $text): array
    {
        $normalizedText = $this->normalizeText($text);

        if ($normalizedText === '') {
            return $this->emptyResult();
        }

        $lexicons = $this->getLexicons();

        $positiveWords = $lexicons['positive'];
        $negativeWords = $lexicons['negative'];

        /*
         * Kata yang terdaftar di kedua kamus dianggap ambigu
         * sehingga tidak dihitung sebagai positif maupun negatif.
         */
        $ambiguousWords = array_intersect(
            array_keys($positiveWords),
            array_keys($negativeWords)
        );

        foreach ($ambiguousWords as $word) {
            unset(
                $positiveWords[$word],
                $negativeWords[$word]
            );
        }

        $tokens = $this->tokenize($normalizedText);

        $positiveScore = 0;
        $negativeScore = 0;
        $positiveMatches = [];
        $negativeMatches = [];

        foreach ($tokens as $token) {
            $variants = $this->tokenVariants($token);

            $positiveMatch = $this->firstMatchingVariant(
                $variants,
                $positiveWords
            );

            $negativeMatch = $this->firstMatchingVariant(
                $variants,
                $negativeWords
            );

            /*
             * Jika satu token cocok dengan kedua sisi setelah
             * normalisasi, token tersebut tidak memengaruhi hasil.
             */
            if (
                $positiveMatch !== null &&
                $negativeMatch !== null
            ) {
                continue;
            }

            if ($positiveMatch !== null) {
                $positiveScore++;
                $positiveMatches[] = $positiveMatch;

                continue;
            }

            if ($negativeMatch !== null) {
                $negativeScore++;
                $negativeMatches[] = $negativeMatch;
            }
        }

        $totalWords = count($tokens);
        $matchedWords = $positiveScore + $negativeScore;
        $neutralScore = max(
            0,
            $totalWords - $matchedWords
        );

        $sentiment = match (true) {
            $positiveScore > $negativeScore => 'Positive',
            $negativeScore > $positiveScore => 'Negative',
            default => 'Neutral',
        };

        $positivePercentage = $this->percentage(
            $positiveScore,
            $totalWords
        );

        $negativePercentage = $this->percentage(
            $negativeScore,
            $totalWords
        );

        $neutralPercentage = $this->percentage(
            $neutralScore,
            $totalWords
        );

        /*
         * Nilai polaritas:
         * -100 = sangat negatif
         *    0 = netral
         *  100 = sangat positif
         */
        $polarityScore = $matchedWords > 0
            ? round(
                (
                    ($positiveScore - $negativeScore) /
                    $matchedWords
                ) * 100,
                2
            )
            : 0.0;

        return [
            'sentiment' => $sentiment,
            'positive_score' => $positiveScore,
            'negative_score' => $negativeScore,
            'neutral_score' => $neutralScore,
            'positive_percentage' => $positivePercentage,
            'negative_percentage' => $negativePercentage,
            'neutral_percentage' => $neutralPercentage,
            'polarity_score' => $polarityScore,
            'total_words' => $totalWords,
            'matched_words' => $matchedWords,
            'positive_matches' => array_values(
                array_unique($positiveMatches)
            ),
            'negative_matches' => array_values(
                array_unique($negativeMatches)
            ),
        ];
    }

    /**
     * Menganalisis gabungan judul dan deskripsi berita.
     */
    public function analyzeNews(
        ?string $title,
        ?string $description
    ): array {
        $text = trim(
            implode(' ', array_filter([
                $title,
                $description,
            ]))
        );

        return $this->analyze($text);
    }

    /**
     * Menganalisis satu berita lalu memperbarui news_cache.
     */
    public function updateNewsById(int $newsId): array
    {
        if ($newsId <= 0) {
            throw new InvalidArgumentException(
                'ID berita harus lebih besar dari nol.'
            );
        }

        $news = DB::table('news_cache')
            ->where('id', $newsId)
            ->first();

        if (!$news) {
            throw new InvalidArgumentException(
                "Berita dengan ID {$newsId} tidak ditemukan."
            );
        }

        $result = $this->analyzeNews(
            $news->title ?? null,
            $news->description ?? null
        );

        DB::table('news_cache')
            ->where('id', $newsId)
            ->update([
                'sentiment' => $result['sentiment'],
                'positive_score' =>
                    $result['positive_score'],
                'negative_score' =>
                    $result['negative_score'],
                'updated_at' => now(),
            ]);

        return $result;
    }

    /**
     * Menganalisis seluruh berita atau berita per negara.
     *
     * @return array{
     *     processed: int,
     *     updated: int,
     *     failed: int,
     *     positive: int,
     *     neutral: int,
     *     negative: int,
     *     errors: array<int, string>
     * }
     */
    public function updateAllNews(
        ?int $countryId = null
    ): array {
        $query = DB::table('news_cache')
            ->select(
                'id',
                'country_id',
                'title',
                'description'
            )
            ->orderBy('id');

        if ($countryId !== null) {
            $query->where('country_id', $countryId);
        }

        $summary = [
            'processed' => 0,
            'updated' => 0,
            'failed' => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'errors' => [],
        ];

        $query->chunkById(
            100,
            function ($newsItems) use (&$summary): void {
                foreach ($newsItems as $news) {
                    $summary['processed']++;

                    try {
                        $result = $this->analyzeNews(
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
                }
            },
            'id'
        );

        return $summary;
    }

    /**
     * Memuat kamus dari database.
     */
    public function getLexicons(
        bool $refresh = false
    ): array {
        if (!$refresh && $this->lexicons !== null) {
            return $this->lexicons;
        }

        if (
            !Schema::hasTable('positive_words') ||
            !Schema::hasTable('negative_words')
        ) {
            throw new InvalidArgumentException(
                'Tabel kamus sentimen belum tersedia.'
            );
        }

        $positiveWords = DB::table('positive_words')
            ->whereNotNull('word')
            ->pluck('word')
            ->map(
                fn ($word): string =>
                    $this->normalizeLexiconWord(
                        (string) $word
                    )
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $negativeWords = DB::table('negative_words')
            ->whereNotNull('word')
            ->pluck('word')
            ->map(
                fn ($word): string =>
                    $this->normalizeLexiconWord(
                        (string) $word
                    )
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->lexicons = [
            'positive' => array_fill_keys(
                $positiveWords,
                true
            ),
            'negative' => array_fill_keys(
                $negativeWords,
                true
            ),
        ];

        return $this->lexicons;
    }

    /**
     * Memaksa service membaca ulang kamus dari database.
     */
    public function refreshLexicons(): void
    {
        $this->lexicons = null;
        $this->getLexicons(refresh: true);
    }

    /**
     * Menormalisasi teks agar mudah dibandingkan dengan kamus.
     */
    private function normalizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = mb_strtolower(
            trim($text),
            'UTF-8'
        );

        if ($text === '') {
            return '';
        }

        /*
         * Tanda hubung dan garis bawah diperlakukan sebagai
         * pemisah kata.
         */
        $text = str_replace(
            ['-', '_', '/', '\\'],
            ' ',
            $text
        );

        /*
         * Hanya pertahankan huruf, angka, apostrof,
         * dan spasi agar teks tetap aman untuk UTF-8.
         */
        $text = preg_replace(
            "/[^\\p{L}\\p{N}'\\s]+/u",
            ' ',
            $text
        ) ?? '';

        return preg_replace(
            '/\s+/u',
            ' ',
            trim($text)
        ) ?? '';
    }

    /**
     * Mengubah teks menjadi daftar token.
     */
    private function tokenize(
        string $normalizedText
    ): array {
        if ($normalizedText === '') {
            return [];
        }

        $tokens = preg_split(
            '/\s+/u',
            $normalizedText,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($tokens)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (string $token): string =>
                        trim($token, "'"),
                    $tokens
                ),
                static fn (string $token): bool =>
                    $token !== ''
            )
        );
    }

    /**
     * Membuat variasi sederhana agar bentuk seperti
     * increases, improved, improving, dan delays tetap
     * dapat cocok dengan kata dasar dalam kamus.
     */
    private function tokenVariants(
        string $token
    ): array {
        $variants = [$token];

        $length = mb_strlen($token, 'UTF-8');

        if (
            $length > 3 &&
            str_ends_with($token, 's')
        ) {
            $variants[] = mb_substr(
                $token,
                0,
                $length - 1,
                'UTF-8'
            );
        }

        if (
            $length > 4 &&
            str_ends_with($token, 'ed')
        ) {
            /*
             * improved -> improve
             */
            $variants[] = mb_substr(
                $token,
                0,
                $length - 1,
                'UTF-8'
            );

            /*
             * delayed -> delay
             */
            $variants[] = mb_substr(
                $token,
                0,
                $length - 2,
                'UTF-8'
            );
        }

        if (
            $length > 5 &&
            str_ends_with($token, 'ing')
        ) {
            $stem = mb_substr(
                $token,
                0,
                $length - 3,
                'UTF-8'
            );

            $variants[] = $stem;
            $variants[] = $stem . 'e';
        }

        if (
            $length > 4 &&
            str_ends_with($token, 'ies')
        ) {
            $variants[] = mb_substr(
                $token,
                0,
                $length - 3,
                'UTF-8'
            ) . 'y';
        }

        return array_values(
            array_unique(
                array_filter($variants)
            )
        );
    }

    /**
     * Mencari varian token pertama yang terdapat di kamus.
     */
    private function firstMatchingVariant(
        array $variants,
        array $lexicon
    ): ?string {
        foreach ($variants as $variant) {
            if (isset($lexicon[$variant])) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Menormalisasi satu kata dari kamus.
     */
    private function normalizeLexiconWord(
        string $word
    ): string {
        return $this->normalizeText($word);
    }

    /**
     * Menghitung persentase dengan aman.
     */
    private function percentage(
        int $value,
        int $total
    ): float {
        if ($total <= 0) {
            return 0.0;
        }

        return round(
            ($value / $total) * 100,
            2
        );
    }

    /**
     * Hasil default untuk teks kosong.
     */
    private function emptyResult(): array
    {
        return [
            'sentiment' => 'Neutral',
            'positive_score' => 0,
            'negative_score' => 0,
            'neutral_score' => 0,
            'positive_percentage' => 0.0,
            'negative_percentage' => 0.0,
            'neutral_percentage' => 0.0,
            'polarity_score' => 0.0,
            'total_words' => 0,
            'matched_words' => 0,
            'positive_matches' => [],
            'negative_matches' => [],
        ];
    }
}
