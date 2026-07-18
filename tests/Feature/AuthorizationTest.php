<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_admin_ports(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.ports.index'))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_api_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.apiLogs.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_access_admin_pages(): void
    {
        $admin = User::factory()->create();

        DB::table('users')
            ->where('id', $admin->id)
            ->update(['role' => 'admin']);

        $admin->refresh();

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.ports.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.apiLogs.index'))
            ->assertOk();
    }
}
