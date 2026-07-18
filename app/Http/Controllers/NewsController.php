<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller
{
    /**
     * Menampilkan halaman Intelijen Berita SupplyGuard.
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $countryId = $request->integer('country_id');
        $sentiment = strtolower(trim((string) $request->query('sentiment', '')));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $allowedSentiments = ['positive', 'neutral', 'negative'];

        if (!in_array($sentiment, $allowedSentiments, true)) {
            $sentiment = '';
        }

        $filteredQuery = DB::table('news_cache')
            ->leftJoin('countries', 'countries.id', '=', 'news_cache.country_id')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('news_cache.title', 'like', "%{$keyword}%")
                        ->orWhere('news_cache.description', 'like', "%{$keyword}%")
                        ->orWhere('news_cache.source_name', 'like', "%{$keyword}%")
                        ->orWhere('news_cache.category', 'like', "%{$keyword}%")
                        ->orWhere('countries.name', 'like', "%{$keyword}%");
                });
            })
            ->when($countryId > 0, function ($query) use ($countryId): void {
                $query->where('news_cache.country_id', $countryId);
            })
            ->when($sentiment !== '', function ($query) use ($sentiment): void {
                $query->whereRaw('LOWER(news_cache.sentiment) = ?', [$sentiment]);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate('news_cache.published_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate('news_cache.published_at', '<=', $dateTo);
            });

        $newsItems = (clone $filteredQuery)
            ->select(
                'news_cache.id',
                'news_cache.country_id',
                'news_cache.title',
                'news_cache.description',
                'news_cache.source_name',
                'news_cache.url',
                'news_cache.image_url',
                'news_cache.category',
                'news_cache.sentiment',
                'news_cache.positive_score',
                'news_cache.negative_score',
                'news_cache.published_at',
                'news_cache.updated_at',
                'countries.name as country_name',
                'countries.cca2',
                'countries.cca3'
            )
            ->orderByDesc('news_cache.published_at')
            ->orderByDesc('news_cache.id')
            ->paginate(12)
            ->withQueryString();

        $countries = DB::table('countries')
            ->join('news_cache', 'news_cache.country_id', '=', 'countries.id')
            ->select('countries.id', 'countries.name', 'countries.cca2')
            ->distinct()
            ->orderBy('countries.name')
            ->get();

        $globalSentiment = DB::table('news_cache')
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

        $filteredSentiment = (clone $filteredQuery)
            ->selectRaw(
                "SUM(CASE WHEN LOWER(news_cache.sentiment) = 'positive' THEN 1 ELSE 0 END) as positive_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(news_cache.sentiment) = 'neutral' THEN 1 ELSE 0 END) as neutral_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(news_cache.sentiment) = 'negative' THEN 1 ELSE 0 END) as negative_count"
            )
            ->first();

        $topCountries = DB::table('news_cache')
            ->join('countries', 'countries.id', '=', 'news_cache.country_id')
            ->select(
                'countries.id',
                'countries.name',
                'countries.cca2',
                DB::raw('COUNT(news_cache.id) as article_count')
            )
            ->groupBy('countries.id', 'countries.name', 'countries.cca2')
            ->orderByDesc('article_count')
            ->limit(6)
            ->get();

        $statistics = [
            'total_news' => DB::table('news_cache')->count(),
            'filtered_news' => (clone $filteredQuery)->count('news_cache.id'),
            'countries_covered' => DB::table('news_cache')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
            'sources' => DB::table('news_cache')
                ->whereNotNull('source_name')
                ->where('source_name', '<>', '')
                ->distinct()
                ->count('source_name'),
            'last_updated' => DB::table('news_cache')->max('updated_at'),
        ];

        $sentimentChart = [
            'positive' => (int) ($filteredSentiment->positive_count ?? 0),
            'neutral' => (int) ($filteredSentiment->neutral_count ?? 0),
            'negative' => (int) ($filteredSentiment->negative_count ?? 0),
        ];

        $globalSentimentTotals = [
            'positive' => (int) ($globalSentiment->positive_count ?? 0),
            'neutral' => (int) ($globalSentiment->neutral_count ?? 0),
            'negative' => (int) ($globalSentiment->negative_count ?? 0),
        ];

        return view('supplyguard.news-intelligence', compact(
            'newsItems',
            'countries',
            'topCountries',
            'statistics',
            'sentimentChart',
            'globalSentimentTotals',
            'keyword',
            'countryId',
            'sentiment',
            'dateFrom',
            'dateTo'
        ));
    }
}
