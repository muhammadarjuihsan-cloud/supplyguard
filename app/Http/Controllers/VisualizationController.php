<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisualizationController extends Controller
{
    /**
     * Menampilkan halaman Visualisasi Data SupplyGuard.
     */
    public function index(Request $request)
    {
        $countries = DB::table('countries')
            ->select('id', 'name', 'cca2', 'cca3', 'region')
            ->orderBy('name')
            ->get();

        $selectedCountryId = $request->integer('country_id');

        if (
            $selectedCountryId <= 0
            || !$countries->contains(
                static fn ($country): bool =>
                    (int) $country->id === $selectedCountryId
            )
        ) {
            $selectedCountryId = (int) (
                $countries->firstWhere('cca3', 'IDN')?->id
                ?? $countries->first()?->id
                ?? 0
            );
        }

        $selectedCountry = $countries->firstWhere(
            'id',
            $selectedCountryId
        );

        $riskDistributionRow = DB::table('risk_scores')
            ->selectRaw(
                "SUM(CASE WHEN risk_level = 'Low' THEN 1 ELSE 0 END) as low_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN risk_level = 'Medium' THEN 1 ELSE 0 END) as medium_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END) as high_count"
            )
            ->first();

        $riskDistribution = [
            'low' => (int) ($riskDistributionRow->low_count ?? 0),
            'medium' => (int) ($riskDistributionRow->medium_count ?? 0),
            'high' => (int) ($riskDistributionRow->high_count ?? 0),
        ];

        $topRiskCountries = DB::table('risk_scores')
            ->join(
                'countries',
                'risk_scores.country_id',
                '=',
                'countries.id'
            )
            ->select(
                'countries.id',
                'countries.name',
                'countries.cca3',
                'risk_scores.total_score',
                'risk_scores.risk_level'
            )
            ->orderByDesc('risk_scores.total_score')
            ->orderBy('countries.name')
            ->limit(10)
            ->get();

        $componentAverageRow = DB::table('risk_scores')
            ->selectRaw('ROUND(AVG(weather_score), 2) as weather')
            ->selectRaw('ROUND(AVG(inflation_score), 2) as inflation')
            ->selectRaw('ROUND(AVG(currency_score), 2) as currency')
            ->selectRaw('ROUND(AVG(news_score), 2) as news')
            ->selectRaw('ROUND(AVG(port_score), 2) as port')
            ->first();

        $componentAverages = [
            'weather' => (float) ($componentAverageRow->weather ?? 0),
            'inflation' => (float) ($componentAverageRow->inflation ?? 0),
            'currency' => (float) ($componentAverageRow->currency ?? 0),
            'news' => (float) ($componentAverageRow->news ?? 0),
            'port' => (float) ($componentAverageRow->port ?? 0),
        ];

        $regionRisk = DB::table('risk_scores')
            ->join(
                'countries',
                'risk_scores.country_id',
                '=',
                'countries.id'
            )
            ->selectRaw(
                "COALESCE(NULLIF(countries.region, ''), 'Tidak diketahui') as region_name"
            )
            ->selectRaw('ROUND(AVG(risk_scores.total_score), 2) as average_score')
            ->selectRaw('COUNT(*) as country_count')
            ->groupBy('region_name')
            ->orderByDesc('average_score')
            ->get();

        $selectedRiskHistory = DB::table('risk_histories')
            ->where('country_id', $selectedCountryId)
            ->select('recorded_date', 'total_score', 'risk_level')
            ->orderBy('recorded_date')
            ->get();

        $selectedEconomyTrend = DB::table('economic_indicators')
            ->where('country_id', $selectedCountryId)
            ->select('year', 'gdp', 'inflation', 'population')
            ->orderBy('year')
            ->get();

        $selectedCurrency = DB::table('currency_rates')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('fetched_at')
            ->first();

        $selectedCurrencyTrend = collect();

        if ($selectedCurrency) {
            $selectedCurrencyTrend = DB::table('currency_histories')
                ->where(
                    'base_currency',
                    $selectedCurrency->base_currency
                )
                ->where(
                    'target_currency',
                    $selectedCurrency->target_currency
                )
                ->select('rate_date', 'rate')
                ->orderBy('rate_date')
                ->get();
        }

        $sentimentRow = DB::table('news_cache')
            ->when(
                $selectedCountryId > 0,
                function ($query) use ($selectedCountryId): void {
                    $query->where(
                        'country_id',
                        $selectedCountryId
                    );
                }
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(sentiment) = 'positive' THEN 1 ELSE 0 END) as positive_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(sentiment) = 'neutral' THEN 1 ELSE 0 END) as neutral_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(sentiment) = 'negative' THEN 1 ELSE 0 END) as negative_count"
            )
            ->first();

        $selectedSentiment = [
            'positive' => (int) ($sentimentRow->positive_count ?? 0),
            'neutral' => (int) ($sentimentRow->neutral_count ?? 0),
            'negative' => (int) ($sentimentRow->negative_count ?? 0),
        ];

        $totalCountries = DB::table('countries')->count();

        $dataCoverage = [
            'Ekonomi' => DB::table('economic_indicators')
                ->distinct()
                ->count('country_id'),
            'Cuaca' => DB::table('weather_cache')
                ->distinct()
                ->count('country_id'),
            'Mata Uang' => DB::table('currency_rates')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
            'Berita' => DB::table('news_cache')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
            'Pelabuhan' => DB::table('ports')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
            'Skor Risiko' => DB::table('risk_scores')
                ->distinct()
                ->count('country_id'),
        ];

        $statistics = [
            'total_countries' => $totalCountries,
            'average_risk' => (int) round(
                (float) DB::table('risk_scores')
                    ->avg('total_score')
            ),
            'complete_data' => $this->completeCountryCount(),
            'total_news' => DB::table('news_cache')->count(),
            'total_ports' => DB::table('ports')->count(),
            'last_risk_update' => DB::table('risk_scores')
                ->max('updated_at'),
        ];

        return view('supplyguard.visualization', compact(
            'countries',
            'selectedCountryId',
            'selectedCountry',
            'riskDistribution',
            'topRiskCountries',
            'componentAverages',
            'regionRisk',
            'selectedRiskHistory',
            'selectedEconomyTrend',
            'selectedCurrency',
            'selectedCurrencyTrend',
            'selectedSentiment',
            'dataCoverage',
            'statistics'
        ));
    }

    /**
     * Menghitung negara yang memiliki seluruh sumber data utama.
     */
    private function completeCountryCount(): int
    {
        return DB::table('countries')
            ->whereExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('economic_indicators')
                    ->whereColumn(
                        'economic_indicators.country_id',
                        'countries.id'
                    );
            })
            ->whereExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('weather_cache')
                    ->whereColumn(
                        'weather_cache.country_id',
                        'countries.id'
                    );
            })
            ->whereExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('currency_rates')
                    ->whereColumn(
                        'currency_rates.country_id',
                        'countries.id'
                    );
            })
            ->whereExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('news_cache')
                    ->whereColumn(
                        'news_cache.country_id',
                        'countries.id'
                    );
            })
            ->whereExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('ports')
                    ->whereColumn(
                        'ports.country_id',
                        'countries.id'
                    );
            })
            ->whereExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('risk_scores')
                    ->whereColumn(
                        'risk_scores.country_id',
                        'countries.id'
                    );
            })
            ->count();
    }
}
