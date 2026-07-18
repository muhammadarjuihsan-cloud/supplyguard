<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk();
    }

    public function test_guest_can_view_register_page(): void
    {
        $this->get(route('register'))
            ->assertOk();
    }

    public function test_new_user_can_register(): void
    {
        $response = $this->post(route('register.process'), [
            'name' => 'Pengguna Uji',
            'email' => 'pengguna@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Pengguna Uji',
            'email' => 'pengguna@example.test',
            'role' => 'user',
        ]);
    }

    public function test_registered_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
        ]);

        $response = $this->post(route('login.process'), [
            'email' => 'login@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'salah@example.test',
        ]);

        $response = $this
            ->from(route('login'))
            ->post(route('login.process'), [
                'email' => 'salah@example.test',
                'password' => 'password-yang-salah',
            ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
