<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncPorts extends Command
{
    protected $signature = 'supplyguard:sync-ports
                            {--limit=0 : Maksimum pelabuhan yang diproses, 0 berarti semua}
                            {--batch=1000 : Jumlah data yang diminta per request, maksimum 2000}
                            {--insecure : Nonaktifkan verifikasi SSL hanya jika sertifikat lokal bermasalah}';

    protected $description = 'Sinkronisasi data pelabuhan global dari World Port Index ke tabel ports';

    private const ENDPOINT = 'https://services9.arcgis.com/j1CY4yzWfwptbTWN/arcgis/rest/services/WorldPortIndex_WFL1/FeatureServer/0/query';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $batch = min(2000, max(1, (int) $this->option('batch')));

        $http = Http::acceptJson()
            ->connectTimeout(20)
            ->timeout(90);

        if ((bool) $this->option('insecure')) {
            $http = $http->withOptions(['verify' => false]);
            $this->warn('Verifikasi SSL dinonaktifkan untuk proses ini.');
        }

        $countries = DB::table('countries')
            ->whereNotNull('cca2')
            ->get(['id', 'name', 'cca2'])
            ->keyBy(fn (object $country): string => strtoupper(trim((string) $country->cca2)));

        if ($countries->isEmpty()) {
            $this->error('Tabel countries belum memiliki data kode negara cca2.');

            return self::FAILURE;
        }

        $existingByCode = [];
        $existingByName = [];

        DB::table('ports')
            ->get(['id', 'country_id', 'name', 'port_code'])
            ->each(function (object $port) use (&$existingByCode, &$existingByName): void {
                $code = strtoupper(trim((string) $port->port_code));

                if ($code !== '') {
                    $existingByCode[$code] = (int) $port->id;
                }

                $nameKey = $this->makeNameKey(
                    (int) $port->country_id,
                    (string) $port->name
                );

                if ($nameKey !== '') {
                    $existingByName[$nameKey] = (int) $port->id;
                }
            });

        $this->newLine();
        $this->info('Sinkronisasi pelabuhan global dimulai.');
        $this->line('Sumber: World Port Index (ArcGIS Feature Service)');
        $this->line('Batas proses: '.($limit === 0 ? 'semua data' : $limit.' data'));
        $this->line('Ukuran batch: '.$batch.' data per request');
        $this->newLine();

        $offset = 0;
        $processed = 0;
        $requestsSucceeded = 0;
        $requestsFailed = 0;
        $created = 0;
        $updated = 0;
        $skippedCountry = 0;
        $skippedInvalid = 0;

        while (true) {
            $remaining = $limit > 0 ? $limit - $processed : $batch;

            if ($limit > 0 && $remaining <= 0) {
                break;
            }

            $requestSize = $limit > 0 ? min($batch, $remaining) : $batch;

            try {
                $response = $this->requestPorts(
                    http: $http,
                    offset: $offset,
                    recordCount: $requestSize
                );
            } catch (Throwable $exception) {
                $requestsFailed++;
                $this->error('Request API gagal: '.$exception->getMessage());
                break;
            }

            if ($response->failed()) {
                $requestsFailed++;
                $this->error('Request API gagal dengan HTTP '.$response->status().'.');
                break;
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                $requestsFailed++;
                $this->error('Respons API bukan JSON yang valid.');
                break;
            }

            if (isset($payload['error'])) {
                $requestsFailed++;
                $message = (string) ($payload['error']['message'] ?? 'Kesalahan tidak diketahui dari ArcGIS.');
                $this->error('ArcGIS menolak request: '.$message);
                break;
            }

            $features = $payload['features'] ?? [];

            if (! is_array($features) || $features === []) {
                break;
            }

            $requestsSucceeded++;
            $fetchedThisRequest = count($features);

            DB::transaction(function () use (
                $features,
                $countries,
                &$existingByCode,
                &$existingByName,
                &$created,
                &$updated,
                &$skippedCountry,
                &$skippedInvalid
            ): void {
                foreach ($features as $feature) {
                    $attributes = is_array($feature['attributes'] ?? null)
                        ? $feature['attributes']
                        : [];

                    $portName = trim((string) ($attributes['PORT_NAME'] ?? ''));
                    $countryCode = strtoupper(trim((string) ($attributes['COUNTRY'] ?? '')));
                    $latitude = $attributes['LATITUDE'] ?? null;
                    $longitude = $attributes['LONGITUDE'] ?? null;

                    if (
                        $portName === ''
                        || ! is_numeric($latitude)
                        || ! is_numeric($longitude)
                        || (float) $latitude < -90
                        || (float) $latitude > 90
                        || (float) $longitude < -180
                        || (float) $longitude > 180
                    ) {
                        $skippedInvalid++;
                        continue;
                    }

                    $country = $countries->get($countryCode);

                    if ($country === null) {
                        $skippedCountry++;
                        continue;
                    }

                    $indexNumber = $attributes['INDEX_NO'] ?? $attributes['OBJECTID'] ?? null;

                    if (! is_numeric($indexNumber)) {
                        $skippedInvalid++;
                        continue;
                    }

                    $portCode = $this->makePortCode((int) $indexNumber);
                    $type = $this->harborTypeLabel((string) ($attributes['HARBORTYPE'] ?? ''));
                    $size = $this->harborSizeLabel((string) ($attributes['HARBORSIZE'] ?? ''));
                    $nameKey = $this->makeNameKey((int) $country->id, $portName);
                    $now = now();

                    $data = [
                        'country_id' => (int) $country->id,
                        'name' => $portName,
                        'country_name' => (string) $country->name,
                        'type' => $type,
                        'latitude' => (float) $latitude,
                        'longitude' => (float) $longitude,
                        'description' => sprintf(
                            'Sumber: World Port Index. Ukuran: %s. Jenis: %s. Indeks WPI: %d.',
                            $size,
                            $type,
                            (int) $indexNumber
                        ),
                        'updated_at' => $now,
                    ];

                    $existingId = $existingByCode[$portCode]
                        ?? ($nameKey !== '' ? ($existingByName[$nameKey] ?? null) : null);

                    if ($existingId !== null) {
                        DB::table('ports')
                            ->where('id', $existingId)
                            ->update($data);

                        $existingByCode[$portCode] = (int) $existingId;

                        if ($nameKey !== '') {
                            $existingByName[$nameKey] = (int) $existingId;
                        }

                        $updated++;
                        continue;
                    }

                    $newId = DB::table('ports')->insertGetId([
                        ...$data,
                        'port_code' => $portCode,
                        'created_at' => $now,
                    ]);

                    $existingByCode[$portCode] = (int) $newId;

                    if ($nameKey !== '') {
                        $existingByName[$nameKey] = (int) $newId;
                    }

                    $created++;
                }
            });

            $processed += $fetchedThisRequest;
            $offset += $fetchedThisRequest;

            $this->line(sprintf(
                'Request %d selesai: %d data diterima | total diproses %d',
                $requestsSucceeded,
                $fetchedThisRequest,
                $processed
            ));

            if ($fetchedThisRequest < $requestSize) {
                break;
            }
        }

        $totalPorts = DB::table('ports')->count();
        $countriesWithPorts = DB::table('ports')
            ->whereNotNull('country_id')
            ->distinct()
            ->count('country_id');

        $this->newLine();
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Data API diproses', $processed],
                ['Request API berhasil', $requestsSucceeded],
                ['Request API gagal', $requestsFailed],
                ['Pelabuhan baru', $created],
                ['Pelabuhan diperbarui', $updated],
                ['Dilewati: negara tidak cocok', $skippedCountry],
                ['Dilewati: data tidak valid', $skippedInvalid],
                ['Total ports', $totalPorts],
                ['Negara memiliki pelabuhan', $countriesWithPorts],
            ]
        );

        if ($requestsSucceeded === 0) {
            $this->error('Tidak ada request pelabuhan yang berhasil.');

            return self::FAILURE;
        }

        $this->info('Sinkronisasi pelabuhan selesai.');

        return self::SUCCESS;
    }

    private function requestPorts(PendingRequest $http, int $offset, int $recordCount)
    {
        return $http->get(self::ENDPOINT, [
            'where' => '1=1',
            'outFields' => 'OBJECTID,INDEX_NO,PORT_NAME,COUNTRY,LATITUDE,LONGITUDE,HARBORSIZE,HARBORTYPE',
            'returnGeometry' => 'false',
            'orderByFields' => 'OBJECTID ASC',
            'resultOffset' => $offset,
            'resultRecordCount' => $recordCount,
            'f' => 'json',
        ]);
    }

    private function makePortCode(int $indexNumber): string
    {
        return 'WPI'.str_pad((string) $indexNumber, 5, '0', STR_PAD_LEFT);
    }

    private function makeNameKey(int $countryId, string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/^port\s+of\s+/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/i', '', $normalized) ?? '';

        return $normalized === '' ? '' : $countryId.'|'.$normalized;
    }

    private function harborSizeLabel(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'L' => 'Besar',
            'M' => 'Menengah',
            'S' => 'Kecil',
            'V' => 'Sangat kecil',
            default => 'Tidak diketahui',
        };
    }

    private function harborTypeLabel(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'CN' => 'Pelabuhan pesisir alami',
            'CB' => 'Pelabuhan pemecah gelombang',
            'CT' => 'Pelabuhan pesisir berpintu pasang',
            'LC' => 'Pelabuhan danau atau kanal',
            'OR' => 'Pelabuhan terbuka',
            'RB' => 'Pelabuhan cekungan sungai',
            'RN' => 'Pelabuhan sungai alami',
            'RT' => 'Pelabuhan sungai berpintu pasang',
            'TH' => 'Pelabuhan perlindungan topan',
            default => 'Pelabuhan laut',
        };
    }
}
