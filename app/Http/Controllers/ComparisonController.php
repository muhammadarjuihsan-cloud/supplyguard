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

        $countryIds = $countries
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values();

        $countryAId = $request->integer('country_a');
        $countryBId = $request->integer('country_b');

        if (!$countryIds->contains($countryAId)) {
            $countryAId = (int) ($countryIds->first() ?? 0);
        }

        /*
         * Negara kedua harus valid dan berbeda dari negara pertama.
         * Ini mencegah tombol Bandingkan pada halaman Data Negara membuka
         * perbandingan negara yang sama pada kedua sisi.
         */
        if (
            !$countryIds->contains($countryBId)
            || $countryBId === $countryAId
        ) {
            $countryBId = (int) (
                $countryIds->first(
                    static fn (int $id): bool => $id !== $countryAId
                ) ?? 0
            );
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

    private function getCountryComparisonData(int $countryId): ?array
    {
        if ($countryId <= 0) {
            return null;
        }

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
