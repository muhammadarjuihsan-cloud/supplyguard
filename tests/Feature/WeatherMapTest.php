<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeatherMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_weather_page_contains_global_weather_map(): void
    {
        $user = User::factory()->create();

        $countryId = (int) DB::table('countries')->insertGetId([
            'name' => 'Indonesia',
            'official_name' => 'Republic of Indonesia',
            'cca2' => 'ID',
            'cca3' => 'IDN',
            'region' => 'Asia',
            'currency_code' => 'IDR',
            'latitude' => -5.0,
            'longitude' => 120.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('weather_cache')->insert([
            'country_id' => $countryId,
            'temperature' => 29.5,
            'rainfall' => 3.2,
            'wind_speed' => 14.0,
            'weather_status' => 'Hujan ringan',
            'weather_risk' => 35,
            'fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Peta Cuaca Global')
            ->assertSee('weatherGlobalMap')
            ->assertSee('Indonesia');
    }
}
