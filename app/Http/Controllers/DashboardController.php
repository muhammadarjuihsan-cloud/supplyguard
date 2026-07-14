<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Menampilkan dashboard SupplyGuard.
     */
    public function index(Request $request)
    {
        /*
         * Seluruh negara sudah tersedia dari hasil sinkronisasi.
         */
        $countries = DB::table('countries')
            ->orderBy('name')
            ->get();

        /*
         * Menentukan negara yang dipilih.
         *
         * Jika tidak ada parameter country_id:
         * 1. Gunakan Indonesia sebagai negara awal.
         * 2. Jika Indonesia tidak ditemukan, gunakan negara pertama.
         *
         * Jika ID dari URL tidak valid, kembali ke negara awal.
         */
        $selectedCountryId = $this->resolveSelectedCountryId(
            requestCountryId: $request->integer('country_id'),
            countries: $countries
        );

        $country = DB::table('countries')
            ->where('id', $selectedCountryId)
            ->first();

        /*
         * Data ekonomi terbaru.
         */
        $economyLatest = DB::table('economic_indicators')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('year')
            ->first();

        /*
         * Riwayat data ekonomi untuk grafik.
         */
        $economyTrend = DB::table('economic_indicators')
            ->where('country_id', $selectedCountryId)
            ->orderBy('year')
            ->get();

        /*
         * Data cuaca terbaru.
         */
        $weather = DB::table('weather_cache')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('fetched_at')
            ->first();

        /*
         * Data kurs terbaru.
         */
        $currency = DB::table('currency_rates')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('fetched_at')
            ->first();

        /*
         * Riwayat kurs mata uang.
         */
        $currencyTrend = collect();

        if ($country && !empty($country->currency_code)) {
            $currencyTrend = DB::table('currency_histories')
                ->where('target_currency', $country->currency_code)
                ->orderBy('rate_date')
                ->get();
        }

        /*
         * Lima berita terbaru.
         */
        $news = DB::table('news_cache')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        /*
         * Pelabuhan yang terhubung dengan negara.
         */
        $ports = DB::table('ports')
            ->where('country_id', $selectedCountryId)
            ->orderBy('name')
            ->get();

        /*
         * Skor risiko terbaru.
         */
        $risk = DB::table('risk_scores')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('score_date')
            ->first();

        /*
         * Riwayat skor risiko.
         */
        $riskTrend = DB::table('risk_histories')
            ->where('country_id', $selectedCountryId)
            ->orderBy('recorded_date')
            ->get();

        /*
         * Daftar pantauan milik pengguna yang sedang login.
         */
        $watchlists = DB::table('watchlists')
            ->join(
                'countries',
                'watchlists.country_id',
                '=',
                'countries.id'
            )
            ->leftJoin(
                'risk_scores',
                'countries.id',
                '=',
                'risk_scores.country_id'
            )
            ->where('watchlists.user_id', auth()->id())
            ->select(
                'watchlists.id',
                'watchlists.user_id',
                'watchlists.country_id',
                'watchlists.created_at',
                'countries.name as country_name',
                'countries.currency_code',
                'risk_scores.total_score',
                'risk_scores.risk_level'
            )
            ->orderBy('countries.name')
            ->get()
            ->unique('country_id')
            ->values();

        /*
         * Mengecek apakah negara aktif sudah ada di watchlist.
         */
        $isWatched = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('country_id', $selectedCountryId)
            ->exists();

        /*
         * Status kelengkapan data.
         *
         * Ini penting karena 246 negara baru belum memiliki
         * seluruh data ekonomi, cuaca, kurs, berita, dan risiko.
         */
        $dataAvailability = [
            'economy' => $economyLatest !== null,
            'weather' => $weather !== null,
            'currency' => $currency !== null,
            'news' => $news->isNotEmpty(),
            'ports' => $ports->isNotEmpty(),
            'risk' => $risk !== null,
        ];

        /*
         * Nama sumber data yang belum tersedia.
         */
        $dataLabels = [
            'economy' => 'Ekonomi',
            'weather' => 'Cuaca',
            'currency' => 'Nilai tukar',
            'news' => 'Berita',
            'ports' => 'Pelabuhan',
            'risk' => 'Skor risiko',
        ];

        $missingData = collect($dataAvailability)
            ->filter(fn (bool $available): bool => !$available)
            ->keys()
            ->map(
                fn (string $key): string =>
                    $dataLabels[$key] ?? $key
            )
            ->values();

        /*
         * Menghitung persentase kelengkapan data negara.
         */
        $availableDataCount = collect($dataAvailability)
            ->filter()
            ->count();

        $totalDataSources = count($dataAvailability);

        $dataCompletionPercentage = $totalDataSources > 0
            ? (int) round(
                ($availableDataCount / $totalDataSources) * 100
            )
            : 0;

        /*
         * Status keseluruhan data.
         */
        $dataStatus = match (true) {
            $dataCompletionPercentage === 100 => 'complete',
            $dataCompletionPercentage >= 50 => 'partial',
            default => 'limited',
        };

        /*
         * Mencari waktu pembaruan terakhir dari data yang tersedia.
         */
        $lastUpdatedAt = collect([
            $weather->fetched_at ?? null,
            $currency->fetched_at ?? null,
            $risk->updated_at ?? null,
            $risk->created_at ?? null,
        ])
            ->filter()
            ->sortDesc()
            ->first();

        return view('supplyguard.dashboard', compact(
            'countries',
            'selectedCountryId',
            'country',
            'economyLatest',
            'economyTrend',
            'weather',
            'currency',
            'currencyTrend',
            'news',
            'ports',
            'risk',
            'riskTrend',
            'watchlists',
            'isWatched',
            'dataAvailability',
            'missingData',
            'availableDataCount',
            'totalDataSources',
            'dataCompletionPercentage',
            'dataStatus',
            'lastUpdatedAt'
        ));
    }

    /**
     * Menentukan ID negara yang valid untuk dashboard.
     */
    private function resolveSelectedCountryId(
        int $requestCountryId,
        Collection $countries
    ): ?int {
        if ($countries->isEmpty()) {
            return null;
        }

        /*
         * Gunakan ID dari URL jika benar-benar terdapat
         * pada koleksi negara.
         */
        if (
            $requestCountryId > 0 &&
            $countries->contains(
                fn ($country): bool =>
                    (int) $country->id === $requestCountryId
            )
        ) {
            return $requestCountryId;
        }

        /*
         * Indonesia menjadi negara awal dashboard.
         */
        $indonesia = $countries->first(
            fn ($country): bool =>
                strtoupper((string) ($country->cca2 ?? '')) === 'ID' ||
                strtoupper((string) ($country->cca3 ?? '')) === 'IDN' ||
                strtolower((string) $country->name) === 'indonesia'
        );

        if ($indonesia) {
            return (int) $indonesia->id;
        }

        return (int) $countries->first()->id;
    }
}
