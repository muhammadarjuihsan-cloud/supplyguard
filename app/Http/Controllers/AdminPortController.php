<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPortController extends Controller
{
    /**
     * Menampilkan pengelolaan dataset pelabuhan dengan pagination.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $keyword = trim((string) $request->query('q', ''));
        $countryId = $request->integer('country_id');
        $type = trim((string) $request->query('type', ''));

        $filteredQuery = DB::table('ports')
            ->leftJoin(
                'countries',
                'ports.country_id',
                '=',
                'countries.id'
            )
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('ports.name', 'like', "%{$keyword}%")
                        ->orWhere('ports.port_code', 'like', "%{$keyword}%")
                        ->orWhere('ports.country_name', 'like', "%{$keyword}%")
                        ->orWhere('countries.name', 'like', "%{$keyword}%")
                        ->orWhere('ports.description', 'like', "%{$keyword}%");
                });
            })
            ->when($countryId > 0, function ($query) use ($countryId): void {
                $query->where('ports.country_id', $countryId);
            })
            ->when($type !== '', function ($query) use ($type): void {
                $query->where('ports.type', $type);
            });

        $ports = (clone $filteredQuery)
            ->select(
                'ports.*',
                'countries.name as linked_country',
                'countries.cca2',
                'countries.cca3'
            )
            ->orderBy('ports.name')
            ->paginate(30)
            ->withQueryString();

        $countries = DB::table('countries')
            ->select('id', 'name', 'cca2', 'cca3')
            ->orderBy('name')
            ->get();

        $types = DB::table('ports')
            ->whereNotNull('type')
            ->where('type', '<>', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $statistics = [
            'total' => DB::table('ports')->count(),
            'filtered' => (clone $filteredQuery)->count('ports.id'),
            'countries' => DB::table('ports')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
            'with_coordinates' => DB::table('ports')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count(),
            'without_country' => DB::table('ports')
                ->whereNull('country_id')
                ->count(),
        ];

        return view('supplyguard.admin-ports', compact(
            'ports',
            'countries',
            'types',
            'statistics',
            'keyword',
            'countryId',
            'type'
        ));
    }

    /**
     * Menambahkan data pelabuhan.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();

        $validated = $this->validatePort($request);

        DB::table('ports')->insert(
            array_merge(
                $this->portPayload($validated),
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            )
        );

        return redirect()
            ->route('admin.ports.index')
            ->with('success', 'Data pelabuhan berhasil ditambahkan.');
    }

    /**
     * Memperbarui data pelabuhan.
     */
    public function update(Request $request, int $id)
    {
        $this->ensureAdmin();

        $exists = DB::table('ports')->where('id', $id)->exists();

        if (!$exists) {
            return redirect()
                ->route('admin.ports.index')
                ->with('error', 'Data pelabuhan tidak ditemukan.');
        }

        $validated = $this->validatePort($request);

        DB::table('ports')
            ->where('id', $id)
            ->update(
                array_merge(
                    $this->portPayload($validated),
                    ['updated_at' => now()]
                )
            );

        return redirect()
            ->route('admin.ports.index')
            ->with('success', 'Data pelabuhan berhasil diperbarui.');
    }

    /**
     * Menghapus data pelabuhan.
     */
    public function destroy(int $id)
    {
        $this->ensureAdmin();

        $deleted = DB::table('ports')
            ->where('id', $id)
            ->delete();

        if ($deleted === 0) {
            return redirect()
                ->route('admin.ports.index')
                ->with('error', 'Data pelabuhan tidak ditemukan.');
        }

        return redirect()
            ->route('admin.ports.index')
            ->with('success', 'Data pelabuhan berhasil dihapus.');
    }

    private function validatePort(Request $request): array
    {
        return $request->validate([
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'port_code' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function portPayload(array $validated): array
    {
        $countryId = !empty($validated['country_id'])
            ? (int) $validated['country_id']
            : null;

        $countryName = $countryId !== null
            ? DB::table('countries')
                ->where('id', $countryId)
                ->value('name')
            : null;

        return [
            'country_id' => $countryId,
            'name' => trim($validated['name']),
            'country_name' => $countryName,
            'port_code' => !empty($validated['port_code'])
                ? strtoupper(trim($validated['port_code']))
                : null,
            'type' => !empty($validated['type'])
                ? trim($validated['type'])
                : null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'description' => !empty($validated['description'])
                ? trim($validated['description'])
                : null,
        ];
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
