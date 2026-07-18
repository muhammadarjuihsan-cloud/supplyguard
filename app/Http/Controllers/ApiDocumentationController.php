<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ApiDocumentationController extends Controller
{
    /**
     * Menampilkan dokumentasi dan penguji REST API SupplyGuard.
     */
    public function index()
    {
        $countries = DB::table('countries')
            ->select('id', 'name', 'cca2', 'cca3')
            ->orderBy('name')
            ->get();

        $statistics = [
            'countries' => DB::table('countries')->count(),
            'risk_scores' => DB::table('risk_scores')->count(),
            'ports' => DB::table('ports')->count(),
            'news' => DB::table('news_cache')->count(),
            'currencies' => DB::table('currency_rates')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id'),
        ];

        $paginationParameters = [
            [
                'name' => 'page',
                'type' => 'integer',
                'required' => false,
                'description' => 'Nomor halaman yang ingin diambil.',
            ],
            [
                'name' => 'per_page',
                'type' => 'integer',
                'required' => false,
                'description' => 'Jumlah data per halaman. Maksimal 100.',
            ],
        ];

        $endpoints = [
            [
                'key' => 'countries',
                'method' => 'GET',
                'path' => '/api/countries',
                'title' => 'Daftar Negara',
                'description' => 'Mengambil negara dengan pencarian, filter wilayah, dan pagination.',
                'parameters' => array_merge([
                    [
                        'name' => 'q',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Cari nama atau kode negara.',
                    ],
                    [
                        'name' => 'region',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter berdasarkan wilayah.',
                    ],
                ], $paginationParameters),
                'example' => '/api/countries?q=Indonesia&per_page=20',
            ],
            [
                'key' => 'risk',
                'method' => 'GET',
                'path' => '/api/risk',
                'title' => 'Skor Risiko Negara',
                'description' => 'Mengambil satu skor berdasarkan country_id atau daftar skor dengan pagination.',
                'parameters' => array_merge([
                    [
                        'name' => 'country_id',
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'ID negara untuk mengambil satu skor risiko.',
                    ],
                    [
                        'name' => 'risk_level',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Low, Medium, atau High.',
                    ],
                    [
                        'name' => 'q',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Cari nama atau kode negara.',
                    ],
                ], $paginationParameters),
                'example' => '/api/risk?country_id=3',
            ],
            [
                'key' => 'ports',
                'method' => 'GET',
                'path' => '/api/ports',
                'title' => 'Data Pelabuhan',
                'description' => 'Mengambil pelabuhan berdasarkan negara, tipe, kata kunci, dan pagination.',
                'parameters' => array_merge([
                    [
                        'name' => 'country_id',
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Filter berdasarkan ID negara.',
                    ],
                    [
                        'name' => 'q',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Cari nama, kode, negara, atau deskripsi.',
                    ],
                    [
                        'name' => 'type',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter tipe pelabuhan.',
                    ],
                ], $paginationParameters),
                'example' => '/api/ports?country_id=3&q=Tanjung&per_page=20',
            ],
            [
                'key' => 'news',
                'method' => 'GET',
                'path' => '/api/news',
                'title' => 'Berita Negara',
                'description' => 'Mengambil berita berdasarkan negara, sentimen, kata kunci, dan pagination.',
                'parameters' => array_merge([
                    [
                        'name' => 'country_id',
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Filter berita berdasarkan ID negara.',
                    ],
                    [
                        'name' => 'sentiment',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'positive, neutral, atau negative.',
                    ],
                    [
                        'name' => 'q',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Cari judul, isi, sumber, atau negara.',
                    ],
                ], [
                    $paginationParameters[0],
                    [
                        'name' => 'per_page',
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Jumlah data per halaman. Maksimal 50.',
                    ],
                ]),
                'example' => '/api/news?country_id=3&sentiment=negative&per_page=10',
            ],
            [
                'key' => 'currency',
                'method' => 'GET',
                'path' => '/api/currency',
                'title' => 'Mata Uang dan Riwayat',
                'description' => 'Mengambil nilai tukar terbaru serta riwayat nilai tukar satu negara.',
                'parameters' => [
                    [
                        'name' => 'country_id',
                        'type' => 'integer',
                        'required' => true,
                        'description' => 'ID negara yang nilai tukarnya ingin dilihat.',
                    ],
                    [
                        'name' => 'history_limit',
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Jumlah riwayat, maksimal 365. Default 30.',
                    ],
                ],
                'example' => '/api/currency?country_id=3&history_limit=30',
            ],
        ];

        return view('supplyguard.api-documentation', compact(
            'countries',
            'statistics',
            'endpoints'
        ));
    }
}
