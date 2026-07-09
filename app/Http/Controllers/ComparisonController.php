<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComparisonController extends Controller
{
    public function index(Request $request)
    {
        $countries = DB::table('countries')
            ->orderBy('name')
            ->get();

        $countryAId = $request->integer('country_a');
        $countryBId = $request->integer('country_b');

        if (!$countryAId && $countries->count() >= 1) {
            $countryAId = $countries[0]->id;
        }

        if (!$countryBId && $countries->count() >= 2) {
            $countryBId = $countries[1]->id;
        }

        $countryA = $this->getCountryComparisonData($countryAId);
        $countryB = $this->getCountryComparisonData($countryBId);

        return view('supplyguard.comparison', compact(
            'countries',
            'countryAId',
            'countryBId',
            'countryA',
            'countryB'
        ));
    }

    private function getCountryComparisonData($countryId)
    {
        $country = DB::table('countries')
            ->where('id', $countryId)
            ->first();

        if (!$country) {
            return null;
        }

        $economy = DB::table('economic_indicators')
            ->where('country_id', $countryId)
            ->orderByDesc('year')
            ->first();

        $weather = DB::table('weather_cache')
            ->where('country_id', $countryId)
            ->orderByDesc('fetched_at')
            ->first();

        $currency = DB::table('currency_rates')
            ->where('country_id', $countryId)
            ->orderByDesc('fetched_at')
            ->first();

        $risk = DB::table('risk_scores')
            ->where('country_id', $countryId)
            ->orderByDesc('score_date')
            ->first();

        return [
            'country' => $country,
            'economy' => $economy,
            'weather' => $weather,
            'currency' => $currency,
            'risk' => $risk,
        ];
    }
}
