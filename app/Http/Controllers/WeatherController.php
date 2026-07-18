<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeatherController extends Controller
{
    /**
     * Menampilkan halaman Monitoring Cuaca SupplyGuard.
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $riskRange = trim((string) $request->query('risk', ''));
        $region = trim((string) $request->query('region', ''));

        $allowedRiskRanges = ['low', 'medium', 'high'];

        if (!in_array($riskRange, $allowedRiskRanges, true)) {
            $riskRange = '';
        }

        $filteredQuery = DB::table('weather_cache')
            ->join(
                'countries',
                'weather_cache.country_id',
                '=',
                'countries.id'
            )
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('countries.name', 'like', "%{$keyword}%")
                        ->orWhere('countries.official_name', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca2', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca3', 'like', "%{$keyword}%")
                        ->orWhere('weather_cache.weather_status', 'like', "%{$keyword}%");
                });
            })
            ->when($region !== '', function ($query) use ($region): void {
                $query->where('countries.region', $region);
            })
            ->when($riskRange !== '', function ($query) use ($riskRange): void {
                match ($riskRange) {
                    'low' => $query->whereBetween(
                        'weather_cache.weather_risk',
                        [0, 30]
                    ),
                    'medium' => $query->whereBetween(
                        'weather_cache.weather_risk',
                        [31, 60]
                    ),
                    'high' => $query->whereBetween(
                        'weather_cache.weather_risk',
                        [61, 100]
                    ),
                };
            });

        $weatherItems = (clone $filteredQuery)
            ->select(
                'weather_cache.id',
                'weather_cache.country_id',
                'weather_cache.temperature',
                'weather_cache.rainfall',
                'weather_cache.wind_speed',
                'weather_cache.weather_status',
                'weather_cache.weather_risk',
                'weather_cache.fetched_at',
                'countries.name as country_name',
                'countries.cca2',
                'countries.cca3',
                'countries.region',
                'countries.subregion',
                'countries.latitude',
                'countries.longitude'
            )
            ->orderByDesc('weather_cache.weather_risk')
            ->orderBy('countries.name')
            ->paginate(20)
            ->withQueryString();


        $weatherMapItems = (clone $filteredQuery)
            ->whereNotNull('countries.latitude')
            ->whereNotNull('countries.longitude')
            ->select(
                'weather_cache.country_id',
                'weather_cache.temperature',
                'weather_cache.rainfall',
                'weather_cache.wind_speed',
                'weather_cache.weather_status',
                'weather_cache.weather_risk',
                'countries.name as country_name',
                'countries.cca2',
                'countries.cca3',
                'countries.region',
                'countries.latitude',
                'countries.longitude'
            )
            ->orderByDesc('weather_cache.weather_risk')
            ->limit(300)
            ->get();

        $regions = DB::table('countries')
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        $globalRiskRow = DB::table('weather_cache')
            ->selectRaw(
                'SUM(CASE WHEN weather_risk BETWEEN 0 AND 30 THEN 1 ELSE 0 END) as low_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN weather_risk BETWEEN 31 AND 60 THEN 1 ELSE 0 END) as medium_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN weather_risk BETWEEN 61 AND 100 THEN 1 ELSE 0 END) as high_count'
            )
            ->first();

        $riskDistribution = [
            'low' => (int) ($globalRiskRow->low_count ?? 0),
            'medium' => (int) ($globalRiskRow->medium_count ?? 0),
            'high' => (int) ($globalRiskRow->high_count ?? 0),
        ];

        $topRiskCountries = DB::table('weather_cache')
            ->join(
                'countries',
                'weather_cache.country_id',
                '=',
                'countries.id'
            )
            ->select(
                'countries.id',
                'countries.name',
                'countries.cca3',
                'weather_cache.weather_status',
                'weather_cache.weather_risk',
                'weather_cache.temperature',
                'weather_cache.rainfall',
                'weather_cache.wind_speed'
            )
            ->orderByDesc('weather_cache.weather_risk')
            ->orderByDesc('weather_cache.wind_speed')
            ->limit(8)
            ->get();

        $statistics = [
            'total_weather' => DB::table('weather_cache')->count(),
            'filtered_weather' => (clone $filteredQuery)
                ->count('weather_cache.id'),
            'average_temperature' => DB::table('weather_cache')
                ->avg('temperature'),
            'average_rainfall' => DB::table('weather_cache')
                ->avg('rainfall'),
            'average_wind' => DB::table('weather_cache')
                ->avg('wind_speed'),
            'last_updated' => DB::table('weather_cache')
                ->max('fetched_at'),
        ];

        return view('supplyguard.weather-monitoring', compact(
            'weatherItems',
            'weatherMapItems',
            'regions',
            'riskDistribution',
            'topRiskCountries',
            'statistics',
            'keyword',
            'riskRange',
            'region'
        ));
    }
}
