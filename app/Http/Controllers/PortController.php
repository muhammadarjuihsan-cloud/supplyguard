<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortController extends Controller
{
    /**
     * Menampilkan halaman lokasi pelabuhan global.
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $countryId = $request->integer('country_id');
        $type = trim((string) $request->query('type', ''));

        $filteredQuery = DB::table('ports')
            ->leftJoin('countries', 'countries.id', '=', 'ports.country_id')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('ports.name', 'like', "%{$keyword}%")
                        ->orWhere('ports.port_code', 'like', "%{$keyword}%")
                        ->orWhere('ports.country_name', 'like', "%{$keyword}%")
                        ->orWhere('countries.name', 'like', "%{$keyword}%");
                });
            })
            ->when($countryId > 0, function ($query) use ($countryId) {
                $query->where('ports.country_id', $countryId);
            })
            ->when($type !== '', function ($query) use ($type) {
                $query->where('ports.type', $type);
            });

        $ports = (clone $filteredQuery)
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
            ->orderBy('ports.name')
            ->paginate(25)
            ->withQueryString();

        $mapPorts = (clone $filteredQuery)
            ->select(
                'ports.id',
                'ports.name',
                'ports.country_name',
                'ports.port_code',
                'ports.type',
                'ports.latitude',
                'ports.longitude'
            )
            ->whereNotNull('ports.latitude')
            ->whereNotNull('ports.longitude')
            ->orderBy('ports.name')
            ->get()
            ->map(static function ($port): array {
                return [
                    'id' => (int) $port->id,
                    'name' => (string) $port->name,
                    'country_name' => (string) ($port->country_name ?? '-'),
                    'port_code' => (string) ($port->port_code ?? '-'),
                    'type' => (string) ($port->type ?? '-'),
                    'latitude' => (float) $port->latitude,
                    'longitude' => (float) $port->longitude,
                ];
            })
            ->values();

        $countries = DB::table('countries')
            ->join('ports', 'ports.country_id', '=', 'countries.id')
            ->select('countries.id', 'countries.name', 'countries.cca2')
            ->distinct()
            ->orderBy('countries.name')
            ->get();

        $types = DB::table('ports')
            ->whereNotNull('type')
            ->where('type', '<>', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $statistics = [
            'total_ports' => DB::table('ports')->count(),
            'countries_with_ports' => DB::table('ports')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
            'filtered_ports' => (clone $filteredQuery)->count('ports.id'),
            'mapped_ports' => $mapPorts->count(),
        ];

        return view('supplyguard.port-location', compact(
            'ports',
            'mapPorts',
            'countries',
            'types',
            'statistics',
            'keyword',
            'countryId',
            'type'
        ));
    }
}
