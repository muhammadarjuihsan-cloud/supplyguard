<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryDataController extends Controller
{
    /**
     * Menampilkan dataset seluruh negara beserta data pendukungnya.
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $region = trim((string) $request->query('region', ''));
        $subregion = trim((string) $request->query('subregion', ''));
        $riskLevel = trim((string) $request->query('risk_level', ''));
        $sort = trim((string) $request->query('sort', 'name'));

        $allowedRiskLevels = ['Low', 'Medium', 'High'];
        $allowedSorts = ['name', 'risk_desc', 'ports_desc', 'news_desc'];

        if (!in_array($riskLevel, $allowedRiskLevels, true)) {
            $riskLevel = '';
        }

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        /*
         * Agregasi dibuat sekali melalui subquery, bukan menghitung COUNT(*)
         * berulang untuk setiap baris negara. Ini menjaga halaman tetap cepat
         * ketika jumlah pelabuhan dan berita terus bertambah.
         */
        $portCounts = DB::table('ports')
            ->select('country_id')
            ->selectRaw('COUNT(*) as port_count')
            ->whereNotNull('country_id')
            ->groupBy('country_id');

        $newsCounts = DB::table('news_cache')
            ->select('country_id')
            ->selectRaw('COUNT(*) as news_count')
            ->whereNotNull('country_id')
            ->groupBy('country_id');

        $filteredQuery = DB::table('countries')
            ->leftJoin(
                'risk_scores',
                'countries.id',
                '=',
                'risk_scores.country_id'
            )
            ->leftJoinSub(
                $portCounts,
                'port_stats',
                'countries.id',
                '=',
                'port_stats.country_id'
            )
            ->leftJoinSub(
                $newsCounts,
                'news_stats',
                'countries.id',
                '=',
                'news_stats.country_id'
            )
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('countries.name', 'like', "%{$keyword}%")
                        ->orWhere(
                            'countries.official_name',
                            'like',
                            "%{$keyword}%"
                        )
                        ->orWhere('countries.cca2', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca3', 'like', "%{$keyword}%")
                        ->orWhere('countries.capital', 'like', "%{$keyword}%")
                        ->orWhere(
                            'countries.currency_code',
                            'like',
                            "%{$keyword}%"
                        )
                        ->orWhere(
                            'countries.currency_name',
                            'like',
                            "%{$keyword}%"
                        );
                });
            })
            ->when($region !== '', function ($query) use ($region): void {
                $query->where('countries.region', $region);
            })
            ->when(
                $subregion !== '',
                function ($query) use ($subregion): void {
                    $query->where('countries.subregion', $subregion);
                }
            )
            ->when(
                $riskLevel !== '',
                function ($query) use ($riskLevel): void {
                    $query->where('risk_scores.risk_level', $riskLevel);
                }
            );

        $countriesQuery = (clone $filteredQuery)
            ->select(
                'countries.id',
                'countries.name',
                'countries.official_name',
                'countries.cca2',
                'countries.cca3',
                'countries.capital',
                'countries.region',
                'countries.subregion',
                'countries.currency_code',
                'countries.currency_name',
                'countries.language',
                'countries.latitude',
                'countries.longitude',
                'countries.updated_at',
                'risk_scores.total_score',
                'risk_scores.risk_level',
                'risk_scores.score_date'
            )
            ->selectRaw('COALESCE(port_stats.port_count, 0) as port_count')
            ->selectRaw('COALESCE(news_stats.news_count, 0) as news_count');

        match ($sort) {
            'risk_desc' => $countriesQuery
                ->orderByRaw('risk_scores.total_score IS NULL ASC')
                ->orderByDesc('risk_scores.total_score')
                ->orderBy('countries.name'),

            'ports_desc' => $countriesQuery
                ->orderByRaw('COALESCE(port_stats.port_count, 0) DESC')
                ->orderBy('countries.name'),

            'news_desc' => $countriesQuery
                ->orderByRaw('COALESCE(news_stats.news_count, 0) DESC')
                ->orderBy('countries.name'),

            default => $countriesQuery->orderBy('countries.name'),
        };

        $countries = $countriesQuery
            ->paginate(25)
            ->withQueryString();

        $regions = DB::table('countries')
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        $subregions = DB::table('countries')
            ->whereNotNull('subregion')
            ->where('subregion', '<>', '')
            ->when($region !== '', function ($query) use ($region): void {
                $query->where('region', $region);
            })
            ->distinct()
            ->orderBy('subregion')
            ->pluck('subregion');

        $statistics = [
            'total' => DB::table('countries')->count(),

            'filtered' => (clone $filteredQuery)
                ->distinct()
                ->count('countries.id'),

            'regions' => DB::table('countries')
                ->whereNotNull('region')
                ->where('region', '<>', '')
                ->distinct()
                ->count('region'),

            'with_risk' => DB::table('risk_scores')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),

            'with_ports' => DB::table('ports')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),

            'with_news' => DB::table('news_cache')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),

            'last_updated_at' => DB::table('countries')->max('updated_at'),
        ];

        return view('supplyguard.country-data', compact(
            'countries',
            'regions',
            'subregions',
            'statistics',
            'keyword',
            'region',
            'subregion',
            'riskLevel',
            'sort'
        ));
    }
}
