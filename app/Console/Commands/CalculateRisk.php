<?php

namespace App\Console\Commands;

use App\Services\RiskScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class CalculateRisk extends Command
{
    /**
     * Nama perintah Artisan.
     */
    protected $signature = 'supplyguard:calculate-risk
                            {--country= : ID, CCA2, CCA3, atau nama negara}
                            {--no-save : Hitung tanpa menyimpan ke risk_scores dan risk_histories}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Menghitung skor risiko rantai pasok satu negara atau seluruh negara';

    /**
     * Menjalankan command.
     */
    public function handle(
        RiskScoringService $riskScoringService
    ): int {
        $this->newLine();
        $this->components->info(
            'Perhitungan risiko SupplyGuard dimulai.'
        );

        $countryOption = trim(
            (string) $this->option('country')
        );

        $save = !(bool) $this->option('no-save');

        if (!$save) {
            $this->components->warn(
                'Mode tanpa penyimpanan aktif.'
            );
        }

        if ($countryOption !== '') {
            return $this->calculateSingleCountry(
                riskScoringService: $riskScoringService,
                countryIdentifier: $countryOption,
                save: $save
            );
        }

        return $this->calculateAllCountries(
            riskScoringService: $riskScoringService,
            save: $save
        );
    }

    /**
     * Menghitung satu negara.
     */
    private function calculateSingleCountry(
        RiskScoringService $riskScoringService,
        string $countryIdentifier,
        bool $save
    ): int {
        try {
            $result = $riskScoringService
                ->calculateCountryByCode(
                    countryIdentifier: $countryIdentifier,
                    save: $save
                );
        } catch (InvalidArgumentException $exception) {
            $this->components->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(
                'Perhitungan risiko gagal.'
            );

            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            'Perhitungan risiko berhasil.'
        );

        $this->table(
            ['Komponen', 'Bobot', 'Skor'],
            [
                [
                    'Cuaca',
                    '25%',
                    $result['weather_score'],
                ],
                [
                    'Inflasi',
                    '25%',
                    $result['inflation_score'],
                ],
                [
                    'Mata uang',
                    '20%',
                    $result['currency_score'],
                ],
                [
                    'Sentimen berita',
                    '20%',
                    $result['news_score'],
                ],
                [
                    'Ketersediaan pelabuhan',
                    '10%',
                    $result['port_score'],
                ],
            ]
        );

        $this->newLine();

        $this->table(
            ['Keterangan', 'Hasil'],
            [
                [
                    'Negara',
                    $result['country_name'],
                ],
                [
                    'Skor total',
                    $result['total_score'] . '/100',
                ],
                [
                    'Level risiko',
                    $result['risk_level'],
                ],
                [
                    'Kelengkapan data',
                    sprintf(
                        '%d%% (%d/%d komponen)',
                        $result[
                            'data_completion_percentage'
                        ],
                        $result['available_components'],
                        $result['total_components']
                    ),
                ],
                [
                    'Komponen belum tersedia',
                    $result['missing_components'] !== []
                        ? implode(
                            ', ',
                            $result['missing_components']
                        )
                        : '-',
                ],
                [
                    'Tanggal skor',
                    $result['score_date'],
                ],
                [
                    'Status penyimpanan',
                    $save
                        ? 'Disimpan'
                        : 'Tidak disimpan',
                ],
            ]
        );

        $this->newLine();
        $this->line(
            '<fg=yellow>Rekomendasi:</> ' .
            $result['recommendation']
        );

        return self::SUCCESS;
    }

    /**
     * Menghitung seluruh negara.
     */
    private function calculateAllCountries(
        RiskScoringService $riskScoringService,
        bool $save
    ): int {
        $countryCount = DB::table('countries')->count();

        if ($countryCount === 0) {
            $this->components->warn(
                'Tabel countries belum memiliki data.'
            );

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Negara yang akan dihitung: %d',
            $countryCount
        ));

        $progressBar = $this->output->createProgressBar(
            $countryCount
        );

        $progressBar->start();

        $summary = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'complete_data' => 0,
            'partial_data' => 0,
            'errors' => [],
        ];

        $highestRisks = [];

        DB::table('countries')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($countries) use (
                    $riskScoringService,
                    $save,
                    &$summary,
                    &$highestRisks,
                    $progressBar
                ): void {
                    foreach ($countries as $country) {
                        $summary['processed']++;

                        try {
                            $result = $riskScoringService
                                ->calculateCountry(
                                    countryId:
                                        (int) $country->id,
                                    save: $save
                                );

                            $summary['success']++;

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

                            if (
                                $result[
                                    'data_completion_percentage'
                                ] === 100
                            ) {
                                $summary['complete_data']++;
                            } else {
                                $summary['partial_data']++;
                            }

                            $highestRisks[] = [
                                'country' =>
                                    $result['country_name'],
                                'score' =>
                                    $result['total_score'],
                                'level' =>
                                    $result['risk_level'],
                                'completion' =>
                                    $result[
                                        'data_completion_percentage'
                                    ],
                            ];
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

                        $progressBar->advance();
                    }
                },
                'id'
            );

        $progressBar->finish();
        $this->newLine(2);

        if ($summary['success'] > 0) {
            $this->components->info(
                'Perhitungan risiko seluruh negara selesai.'
            );
        } else {
            $this->components->error(
                'Tidak ada negara yang berhasil dihitung.'
            );
        }

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                [
                    'Negara diproses',
                    $summary['processed'],
                ],
                [
                    'Berhasil dihitung',
                    $summary['success'],
                ],
                [
                    'Gagal dihitung',
                    $summary['failed'],
                ],
                [
                    'Risiko rendah',
                    $summary['low'],
                ],
                [
                    'Risiko sedang',
                    $summary['medium'],
                ],
                [
                    'Risiko tinggi',
                    $summary['high'],
                ],
                [
                    'Data lengkap',
                    $summary['complete_data'],
                ],
                [
                    'Data sebagian',
                    $summary['partial_data'],
                ],
                [
                    'Status penyimpanan',
                    $save
                        ? 'Disimpan'
                        : 'Tidak disimpan',
                ],
            ]
        );

        if ($highestRisks !== []) {
            usort(
                $highestRisks,
                static fn (
                    array $first,
                    array $second
                ): int =>
                    $second['score'] <=> $first['score']
            );

            $topRisks = array_slice(
                $highestRisks,
                0,
                10
            );

            $this->newLine();
            $this->components->info(
                '10 negara dengan skor risiko tertinggi:'
            );

            $this->table(
                [
                    'Peringkat',
                    'Negara',
                    'Skor',
                    'Level',
                    'Kelengkapan',
                ],
                array_map(
                    static fn (
                        array $item,
                        int $index
                    ): array => [
                        $index + 1,
                        $item['country'],
                        $item['score'] . '/100',
                        $item['level'],
                        $item['completion'] . '%',
                    ],
                    $topRisks,
                    array_keys($topRisks)
                )
            );
        }

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

        if ($save) {
            $this->line(
                'Hasil terbaru tersimpan di risk_scores dan riwayat harian tersimpan di risk_histories.'
            );
        }

        $this->line(
            'Buka dashboard untuk melihat skor risiko terbaru.'
        );

        return $summary['success'] > 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
