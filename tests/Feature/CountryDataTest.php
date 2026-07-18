<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CountryDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_country_dataset(): void
    {
        $user = User::factory()->create();

        $countryId = (int) DB::table('countries')->insertGetId([
            'name' => 'Indonesia',
            'official_name' => 'Republic of Indonesia',
            'cca2' => 'ID',
            'cca3' => 'IDN',
            'capital' => 'Jakarta',
            'region' => 'Asia',
            'subregion' => 'South-Eastern Asia',
            'currency_code' => 'IDR',
            'currency_name' => 'Indonesian rupiah',
            'language' => 'Indonesian',
            'latitude' => -5.0,
            'longitude' => 120.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('risk_scores')->insert([
            'country_id' => $countryId,
            'weather_score' => 10,
            'inflation_score' => 20,
            'currency_score' => 30,
            'news_score' => 40,
            'port_score' => 50,
            'total_score' => 42,
            'risk_level' => 'Medium',
            'recommendation' => 'Pantau indikator risiko.',
            'score_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ports')->insert([
            'country_id' => $countryId,
            'name' => 'Tanjung Priok',
            'country_name' => 'Indonesia',
            'port_code' => 'IDTPP',
            'type' => 'Seaport',
            'latitude' => -6.1045,
            'longitude' => 106.8808,
            'description' => 'Pelabuhan pengujian.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('news_cache')->insert([
            'country_id' => $countryId,
            'title' => 'Berita logistik Indonesia',
            'description' => 'Berita pengujian.',
            'source_name' => 'SupplyGuard Test',
            'url' => 'https://example.test/indonesia',
            'category' => 'Logistics',
            'sentiment' => 'Neutral',
            'positive_score' => 0,
            'negative_score' => 0,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('countries.index', [
                'q' => 'Indonesia',
                'region' => 'Asia',
            ]))
            ->assertOk()
            ->assertSee('Data Negara Global')
            ->assertSee('Memiliki Berita')
            ->assertSee('Indonesia')
            ->assertSee('IDN')
            ->assertSee('Jakarta')
            ->assertSee('Sedang')
            ->assertSee('Analisis');
    }

    public function test_country_dataset_can_sort_by_port_count(): void
    {
        $user = User::factory()->create();

        $firstCountryId = (int) DB::table('countries')->insertGetId([
            'name' => 'Negara Sedikit Port',
            'cca2' => 'AA',
            'cca3' => 'AAA',
            'region' => 'Asia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondCountryId = (int) DB::table('countries')->insertGetId([
            'name' => 'Negara Banyak Port',
            'cca2' => 'BB',
            'cca3' => 'BBB',
            'region' => 'Asia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ports')->insert([
            [
                'country_id' => $firstCountryId,
                'name' => 'Port A',
                'country_name' => 'Negara Sedikit Port',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_id' => $secondCountryId,
                'name' => 'Port B',
                'country_name' => 'Negara Banyak Port',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_id' => $secondCountryId,
                'name' => 'Port C',
                'country_name' => 'Negara Banyak Port',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('countries.index', ['sort' => 'ports_desc']))
            ->assertOk();

        $response->assertSeeInOrder([
            'Negara Banyak Port',
            'Negara Sedikit Port',
        ]);
    }

    public function test_guest_is_redirected_from_country_dataset(): void
    {
        $this
            ->get(route('countries.index'))
            ->assertRedirect(route('login'));
    }
}
