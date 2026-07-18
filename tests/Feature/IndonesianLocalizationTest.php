<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class IndonesianLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_available_in_indonesian(): void
    {
        App::setLocale('id');

        $response = $this
            ->from(route('register'))
            ->post(route('register.process'), [
                'name' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'name' => 'Kolom nama wajib diisi.',
                'email' => 'Kolom email wajib diisi.',
                'password' => 'Kolom kata sandi wajib diisi.',
            ]);
    }

    public function test_invalid_email_message_is_in_indonesian(): void
    {
        App::setLocale('id');

        $response = $this
            ->from(route('register'))
            ->post(route('register.process'), [
                'name' => 'Pengguna Uji',
                'email' => 'alamat-email-tidak-valid',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'email' => 'Kolom email harus berupa alamat email yang valid.',
            ]);
    }
}
