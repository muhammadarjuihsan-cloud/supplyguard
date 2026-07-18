<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WatchlistTest extends TestCase
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

    public function test_user_can_add_country_to_watchlist(): void
    {
        $user = User::factory()->create();
        $countryId = $this->createCountry();

        $this
            ->actingAs($user)
            ->from(route('watchlist.index'))
            ->post(route('watchlist.store'), [
                'country_id' => $countryId,
                'note' => '  Pantau risiko mingguan  ',
            ])
            ->assertRedirect(route('watchlist.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('watchlists', [
            'user_id' => $user->id,
            'country_id' => $countryId,
            'note' => 'Pantau risiko mingguan',
        ]);
    }

    public function test_duplicate_country_is_not_added_twice(): void
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

        $this
            ->actingAs($user)
            ->from(route('watchlist.index'))
            ->post(route('watchlist.store'), [
                'country_id' => $countryId,
            ])
            ->assertRedirect(route('watchlist.index'))
            ->assertSessionHas('error');

        $this->assertSame(
            1,
            DB::table('watchlists')
                ->where('user_id', $user->id)
                ->where('country_id', $countryId)
                ->count()
        );
    }

    public function test_user_can_update_watchlist_note(): void
    {
        $user = User::factory()->create();
        $countryId = $this->createCountry();

        DB::table('watchlists')->insert([
            'user_id' => $user->id,
            'country_id' => $countryId,
            'note' => 'Catatan lama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->from(route('watchlist.index'))
            ->patch(route('watchlist.note.update', $countryId), [
                'note' => 'Catatan baru',
            ])
            ->assertRedirect(route('watchlist.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('watchlists', [
            'user_id' => $user->id,
            'country_id' => $countryId,
            'note' => 'Catatan baru',
        ]);
    }

    public function test_user_can_remove_country_from_watchlist(): void
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

        $this
            ->actingAs($user)
            ->from(route('watchlist.index'))
            ->delete(route('watchlist.destroyByCountry', $countryId))
            ->assertRedirect(route('watchlist.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('watchlists', [
            'user_id' => $user->id,
            'country_id' => $countryId,
        ]);
    }
}
