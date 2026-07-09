<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WatchlistController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
        ]);

        $exists = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('country_id', $request->country_id)
            ->exists();

        if (!$exists) {
            DB::table('watchlists')->insert([
                'user_id' => auth()->id(),
                'country_id' => $request->country_id,
                'note' => 'Monitored country',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('dashboard', ['country_id' => $request->country_id])
            ->with('success', 'Country added to monitoring list.');
    }

    public function destroyByCountry($countryId)
    {
        DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('country_id', $countryId)
            ->delete();

        return redirect()
            ->route('dashboard', ['country_id' => $countryId])
            ->with('success', 'Country removed from monitoring list.');
    }
}
