<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $countries = DB::table('countries')
            ->orderBy('name')
            ->get();

        $selectedCountryId = $request->integer('country_id');

        if (!$selectedCountryId && $countries->count() > 0) {
            $selectedCountryId = $countries->first()->id;
        }

        $country = DB::table('countries')
            ->where('id', $selectedCountryId)
            ->first();

        $economyLatest = DB::table('economic_indicators')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('year')
            ->first();

        $economyTrend = DB::table('economic_indicators')
            ->where('country_id', $selectedCountryId)
            ->orderBy('year')
            ->get();

        $weather = DB::table('weather_cache')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('fetched_at')
            ->first();

        $currency = DB::table('currency_rates')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('fetched_at')
            ->first();

        $currencyTrend = collect();

        if ($country && $country->currency_code) {
            $currencyTrend = DB::table('currency_histories')
                ->where('target_currency', $country->currency_code)
                ->orderBy('rate_date')
                ->get();
        }

        $news = DB::table('news_cache')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        $ports = DB::table('ports')
            ->where('country_id', $selectedCountryId)
            ->orderBy('name')
            ->get();

        $risk = DB::table('risk_scores')
            ->where('country_id', $selectedCountryId)
            ->orderByDesc('score_date')
            ->first();

        $riskTrend = DB::table('risk_histories')
            ->where('country_id', $selectedCountryId)
            ->orderBy('recorded_date')
            ->get();

        $watchlists = DB::table('watchlists')
            ->join('countries', 'watchlists.country_id', '=', 'countries.id')
            ->leftJoin('risk_scores', 'countries.id', '=', 'risk_scores.country_id')
            ->where('watchlists.user_id', auth()->id())
            ->select(
                'watchlists.*',
                'countries.name as country_name',
                'countries.currency_code',
                'risk_scores.total_score',
                'risk_scores.risk_level'
            )
            ->orderBy('countries.name')
            ->get();

        $isWatched = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('country_id', $selectedCountryId)
            ->exists();

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
            'isWatched'
        ));
    }
}
