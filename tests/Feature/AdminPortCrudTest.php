<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPortCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        DB::table('users')
            ->where('id', $admin->id)
            ->update(['role' => 'admin']);

        $admin->refresh();

        return $admin;
    }

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

    public function test_admin_can_create_update_and_delete_port(): void
    {
        $admin = $this->createAdmin();
        $countryId = $this->createCountry();

        $this
            ->actingAs($admin)
            ->post(route('admin.ports.store'), [
                'country_id' => $countryId,
                'name' => 'Pelabuhan Uji',
                'port_code' => 'IDTST',
                'type' => 'Seaport',
                'latitude' => -6.1,
                'longitude' => 106.8,
                'description' => 'Data untuk pengujian otomatis.',
            ])
            ->assertRedirect(route('admin.ports.index'))
            ->assertSessionHas('success');

        $portId = (int) DB::table('ports')
            ->where('port_code', 'IDTST')
            ->value('id');

        $this->assertGreaterThan(0, $portId);

        $this
            ->actingAs($admin)
            ->patch(route('admin.ports.update', $portId), [
                'country_id' => $countryId,
                'name' => 'Pelabuhan Uji Diperbarui',
                'port_code' => 'IDTST',
                'type' => 'International Seaport',
                'latitude' => -6.2,
                'longitude' => 106.9,
                'description' => 'Data telah diperbarui.',
            ])
            ->assertRedirect(route('admin.ports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ports', [
            'id' => $portId,
            'name' => 'Pelabuhan Uji Diperbarui',
            'country_name' => 'Indonesia',
            'port_code' => 'IDTST',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.ports.destroy', $portId))
            ->assertRedirect(route('admin.ports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ports', [
            'id' => $portId,
        ]);
    }
}
