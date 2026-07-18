<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WatchlistController extends Controller
{
    /**
     * Menampilkan halaman khusus Daftar Pantauan.
     */
    public function index(Request $request)
    {
        $userId = (int) auth()->id();
        $keyword = trim((string) $request->query('q', ''));
        $riskLevel = trim((string) $request->query('risk_level', ''));

        $allowedRiskLevels = ['Low', 'Medium', 'High'];

        if (!in_array($riskLevel, $allowedRiskLevels, true)) {
            $riskLevel = '';
        }

        $portCounts = DB::table('ports')
            ->select('country_id', DB::raw('COUNT(*) as port_count'))
            ->groupBy('country_id');

        $newsCounts = DB::table('news_cache')
            ->select('country_id', DB::raw('COUNT(*) as news_count'))
            ->groupBy('country_id');

        $baseQuery = DB::table('watchlists')
            ->join('countries', 'watchlists.country_id', '=', 'countries.id')
            ->leftJoin('risk_scores', 'countries.id', '=', 'risk_scores.country_id')
            ->leftJoinSub($portCounts, 'port_totals', function ($join): void {
                $join->on('countries.id', '=', 'port_totals.country_id');
            })
            ->leftJoinSub($newsCounts, 'news_totals', function ($join): void {
                $join->on('countries.id', '=', 'news_totals.country_id');
            })
            ->where('watchlists.user_id', $userId);

        $watchlists = (clone $baseQuery)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('countries.name', 'like', "%{$keyword}%")
                        ->orWhere('countries.official_name', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca2', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca3', 'like', "%{$keyword}%")
                        ->orWhere('countries.region', 'like', "%{$keyword}%")
                        ->orWhere('watchlists.note', 'like', "%{$keyword}%");
                });
            })
            ->when($riskLevel !== '', function ($query) use ($riskLevel): void {
                $query->where('risk_scores.risk_level', $riskLevel);
            })
            ->select(
                'watchlists.id',
                'watchlists.country_id',
                'watchlists.note',
                'watchlists.created_at as watched_at',
                'countries.name as country_name',
                'countries.official_name',
                'countries.cca2',
                'countries.cca3',
                'countries.region',
                'countries.subregion',
                'countries.currency_code',
                'countries.latitude',
                'countries.longitude',
                'risk_scores.weather_score',
                'risk_scores.inflation_score',
                'risk_scores.currency_score',
                'risk_scores.news_score',
                'risk_scores.port_score',
                'risk_scores.total_score',
                'risk_scores.risk_level',
                'risk_scores.recommendation',
                'risk_scores.score_date',
                DB::raw('COALESCE(port_totals.port_count, 0) as port_count'),
                DB::raw('COALESCE(news_totals.news_count, 0) as news_count')
            )
            ->orderByRaw(
                "CASE risk_scores.risk_level
                    WHEN 'High' THEN 1
                    WHEN 'Medium' THEN 2
                    WHEN 'Low' THEN 3
                    ELSE 4
                END"
            )
            ->orderBy('countries.name')
            ->paginate(12)
            ->withQueryString();

        $statRows = (clone $baseQuery)
            ->select('risk_scores.total_score', 'risk_scores.risk_level')
            ->get();

        $statistics = [
            'total' => $statRows->count(),
            'high' => $statRows->where('risk_level', 'High')->count(),
            'medium' => $statRows->where('risk_level', 'Medium')->count(),
            'low' => $statRows->where('risk_level', 'Low')->count(),
            'unavailable' => $statRows->whereNull('risk_level')->count(),
            'average_score' => $statRows
                ->whereNotNull('total_score')
                ->avg('total_score'),
        ];

        $statistics['average_score'] = $statistics['average_score'] !== null
            ? (int) round((float) $statistics['average_score'])
            : null;

        $availableCountries = DB::table('countries')
            ->whereNotExists(function ($query) use ($userId): void {
                $query
                    ->select(DB::raw(1))
                    ->from('watchlists')
                    ->whereColumn('watchlists.country_id', 'countries.id')
                    ->where('watchlists.user_id', $userId);
            })
            ->select('id', 'name', 'cca2', 'cca3')
            ->orderBy('name')
            ->get();

        return view('supplyguard.watchlist', compact(
            'watchlists',
            'statistics',
            'availableCountries',
            'keyword',
            'riskLevel'
        ));
    }

    /**
     * Menambahkan negara ke daftar pantauan pengguna.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) auth()->id();
        $countryId = (int) $validated['country_id'];

        $exists = DB::table('watchlists')
            ->where('user_id', $userId)
            ->where('country_id', $countryId)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Negara tersebut sudah ada dalam daftar pantauan.');
        }

        DB::table('watchlists')->insert([
            'user_id' => $userId,
            'country_id' => $countryId,
            'note' => $this->normalizeNote($validated['note'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Negara berhasil ditambahkan ke daftar pantauan.');
    }

    /**
     * Memperbarui catatan negara yang dipantau.
     */
    public function updateNote(Request $request, int $countryId)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('country_id', $countryId)
            ->update([
                'note' => $this->normalizeNote($validated['note'] ?? null),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            $exists = DB::table('watchlists')
                ->where('user_id', auth()->id())
                ->where('country_id', $countryId)
                ->exists();

            if (!$exists) {
                return back()->with('error', 'Data pantauan tidak ditemukan.');
            }
        }

        return back()->with('success', 'Catatan pantauan berhasil diperbarui.');
    }

    /**
     * Menghapus negara dari daftar pantauan pengguna.
     */
    public function destroyByCountry(int $countryId)
    {
        $deleted = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('country_id', $countryId)
            ->delete();

        if ($deleted === 0) {
            return back()->with('error', 'Negara tersebut tidak ditemukan dalam daftar pantauan.');
        }

        return back()->with('success', 'Negara berhasil dihapus dari daftar pantauan.');
    }

    /**
     * Merapikan catatan kosong sebelum disimpan.
     */
    private function normalizeNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }
}
