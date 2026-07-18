<?php

namespace App\Http\Controllers;

use App\Services\RiskScoringService;
use App\Services\SentimentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AdminController extends Controller
{
    private function ensureAdmin(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Halaman ini hanya dapat diakses oleh administrator.');
        }
    }

    public function index()
    {
        $this->ensureAdmin();

        $stats = [
            'users' => DB::table('users')->count(),
            'countries' => DB::table('countries')->count(),
            'ports' => DB::table('ports')->count(),
            'articles' => DB::table('articles')->count(),
            'positive_words' => DB::table('positive_words')->count(),
            'negative_words' => DB::table('negative_words')->count(),
        ];

        $automation = [
            'risk_country_count' => DB::table('risk_scores')
                ->distinct()
                ->count('country_id'),

            'risk_total_rows' => DB::table('risk_scores')->count(),

            'risk_last_updated' => DB::table('risk_scores')
                ->max('updated_at'),

            'news_total' => DB::table('news_cache')->count(),

            'news_analyzed' => DB::table('news_cache')
                ->whereNotNull('sentiment')
                ->count(),

            'sentiment_last_updated' => DB::table('news_cache')
                ->whereNotNull('sentiment')
                ->max('updated_at'),
        ];

        $users = DB::table('users')
            ->orderByDesc('created_at')
            ->get();

        $countries = DB::table('countries')
            ->orderBy('name')
            ->get();

        $articles = DB::table('articles')
            ->leftJoin('users', 'articles.user_id', '=', 'users.id')
            ->select('articles.*', 'users.name as author_name')
            ->orderByDesc('articles.created_at')
            ->get();

        $positiveWords = DB::table('positive_words')
            ->orderBy('word')
            ->get();

        $negativeWords = DB::table('negative_words')
            ->orderBy('word')
            ->get();

        return view('supplyguard.admin', compact(
            'stats',
            'automation',
            'users',
            'countries',
            'articles',
            'positiveWords',
            'negativeWords'
        ));
    }

    /**
     * Menjalankan ulang analisis sentimen seluruh berita.
     */
    public function reanalyzeSentiment(
        SentimentService $sentimentService
    ) {
        $this->ensureAdmin();

        $startedAt = microtime(true);

        try {
            $sentimentService->refreshLexicons();
            $summary = $sentimentService->updateAllNews();

            $duration = round(microtime(true) - $startedAt, 2);

            return redirect()
                ->route('admin.index')
                ->with(
                    'success',
                    sprintf(
                        'Analisis sentimen selesai. %d dari %d berita berhasil diperbarui dalam %s detik.',
                        $summary['updated'],
                        $summary['processed'],
                        number_format($duration, 2, ',', '.')
                    )
                )
                ->with('admin_process_summary', [
                    'type' => 'sentiment',
                    'processed' => $summary['processed'],
                    'success' => $summary['updated'],
                    'failed' => $summary['failed'],
                    'positive' => $summary['positive'],
                    'neutral' => $summary['neutral'],
                    'negative' => $summary['negative'],
                    'duration' => $duration,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.index')
                ->with(
                    'error',
                    'Analisis sentimen gagal: ' . $exception->getMessage()
                );
        }
    }

    /**
     * Menghitung ulang risiko seluruh negara.
     */
    public function recalculateRisk(
        RiskScoringService $riskScoringService
    ) {
        $this->ensureAdmin();

        $startedAt = microtime(true);

        try {
            $summary = $riskScoringService->calculateAll();

            $duration = round(microtime(true) - $startedAt, 2);

            return redirect()
                ->route('admin.index')
                ->with(
                    'success',
                    sprintf(
                        'Perhitungan risiko selesai. %d dari %d negara berhasil dihitung dalam %s detik.',
                        $summary['saved'],
                        $summary['processed'],
                        number_format($duration, 2, ',', '.')
                    )
                )
                ->with('admin_process_summary', [
                    'type' => 'risk',
                    'processed' => $summary['processed'],
                    'success' => $summary['saved'],
                    'failed' => $summary['failed'],
                    'low' => $summary['low'],
                    'medium' => $summary['medium'],
                    'high' => $summary['high'],
                    'duration' => $duration,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.index')
                ->with(
                    'error',
                    'Perhitungan risiko gagal: ' . $exception->getMessage()
                );
        }
    }


    /**
     * Memperbarui profil, peran, dan kata sandi pengguna.
     */
    public function updateUser(Request $request, int $id)
    {
        $this->ensureAdmin();

        $user = DB::table('users')->where('id', $id)->first();

        if (!$user) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Pengguna tidak ditemukan.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $role = $validated['role'] ?? $user->role;

        /*
         * Administrator tidak boleh menurunkan peran akun yang sedang
         * dipakai karena dapat mengunci akses ke halaman admin.
         */
        if ($id === (int) auth()->id()) {
            $role = $user->role;
        }

        $payload = [
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'role' => $role,
            'updated_at' => now(),
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        DB::table('users')
            ->where('id', $id)
            ->update($payload);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function updateUserRole(Request $request, int $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        if ($id === (int) auth()->id()) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Peran akun yang sedang digunakan tidak dapat diubah.');
        }

        $updated = DB::table('users')
            ->where('id', $id)
            ->update([
                'role' => $request->string('role')->toString(),
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Pengguna tidak ditemukan atau perannya tidak berubah.');
        }

        return redirect()
            ->route('admin.index')
            ->with('success', 'Peran pengguna berhasil diperbarui.');
    }

    public function destroyUser(int $id)
    {
        $this->ensureAdmin();

        if ($id === (int) auth()->id()) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Kamu tidak dapat menghapus akun yang sedang digunakan.');
        }

        DB::table('users')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }

    public function storePort(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'country_id' => ['nullable', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'port_code' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $countryName = null;

        if (!empty($validated['country_id'])) {
            $countryName = DB::table('countries')
                ->where('id', $validated['country_id'])
                ->value('name');
        }

        DB::table('ports')->insert([
            'country_id' => $validated['country_id'] ?? null,
            'name' => trim($validated['name']),
            'country_name' => $countryName,
            'port_code' => !empty($validated['port_code'])
                ? strtoupper(trim($validated['port_code']))
                : null,
            'type' => !empty($validated['type'])
                ? trim($validated['type'])
                : null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'description' => !empty($validated['description'])
                ? trim($validated['description'])
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Data pelabuhan berhasil ditambahkan.');
    }

    public function destroyPort(int $id)
    {
        $this->ensureAdmin();

        DB::table('ports')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Data pelabuhan berhasil dihapus.');
    }

    public function storeArticle(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $title = trim($validated['title']);

        DB::table('articles')->insert([
            'user_id' => auth()->id(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'category' => !empty($validated['category'])
                ? trim($validated['category'])
                : null,
            'content' => trim($validated['content']),
            'is_published' => $request->boolean('is_published'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Artikel berhasil ditambahkan.');
    }


    /**
     * Memperbarui artikel internal.
     */
    public function updateArticle(Request $request, int $id)
    {
        $this->ensureAdmin();

        $article = DB::table('articles')->where('id', $id)->first();

        if (!$article) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Artikel tidak ditemukan.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $title = trim($validated['title']);
        $slug = $article->slug;

        if ($title !== $article->title) {
            $slug = Str::slug($title)
                . '-'
                . Str::lower(Str::random(6));
        }

        DB::table('articles')
            ->where('id', $id)
            ->update([
                'title' => $title,
                'slug' => $slug,
                'category' => !empty($validated['category'])
                    ? trim($validated['category'])
                    : null,
                'content' => trim($validated['content']),
                'is_published' => $request->boolean('is_published'),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroyArticle(int $id)
    {
        $this->ensureAdmin();

        DB::table('articles')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    public function storePositiveWord(Request $request)
    {
        $this->ensureAdmin();

        $request->merge([
            'word' => Str::lower(trim((string) $request->input('word'))),
        ]);

        $validated = $request->validate([
            'word' => [
                'required',
                'string',
                'max:100',
                'unique:positive_words,word',
            ],
        ]);

        DB::table('positive_words')->insert([
            'word' => $validated['word'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Kata positif berhasil ditambahkan.');
    }


    /**
     * Memperbarui kata positif.
     */
    public function updatePositiveWord(Request $request, int $id)
    {
        $this->ensureAdmin();

        $request->merge([
            'word' => Str::lower(trim((string) $request->input('word'))),
        ]);

        $validated = $request->validate([
            'word' => [
                'required',
                'string',
                'max:100',
                Rule::unique('positive_words', 'word')->ignore($id),
            ],
        ]);

        $updated = DB::table('positive_words')
            ->where('id', $id)
            ->update([
                'word' => $validated['word'],
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Kata positif tidak ditemukan atau tidak berubah.');
        }

        return redirect()
            ->route('admin.index')
            ->with('success', 'Kata positif berhasil diperbarui.');
    }

    public function destroyPositiveWord(int $id)
    {
        $this->ensureAdmin();

        DB::table('positive_words')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Kata positif berhasil dihapus.');
    }

    public function storeNegativeWord(Request $request)
    {
        $this->ensureAdmin();

        $request->merge([
            'word' => Str::lower(trim((string) $request->input('word'))),
        ]);

        $validated = $request->validate([
            'word' => [
                'required',
                'string',
                'max:100',
                'unique:negative_words,word',
            ],
        ]);

        DB::table('negative_words')->insert([
            'word' => $validated['word'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Kata negatif berhasil ditambahkan.');
    }


    /**
     * Memperbarui kata negatif.
     */
    public function updateNegativeWord(Request $request, int $id)
    {
        $this->ensureAdmin();

        $request->merge([
            'word' => Str::lower(trim((string) $request->input('word'))),
        ]);

        $validated = $request->validate([
            'word' => [
                'required',
                'string',
                'max:100',
                Rule::unique('negative_words', 'word')->ignore($id),
            ],
        ]);

        $updated = DB::table('negative_words')
            ->where('id', $id)
            ->update([
                'word' => $validated['word'],
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Kata negatif tidak ditemukan atau tidak berubah.');
        }

        return redirect()
            ->route('admin.index')
            ->with('success', 'Kata negatif berhasil diperbarui.');
    }

    public function destroyNegativeWord(int $id)
    {
        $this->ensureAdmin();

        DB::table('negative_words')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Kata negatif berhasil dihapus.');
    }
}
