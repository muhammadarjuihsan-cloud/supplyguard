<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplyGuardApiController extends Controller
{
    /**
     * Daftar negara dengan pencarian, filter wilayah, dan pagination.
     */
    public function countries(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $region = trim((string) $request->query('region', ''));
        $perPage = $this->perPage($request, default: 50, maximum: 100);

        $countries = DB::table('countries')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('official_name', 'like', "%{$keyword}%")
                        ->orWhere('cca2', 'like', "%{$keyword}%")
                        ->orWhere('cca3', 'like', "%{$keyword}%");
                });
            })
            ->when($region !== '', function ($query) use ($region): void {
                $query->where('region', $region);
            })
            ->orderBy('name')
            ->paginate($perPage);

        return $this->paginatedResponse($countries);
    }

    /**
     * Skor risiko terbaru.
     *
     * - Dengan country_id: satu negara.
     * - Tanpa country_id: daftar seluruh negara dengan pagination.
     */
    public function risk(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');
        $riskLevel = trim((string) $request->query('risk_level', ''));
        $keyword = trim((string) $request->query('q', ''));

        if (
            $riskLevel !== ''
            && !in_array($riskLevel, ['Low', 'Medium', 'High'], true)
        ) {
            return $this->errorResponse(
                'risk_level harus bernilai Low, Medium, atau High.',
                422
            );
        }

        $query = DB::table('risk_scores')
            ->join(
                'countries',
                'risk_scores.country_id',
                '=',
                'countries.id'
            )
            ->select(
                'risk_scores.*',
                'countries.name as country_name',
                'countries.official_name as country_official_name',
                'countries.cca2',
                'countries.cca3',
                'countries.region',
                'countries.currency_code'
            );

        if ($countryId > 0) {
            $risk = (clone $query)
                ->where('risk_scores.country_id', $countryId)
                ->first();

            if (!$risk) {
                return $this->errorResponse(
                    'Skor risiko untuk negara tersebut tidak ditemukan.',
                    404
                );
            }

            return $this->successResponse($risk);
        }

        $perPage = $this->perPage($request, default: 25, maximum: 100);

        $risks = $query
            ->when($riskLevel !== '', function ($query) use ($riskLevel): void {
                $query->where('risk_scores.risk_level', $riskLevel);
            })
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('countries.name', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca2', 'like', "%{$keyword}%")
                        ->orWhere('countries.cca3', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('risk_scores.total_score')
            ->orderBy('countries.name')
            ->paginate($perPage);

        return $this->paginatedResponse($risks);
    }

    /**
     * Data pelabuhan dengan filter dan pagination.
     */
    public function ports(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');
        $keyword = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $perPage = $this->perPage($request, default: 50, maximum: 100);

        if (
            $countryId > 0
            && !DB::table('countries')->where('id', $countryId)->exists()
        ) {
            return $this->errorResponse(
                'Negara yang diminta tidak ditemukan.',
                404
            );
        }

        $ports = DB::table('ports')
            ->leftJoin(
                'countries',
                'ports.country_id',
                '=',
                'countries.id'
            )
            ->select(
                'ports.id',
                'ports.country_id',
                'ports.name',
                'ports.country_name',
                'ports.port_code',
                'ports.type',
                'ports.latitude',
                'ports.longitude',
                'ports.description',
                'countries.cca2',
                'countries.cca3'
            )
            ->when($countryId > 0, function ($query) use ($countryId): void {
                $query->where('ports.country_id', $countryId);
            })
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('ports.name', 'like', "%{$keyword}%")
                        ->orWhere('ports.country_name', 'like', "%{$keyword}%")
                        ->orWhere('ports.port_code', 'like', "%{$keyword}%")
                        ->orWhere('ports.description', 'like', "%{$keyword}%");
                });
            })
            ->when($type !== '', function ($query) use ($type): void {
                $query->where('ports.type', $type);
            })
            ->orderBy('ports.name')
            ->paginate($perPage);

        return $this->paginatedResponse($ports);
    }

    /**
     * Berita terbaru dengan filter negara, sentimen, pencarian, dan pagination.
     */
    public function news(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');
        $keyword = trim((string) $request->query('q', ''));
        $sentiment = strtolower(
            trim((string) $request->query('sentiment', ''))
        );
        $perPage = $this->perPage($request, default: 20, maximum: 50);

        if (
            $sentiment !== ''
            && !in_array(
                $sentiment,
                ['positive', 'neutral', 'negative'],
                true
            )
        ) {
            return $this->errorResponse(
                'sentiment harus bernilai positive, neutral, atau negative.',
                422
            );
        }

        if (
            $countryId > 0
            && !DB::table('countries')->where('id', $countryId)->exists()
        ) {
            return $this->errorResponse(
                'Negara yang diminta tidak ditemukan.',
                404
            );
        }

        $news = DB::table('news_cache')
            ->leftJoin(
                'countries',
                'news_cache.country_id',
                '=',
                'countries.id'
            )
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
                'countries.name as country_name',
                'countries.cca2',
                'countries.cca3'
            )
            ->when($countryId > 0, function ($query) use ($countryId): void {
                $query->where('news_cache.country_id', $countryId);
            })
            ->when($sentiment !== '', function ($query) use ($sentiment): void {
                $query->whereRaw(
                    'LOWER(news_cache.sentiment) = ?',
                    [$sentiment]
                );
            })
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('news_cache.title', 'like', "%{$keyword}%")
                        ->orWhere('news_cache.description', 'like', "%{$keyword}%")
                        ->orWhere('news_cache.source_name', 'like', "%{$keyword}%")
                        ->orWhere('countries.name', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('news_cache.published_at')
            ->orderByDesc('news_cache.id')
            ->paginate($perPage);

        return $this->paginatedResponse($news);
    }

    /**
     * Nilai tukar terbaru dan riwayat satu negara.
     */
    public function currency(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');

        if ($countryId <= 0) {
            return $this->errorResponse(
                'Parameter country_id wajib diisi.',
                422
            );
        }

        $country = DB::table('countries')
            ->where('id', $countryId)
            ->select(
                'id',
                'name',
                'cca2',
                'cca3',
                'currency_code',
                'currency_name'
            )
            ->first();

        if (!$country) {
            return $this->errorResponse(
                'Negara yang diminta tidak ditemukan.',
                404
            );
        }

        $currency = DB::table('currency_rates')
            ->where('country_id', $countryId)
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->first();

        if (!$currency) {
            return $this->errorResponse(
                'Data nilai tukar untuk negara tersebut belum tersedia.',
                404
            );
        }

        $historyLimit = min(
            max($request->integer('history_limit', 30), 1),
            365
        );

        $history = DB::table('currency_histories')
            ->where('base_currency', $currency->base_currency)
            ->where('target_currency', $currency->target_currency)
            ->orderByDesc('rate_date')
            ->limit($historyLimit)
            ->get()
            ->sortBy('rate_date')
            ->values();

        return $this->successResponse([
            'country' => $country,
            'current' => $currency,
            'history' => $history,
        ]);
    }

    private function perPage(
        Request $request,
        int $default,
        int $maximum
    ): int {
        return min(
            max($request->integer('per_page', $default), 1),
            $maximum
        );
    }

    private function successResponse(mixed $data): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function errorResponse(
        string $message,
        int $statusCode
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'generated_at' => now()->toIso8601String(),
        ], $statusCode);
    }

    private function paginatedResponse(
        LengthAwarePaginator $paginator
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
