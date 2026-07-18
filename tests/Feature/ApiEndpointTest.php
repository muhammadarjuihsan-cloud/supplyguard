<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function createCountry(): int
    {
        return (int) DB::table('countries')->insertGetId([
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
    }

    public function test_countries_endpoint_returns_paginated_json(): void
    {
        $this->createCountry();

        $response = $this->getJson('/api/countries?q=Indonesia&per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('data.0.name', 'Indonesia')
            ->assertJsonStructure([
                'status',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to',
                ],
                'links' => [
                    'first',
                    'last',
                    'previous',
                    'next',
                ],
                'generated_at',
            ]);
    }

    public function test_risk_endpoint_returns_country_risk(): void
    {
        $countryId = $this->createCountry();

        DB::table('risk_scores')->insert([
            'country_id' => $countryId,
            'weather_score' => 10,
            'inflation_score' => 20,
            'currency_score' => 30,
            'news_score' => 40,
            'port_score' => 50,
            'total_score' => 28,
            'risk_level' => 'Low',
            'recommendation' => 'Risiko masih rendah.',
            'score_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/risk?country_id={$countryId}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.country_name', 'Indonesia')
            ->assertJsonPath('data.total_score', 28)
            ->assertJsonPath('data.risk_level', 'Low');
    }

    public function test_invalid_risk_level_returns_validation_error(): void
    {
        $this->getJson('/api/risk?risk_level=Salah')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_ports_endpoint_filters_country_and_paginates(): void
    {
        $countryId = $this->createCountry();

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

        $this->getJson("/api/ports?country_id={$countryId}&per_page=10")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Tanjung Priok')
            ->assertJsonPath('data.0.port_code', 'IDTPP');
    }

    public function test_news_endpoint_filters_sentiment(): void
    {
        $countryId = $this->createCountry();

        DB::table('news_cache')->insert([
            'country_id' => $countryId,
            'title' => 'Gangguan pelabuhan meningkat',
            'description' => 'Berita pengujian SupplyGuard.',
            'source_name' => 'SupplyGuard Test',
            'url' => 'https://example.test/news',
            'image_url' => null,
            'category' => 'Logistics',
            'sentiment' => 'Negative',
            'positive_score' => 0,
            'negative_score' => 2,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(
            "/api/news?country_id={$countryId}&sentiment=negative&per_page=10"
        )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sentiment', 'Negative');
    }

    public function test_invalid_news_sentiment_returns_validation_error(): void
    {
        $this->getJson('/api/news?sentiment=salah')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_currency_requires_country_id(): void
    {
        $this->getJson('/api/currency')
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Parameter country_id wajib diisi.'
            );
    }

    public function test_currency_endpoint_returns_current_rate_and_history(): void
    {
        $countryId = $this->createCountry();

        DB::table('currency_rates')->insert([
            'country_id' => $countryId,
            'base_currency' => 'USD',
            'target_currency' => 'IDR',
            'rate' => 16000,
            'change_percent' => 0.5,
            'currency_risk' => 20,
            'fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('currency_histories')->insert([
            'base_currency' => 'USD',
            'target_currency' => 'IDR',
            'rate' => 15950,
            'rate_date' => now()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(
            "/api/currency?country_id={$countryId}&history_limit=30"
        )
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.country.name', 'Indonesia')
            ->assertJsonPath('data.current.target_currency', 'IDR')
            ->assertJsonCount(1, 'data.history');
    }
}
