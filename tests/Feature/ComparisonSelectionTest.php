<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComparisonSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_automatically_selects_different_second_country(): void
    {
        $user = User::factory()->create();

        $countryAId = (int) DB::table('countries')->insertGetId([
            'name' => 'Indonesia',
            'cca2' => 'ID',
            'cca3' => 'IDN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countryBId = (int) DB::table('countries')->insertGetId([
            'name' => 'Malaysia',
            'cca2' => 'MY',
            'cca3' => 'MYS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('comparison', [
                'country_a' => $countryAId,
            ]))
            ->assertOk()
            ->assertViewHas('countryAId', $countryAId)
            ->assertViewHas('countryBId', $countryBId);
    }

    public function test_same_country_parameter_is_replaced_by_another_country(): void
    {
        $user = User::factory()->create();

        $countryAId = (int) DB::table('countries')->insertGetId([
            'name' => 'Indonesia',
            'cca2' => 'ID',
            'cca3' => 'IDN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countryBId = (int) DB::table('countries')->insertGetId([
            'name' => 'Thailand',
            'cca2' => 'TH',
            'cca3' => 'THA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('comparison', [
                'country_a' => $countryAId,
                'country_b' => $countryAId,
            ]))
            ->assertOk()
            ->assertViewHas('countryAId', $countryAId)
            ->assertViewHas('countryBId', $countryBId);
    }
}
