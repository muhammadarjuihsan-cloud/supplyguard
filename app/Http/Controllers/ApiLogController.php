<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiLogController extends Controller
{
    /**
     * Menampilkan log integrasi API eksternal untuk administrator.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $keyword = trim((string) $request->query('q', ''));
        $apiName = trim((string) $request->query('api_name', ''));
        $status = strtolower(trim((string) $request->query('status', '')));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $allowedStatuses = ['success', 'failed', 'error'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $filteredQuery = DB::table('api_logs')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('api_name', 'like', "%{$keyword}%")
                        ->orWhere('endpoint', 'like', "%{$keyword}%")
                        ->orWhere('message', 'like', "%{$keyword}%")
                        ->orWhere('response_code', 'like', "%{$keyword}%");
                });
            })
            ->when($apiName !== '', function ($query) use ($apiName): void {
                $query->where('api_name', $apiName);
            })
            ->when($status !== '', function ($query) use ($status): void {
                if ($status === 'failed') {
                    $query->whereIn(
                        DB::raw('LOWER(status)'),
                        ['failed', 'failure']
                    );
                    return;
                }

                $query->whereRaw('LOWER(status) = ?', [$status]);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate(
                    DB::raw('COALESCE(requested_at, created_at)'),
                    '>=',
                    $dateFrom
                );
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate(
                    DB::raw('COALESCE(requested_at, created_at)'),
                    '<=',
                    $dateTo
                );
            });

        $logs = (clone $filteredQuery)
            ->orderByRaw('COALESCE(requested_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $apiNames = DB::table('api_logs')
            ->whereNotNull('api_name')
            ->where('api_name', '<>', '')
            ->distinct()
            ->orderBy('api_name')
            ->pluck('api_name');

        $statusRow = DB::table('api_logs')
            ->selectRaw(
                "SUM(CASE WHEN LOWER(status) = 'success' THEN 1 ELSE 0 END) as success_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(status) IN ('failed', 'failure') THEN 1 ELSE 0 END) as failed_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(status) = 'error' THEN 1 ELSE 0 END) as error_count"
            )
            ->first();

        $responseGroups = DB::table('api_logs')
            ->selectRaw(
                "SUM(CASE WHEN response_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as code_2xx"
            )
            ->selectRaw(
                "SUM(CASE WHEN response_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) as code_3xx"
            )
            ->selectRaw(
                "SUM(CASE WHEN response_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) as code_4xx"
            )
            ->selectRaw(
                "SUM(CASE WHEN response_code >= 500 THEN 1 ELSE 0 END) as code_5xx"
            )
            ->selectRaw(
                "SUM(CASE WHEN response_code IS NULL THEN 1 ELSE 0 END) as code_unknown"
            )
            ->first();

        $apiUsage = DB::table('api_logs')
            ->select(
                'api_name',
                DB::raw('COUNT(*) as request_count'),
                DB::raw(
                    "SUM(CASE WHEN LOWER(status) = 'success' THEN 1 ELSE 0 END) as success_count"
                )
            )
            ->groupBy('api_name')
            ->orderByDesc('request_count')
            ->limit(8)
            ->get();

        $statistics = [
            'total' => DB::table('api_logs')->count(),
            'filtered' => (clone $filteredQuery)->count(),
            'success' => (int) ($statusRow->success_count ?? 0),
            'failed' => (int) ($statusRow->failed_count ?? 0),
            'error' => (int) ($statusRow->error_count ?? 0),
            'last_request' => DB::table('api_logs')
                ->selectRaw('MAX(COALESCE(requested_at, created_at)) as last_request')
                ->value('last_request'),
        ];

        $responseCodeChart = [
            '2xx' => (int) ($responseGroups->code_2xx ?? 0),
            '3xx' => (int) ($responseGroups->code_3xx ?? 0),
            '4xx' => (int) ($responseGroups->code_4xx ?? 0),
            '5xx' => (int) ($responseGroups->code_5xx ?? 0),
            'unknown' => (int) ($responseGroups->code_unknown ?? 0),
        ];

        return view('supplyguard.admin-api-logs', compact(
            'logs',
            'apiNames',
            'apiUsage',
            'statistics',
            'responseCodeChart',
            'keyword',
            'apiName',
            'status',
            'dateFrom',
            'dateTo'
        ));
    }

    private function ensureAdmin(): void
    {
        if (
            !auth()->check()
            || auth()->user()->role !== 'admin'
        ) {
            abort(
                403,
                'Halaman ini hanya dapat diakses oleh administrator.'
            );
        }
    }
}
