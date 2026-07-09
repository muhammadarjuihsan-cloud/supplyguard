<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplyGuardApiController extends Controller
{
    public function countries()
    {
        $countries = DB::table('countries')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $countries,
        ]);
    }

    public function risk(Request $request)
    {
        $countryId = $request->integer('country_id');

        $risk = DB::table('risk_scores')
            ->join('countries', 'risk_scores.country_id', '=', 'countries.id')
            ->select(
                'risk_scores.*',
                'countries.name as country_name',
                'countries.currency_code'
            )
            ->when($countryId, function ($query) use ($countryId) {
                return $query->where('risk_scores.country_id', $countryId);
            })
            ->orderByDesc('risk_scores.score_date')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $risk,
        ]);
    }

    public function ports(Request $request)
    {
        $countryId = $request->integer('country_id');
        $keyword = $request->get('q');

        $ports = DB::table('ports')
            ->when($countryId, function ($query) use ($countryId) {
                return $query->where('country_id', $countryId);
            })
            ->when($keyword, function ($query) use ($keyword) {
                return $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('country_name', 'like', "%{$keyword}%")
                        ->orWhere('port_code', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $ports,
        ]);
    }

    public function news(Request $request)
    {
        $countryId = $request->integer('country_id');

        $news = DB::table('news_cache')
            ->when($countryId, function ($query) use ($countryId) {
                return $query->where('country_id', $countryId);
            })
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $news,
        ]);
    }

    public function currency(Request $request)
    {
        $countryId = $request->integer('country_id');

        $currency = DB::table('currency_rates')
            ->where('country_id', $countryId)
            ->orderByDesc('fetched_at')
            ->first();

        $history = collect();

        if ($currency) {
            $history = DB::table('currency_histories')
                ->where('base_currency', $currency->base_currency)
                ->where('target_currency', $currency->target_currency)
                ->orderBy('rate_date')
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'current' => $currency,
                'history' => $history,
            ],
        ]);
    }
}
