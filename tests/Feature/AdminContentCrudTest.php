<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminContentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        DB::table('users')
            ->where('id', $admin->id)
            ->update(['role' => 'admin']);

        return $admin->refresh();
    }

    public function test_admin_can_update_user_profile_role_and_password(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'email' => 'lama@example.test',
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.update', $user->id), [
                'name' => 'Pengguna Diperbarui',
                'email' => 'baru@example.test',
                'role' => 'admin',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $updated = DB::table('users')->where('id', $user->id)->first();

        $this->assertSame('Pengguna Diperbarui', $updated->name);
        $this->assertSame('baru@example.test', $updated->email);
        $this->assertSame('admin', $updated->role);
        $this->assertTrue(Hash::check('password-baru', $updated->password));
    }

    public function test_active_admin_cannot_lower_own_role(): void
    {
        $admin = $this->createAdmin();

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.update', $admin->id), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'user',
            ])
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_create_update_and_delete_article(): void
    {
        $admin = $this->createAdmin();

        $this
            ->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'Artikel Awal',
                'category' => 'Logistik',
                'content' => 'Isi artikel awal.',
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $articleId = (int) DB::table('articles')
            ->where('title', 'Artikel Awal')
            ->value('id');

        $this
            ->actingAs($admin)
            ->patch(route('admin.articles.update', $articleId), [
                'title' => 'Artikel Diperbarui',
                'category' => 'Ekonomi',
                'content' => 'Isi artikel yang telah diperbarui.',
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('articles', [
            'id' => $articleId,
            'title' => 'Artikel Diperbarui',
            'category' => 'Ekonomi',
            'is_published' => 0,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.articles.destroy', $articleId))
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('articles', [
            'id' => $articleId,
        ]);
    }

    public function test_admin_can_update_positive_and_negative_words(): void
    {
        $admin = $this->createAdmin();

        $positiveId = (int) DB::table('positive_words')->insertGetId([
            'word' => 'baik',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $negativeId = (int) DB::table('negative_words')->insertGetId([
            'word' => 'buruk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.positiveWords.update', $positiveId), [
                'word' => 'stabil',
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this
            ->actingAs($admin)
            ->patch(route('admin.negativeWords.update', $negativeId), [
                'word' => 'krisis',
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('positive_words', [
            'id' => $positiveId,
            'word' => 'stabil',
        ]);

        $this->assertDatabaseHas('negative_words', [
            'id' => $negativeId,
            'word' => 'krisis',
        ]);
    }
}
