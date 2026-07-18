<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class RiskScoringService
{
    /**
     * Bobot resmi Risk Scoring Engine SupplyGuard.
     */
    private const WEIGHTS = [
        'weather' => 0.25,
        'inflation' => 0.25,
        'currency' => 0.20,
        'news' => 0.20,
        'port' => 0.10,
    ];

    /**
     * Skor netral digunakan ketika suatu sumber data belum tersedia.
     *
     * Nilai 50 mencegah data kosong dianggap otomatis aman
     * atau otomatis berisiko tinggi.
     */
    private const NEUTRAL_SCORE = 50;

    /**
     * Menghitung risiko satu negara dan menyimpan hasilnya.
     *
     * @return array{
     *     country_id: int,
     *     country_name: string,
     *     weather_score: int,
     *     inflation_score: int,
     *     currency_score: int,
     *     news_score: int,
     *     port_score: int,
     *     total_score: int,
     *     risk_level: string,
     *     recommendation: string,
     *     score_date: string,
     *     available_components: int,
     *     total_components: int,
     *     data_completion_percentage: int,
     *     missing_components: array<int, string>
     * }
     */
    public function calculateCountry(
        int $countryId,
        bool $save = true
    ): array {
        $this->ensureRequiredTablesExist();

        if ($countryId <= 0) {
            throw new InvalidArgumentException(
                'ID negara harus lebih besar dari nol.'
            );
        }

        $country = DB::table('countries')
            ->where('id', $countryId)
            ->first();

        if (!$country) {
            throw new InvalidArgumentException(
                "Negara dengan ID {$countryId} tidak ditemukan."
            );
        }

        $weather = DB::table('weather_cache')
            ->where('country_id', $countryId)
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->first();

        $economy = DB::table('economic_indicators')
            ->where('country_id', $countryId)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->first();

        $currency = DB::table('currency_rates')
            ->where('country_id', $countryId)
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->first();

        $newsItems = DB::table('news_cache')
            ->where('country_id', $countryId)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $portCount = DB::table('ports')
            ->where('country_id', $countryId)
            ->count();

        $availability = [
            'weather' => $weather !== null,
            'inflation' => $economy !== null
                && $economy->inflation !== null,
            'currency' => $currency !== null
                && $currency->currency_risk !== null,
            'news' => $newsItems->isNotEmpty(),
            'port' => $portCount > 0,
        ];

        $scores = [
            'weather' => $availability['weather']
                ? $this->clampScore(
                    $weather->weather_risk ?? self::NEUTRAL_SCORE
                )
                : self::NEUTRAL_SCORE,

            'inflation' => $availability['inflation']
                ? $this->inflationScore(
                    (float) $economy->inflation
                )
                : self::NEUTRAL_SCORE,

            'currency' => $availability['currency']
                ? $this->clampScore(
                    $currency->currency_risk
                )
                : self::NEUTRAL_SCORE,

            'news' => $availability['news']
                ? $this->newsScore($newsItems)
                : self::NEUTRAL_SCORE,

            'port' => $availability['port']
                ? $this->portScore($portCount)
                : self::NEUTRAL_SCORE,
        ];

        $totalScore = $this->weightedTotal($scores);
        $riskLevel = $this->riskLevel($totalScore);

        $missingComponents = collect($availability)
            ->filter(
                static fn (bool $available): bool =>
                    !$available
            )
            ->keys()
            ->map(
                fn (string $key): string =>
                    $this->componentLabel($key)
            )
            ->values()
            ->all();

        $availableComponents = collect($availability)
            ->filter()
            ->count();

        $totalComponents = count($availability);

        $dataCompletionPercentage = $totalComponents > 0
            ? (int) round(
                ($availableComponents / $totalComponents) * 100
            )
            : 0;

        $recommendation = $this->buildRecommendation(
            countryName: (string) $country->name,
            totalScore: $totalScore,
            riskLevel: $riskLevel,
            scores: $scores,
            missingComponents: $missingComponents
        );

        $result = [
            'country_id' => (int) $country->id,
            'country_name' => (string) $country->name,
            'weather_score' => $scores['weather'],
            'inflation_score' => $scores['inflation'],
            'currency_score' => $scores['currency'],
            'news_score' => $scores['news'],
            'port_score' => $scores['port'],
            'total_score' => $totalScore,
            'risk_level' => $riskLevel,
            'recommendation' => $recommendation,
            'score_date' => now()->toDateString(),
            'available_components' => $availableComponents,
            'total_components' => $totalComponents,
            'data_completion_percentage' =>
                $dataCompletionPercentage,
            'missing_components' => $missingComponents,
        ];

        if ($save) {
            $this->saveResult($result);
        }

        return $result;
    }

    /**
     * Menghitung risiko negara berdasarkan ID, CCA2, CCA3,
     * atau nama negara.
     */
    public function calculateCountryByCode(
        string $countryIdentifier,
        bool $save = true
    ): array {
        $countryIdentifier = trim($countryIdentifier);

        if ($countryIdentifier === '') {
            throw new InvalidArgumentException(
                'Identitas negara tidak boleh kosong.'
            );
        }

        $country = DB::table('countries')
            ->where(function ($query) use (
                $countryIdentifier
            ): void {
                if (ctype_digit($countryIdentifier)) {
                    $query->orWhere(
                        'id',
                        (int) $countryIdentifier
                    );
                }

                $upperValue = strtoupper(
                    $countryIdentifier
                );

                $query
                    ->orWhere('cca2', $upperValue)
                    ->orWhere('cca3', $upperValue)
                    ->orWhereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower($countryIdentifier)]
                    );
            })
            ->first();

        if (!$country) {
            throw new InvalidArgumentException(
                "Negara '{$countryIdentifier}' tidak ditemukan."
            );
        }

        return $this->calculateCountry(
            countryId: (int) $country->id,
            save: $save
        );
    }

    /**
     * Menghitung seluruh negara.
     *
     * @return array{
     *     processed: int,
     *     saved: int,
     *     failed: int,
     *     low: int,
     *     medium: int,
     *     high: int,
     *     errors: array<int, string>
     * }
     */
    public function calculateAll(
        ?callable $onProgress = null
    ): array {
        $this->ensureRequiredTablesExist();

        $summary = [
            'processed' => 0,
            'saved' => 0,
            'failed' => 0,
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'errors' => [],
        ];

        DB::table('countries')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($countries) use (
                    &$summary,
                    $onProgress
                ): void {
                    foreach ($countries as $country) {
                        $summary['processed']++;

                        try {
                            $result = $this->calculateCountry(
                                countryId: (int) $country->id,
                                save: true
                            );

                            $summary['saved']++;

                            $levelKey = strtolower(
                                $result['risk_level']
                            );

                            if (
                                array_key_exists(
                                    $levelKey,
                                    $summary
                                )
                            ) {
                                $summary[$levelKey]++;
                            }
                        } catch (Throwable $exception) {
                            $summary['failed']++;

                            if (
                                count($summary['errors']) < 10
                            ) {
                                $summary['errors'][] = sprintf(
                                    '%s: %s',
                                    $country->name,
                                    $exception->getMessage()
                                );
                            }
                        }

                        if ($onProgress !== null) {
                            $onProgress(
                                $summary['processed'],
                                $country
                            );
                        }
                    }
                },
                'id'
            );

        return $summary;
    }

    /**
     * Menghitung skor inflasi 0-100.
     *
     * Inflasi moderat memperoleh skor rendah.
     * Deflasi dan inflasi sangat tinggi memperoleh skor lebih tinggi.
     */
    public function inflationScore(float $inflation): int
    {
        return match (true) {
            $inflation < -3 => 85,
            $inflation < 0 => 45,
            $inflation <= 2.5 => 15,
            $inflation <= 5 => 35,
            $inflation <= 8 => 55,
            $inflation <= 12 => 75,
            $inflation <= 20 => 90,
            default => 100,
        };
    }

    /**
     * Mengubah kumpulan berita menjadi skor risiko 0-100.
     */
    public function newsScore(Collection $newsItems): int
    {
        if ($newsItems->isEmpty()) {
            return self::NEUTRAL_SCORE;
        }

        $articleScores = $newsItems
            ->map(function ($news): int {
                $positiveScore = max(
                    0,
                    (int) ($news->positive_score ?? 0)
                );

                $negativeScore = max(
                    0,
                    (int) ($news->negative_score ?? 0)
                );

                $matchedWords =
                    $positiveScore + $negativeScore;

                if ($matchedWords > 0) {
                    return $this->clampScore(
                        ($negativeScore / $matchedWords) * 100
                    );
                }

                return match (
                    strtolower(
                        (string) ($news->sentiment ?? 'neutral')
                    )
                ) {
                    'positive' => 20,
                    'negative' => 80,
                    default => self::NEUTRAL_SCORE,
                };
            })
            ->values();

        return $this->clampScore(
            $articleScores->average()
        );
    }

    /**
     * Mengubah jumlah pelabuhan menjadi skor risiko.
     *
     * Semakin banyak pelabuhan yang tersedia, semakin rendah
     * risiko ketersediaan logistik.
     */
    public function portScore(int $portCount): int
    {
        return match (true) {
            $portCount >= 10 => 5,
            $portCount >= 5 => 15,
            $portCount >= 3 => 25,
            $portCount === 2 => 40,
            $portCount === 1 => 60,
            default => self::NEUTRAL_SCORE,
        };
    }

    /**
     * Menghitung total berbobot.
     */
    private function weightedTotal(array $scores): int
    {
        $total =
            ($scores['weather'] * self::WEIGHTS['weather']) +
            ($scores['inflation'] * self::WEIGHTS['inflation']) +
            ($scores['currency'] * self::WEIGHTS['currency']) +
            ($scores['news'] * self::WEIGHTS['news']) +
            ($scores['port'] * self::WEIGHTS['port']);

        return $this->clampScore($total);
    }

    /**
     * Menentukan level risiko berdasarkan skor total.
     */
    private function riskLevel(int $totalScore): string
    {
        return match (true) {
            $totalScore <= 30 => 'Low',
            $totalScore <= 60 => 'Medium',
            default => 'High',
        };
    }

    /**
     * Menyimpan satu skor terbaru per negara dan riwayat harian.
     *
     * Tabel risk_scores hanya menyimpan kondisi terkini.
     * Tabel risk_histories menyimpan satu riwayat per negara per tanggal.
     * Duplikat lama pada risk_scores dibersihkan otomatis saat negara dihitung.
     */
    private function saveResult(array $result): void
    {
        DB::transaction(function () use ($result): void {
            $riskData = [
                'weather_score' =>
                    $result['weather_score'],
                'inflation_score' =>
                    $result['inflation_score'],
                'currency_score' =>
                    $result['currency_score'],
                'news_score' =>
                    $result['news_score'],
                'port_score' =>
                    $result['port_score'],
                'total_score' =>
                    $result['total_score'],
                'risk_level' =>
                    $result['risk_level'],
                'recommendation' =>
                    $result['recommendation'],
                'score_date' =>
                    $result['score_date'],
                'updated_at' => now(),
            ];

            /*
             * risk_scores hanya boleh memiliki satu baris per negara.
             * Ambil data terbaru sebagai baris utama, lalu hapus duplikat lama.
             */
            $riskIds = DB::table('risk_scores')
                ->where(
                    'country_id',
                    $result['country_id']
                )
                ->orderByDesc('score_date')
                ->orderByDesc('id')
                ->pluck('id');

            $currentRiskId = $riskIds->first();

            if ($currentRiskId !== null) {
                DB::table('risk_scores')
                    ->where('id', $currentRiskId)
                    ->update($riskData);

                $duplicateRiskIds = $riskIds
                    ->slice(1)
                    ->values()
                    ->all();

                if ($duplicateRiskIds !== []) {
                    DB::table('risk_scores')
                        ->whereIn('id', $duplicateRiskIds)
                        ->delete();
                }
            } else {
                DB::table('risk_scores')->insert(
                    array_merge(
                        [
                            'country_id' =>
                                $result['country_id'],
                            'created_at' => now(),
                        ],
                        $riskData
                    )
                );
            }

            /*
             * Riwayat tetap disimpan per negara per tanggal.
             * Jika proses dijalankan ulang pada hari yang sama,
             * riwayat hari tersebut cukup diperbarui.
             */
            $historyData = [
                'total_score' =>
                    $result['total_score'],
                'risk_level' =>
                    $result['risk_level'],
                'recorded_date' =>
                    $result['score_date'],
                'updated_at' => now(),
            ];

            $historyIds = DB::table('risk_histories')
                ->where(
                    'country_id',
                    $result['country_id']
                )
                ->where(
                    'recorded_date',
                    $result['score_date']
                )
                ->orderByDesc('id')
                ->pluck('id');

            $currentHistoryId = $historyIds->first();

            if ($currentHistoryId !== null) {
                DB::table('risk_histories')
                    ->where('id', $currentHistoryId)
                    ->update($historyData);

                $duplicateHistoryIds = $historyIds
                    ->slice(1)
                    ->values()
                    ->all();

                if ($duplicateHistoryIds !== []) {
                    DB::table('risk_histories')
                        ->whereIn('id', $duplicateHistoryIds)
                        ->delete();
                }
            } else {
                DB::table('risk_histories')->insert(
                    array_merge(
                        [
                            'country_id' =>
                                $result['country_id'],
                            'created_at' => now(),
                        ],
                        $historyData
                    )
                );
            }
        });
    }

    /**
     * Membuat rekomendasi berdasarkan komponen risiko tertinggi.
     */
    private function buildRecommendation(
        string $countryName,
        int $totalScore,
        string $riskLevel,
        array $scores,
        array $missingComponents
    ): string {
        $sortedScores = collect($scores)
            ->sortDesc();

        $highestKey = (string) $sortedScores
            ->keys()
            ->first();

        $highestScore = (int) $sortedScores
            ->first();

        $mainRecommendation = match ($highestKey) {
            'weather' =>
                'Pantau prakiraan cuaca dan siapkan rute logistik alternatif.',
            'inflation' =>
                'Evaluasi biaya pengadaan, kontrak pemasok, dan tekanan harga.',
            'currency' =>
                'Pantau volatilitas kurs dan pertimbangkan strategi lindung nilai.',
            'news' =>
                'Tinjau perkembangan berita dan gangguan geopolitik secara berkala.',
            'port' =>
                'Evaluasi kapasitas pelabuhan dan siapkan jalur distribusi alternatif.',
            default =>
                'Lakukan pemantauan rutin terhadap seluruh indikator.',
        };

        $levelMessage = match ($riskLevel) {
            'Low' =>
                "Risiko rantai pasok {$countryName} tergolong rendah ({$totalScore}/100).",
            'Medium' =>
                "Risiko rantai pasok {$countryName} tergolong sedang ({$totalScore}/100).",
            default =>
                "Risiko rantai pasok {$countryName} tergolong tinggi ({$totalScore}/100).",
        };

        $recommendation = sprintf(
            '%s Komponen tertinggi adalah %s (%d/100). %s',
            $levelMessage,
            $this->componentLabel($highestKey),
            $highestScore,
            $mainRecommendation
        );

        if ($missingComponents !== []) {
            $recommendation .= sprintf(
                ' Data %s belum tersedia; sistem menggunakan skor netral 50 agar hasil tidak dianggap otomatis aman atau berisiko tinggi.',
                implode(', ', $missingComponents)
            );
        }

        return $recommendation;
    }

    /**
     * Membatasi skor pada rentang 0-100.
     */
    private function clampScore(mixed $score): int
    {
        if (!is_numeric($score)) {
            return self::NEUTRAL_SCORE;
        }

        return (int) round(
            min(100, max(0, (float) $score))
        );
    }

    /**
     * Nama komponen dalam Bahasa Indonesia.
     */
    private function componentLabel(string $key): string
    {
        return match ($key) {
            'weather' => 'Cuaca',
            'inflation' => 'Inflasi',
            'currency' => 'Mata uang',
            'news' => 'Sentimen berita',
            'port' => 'Ketersediaan pelabuhan',
            default => ucfirst($key),
        };
    }

    /**
     * Memastikan seluruh tabel yang dibutuhkan tersedia.
     */
    private function ensureRequiredTablesExist(): void
    {
        $requiredTables = [
            'countries',
            'weather_cache',
            'economic_indicators',
            'currency_rates',
            'news_cache',
            'ports',
            'risk_scores',
            'risk_histories',
        ];

        $missingTables = collect($requiredTables)
            ->reject(
                static fn (string $table): bool =>
                    Schema::hasTable($table)
            )
            ->values();

        if ($missingTables->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Tabel berikut belum tersedia: ' .
                $missingTables->implode(', ')
            );
        }
    }
}
