<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createCountry(): int
    {
        return (int) DB::table('countries')->insertGetId([
            'name' => 'Indonesia',
            'official_name' => 'Republic of Indonesia',
            'cca2' => 'ID',
            'cca3' => 'IDN',
            'region' => 'Asia',
            'currency_code' => 'IDR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_watchlist_rejects_duplicate_user_and_country(): void
    {
        $user = User::factory()->create();
        $countryId = $this->createCountry();

        DB::table('watchlists')->insert([
            'user_id' => $user->id,
            'country_id' => $countryId,
            'note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('watchlists')->insert([
            'user_id' => $user->id,
            'country_id' => $countryId,
            'note' => 'Duplikat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_risk_scores_reject_duplicate_country(): void
    {
        $countryId = $this->createCountry();

        $payload = [
            'country_id' => $countryId,
            'weather_score' => 10,
            'inflation_score' => 20,
            'currency_score' => 30,
            'news_score' => 40,
            'port_score' => 50,
            'total_score' => 28,
            'risk_level' => 'Low',
            'recommendation' => 'Risiko rendah.',
            'score_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('risk_scores')->insert($payload);

        $this->expectException(QueryException::class);

        DB::table('risk_scores')->insert($payload);
    }
}
