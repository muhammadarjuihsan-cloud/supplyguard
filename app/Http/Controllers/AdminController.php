<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function ensureAdmin()
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Only admin can access this page.');
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

        $users = DB::table('users')
            ->orderByDesc('created_at')
            ->get();

        $countries = DB::table('countries')
            ->orderBy('name')
            ->get();

        $ports = DB::table('ports')
            ->leftJoin('countries', 'ports.country_id', '=', 'countries.id')
            ->select('ports.*', 'countries.name as linked_country')
            ->orderBy('ports.name')
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
            'users',
            'countries',
            'ports',
            'articles',
            'positiveWords',
            'negativeWords'
        ));
    }

    public function updateUserRole(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'role' => ['required', 'in:admin,user'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->update([
                'role' => $request->input('role'),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'User role updated successfully.');
    }

    public function destroyUser($id)
    {
        $this->ensureAdmin();

        if ((int) $id === auth()->id()) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'You cannot delete your own account.');
        }

        DB::table('users')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'User deleted successfully.');
    }

    public function storePort(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'country_id' => ['nullable', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'port_code' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ]);

        $countryId = $request->input('country_id');
        $countryName = null;

        if ($countryId) {
            $country = DB::table('countries')
                ->where('id', $countryId)
                ->first();

            $countryName = $country ? $country->name : null;
        }

        DB::table('ports')->insert([
            'country_id' => $countryId,
            'name' => $request->input('name'),
            'country_name' => $countryName,
            'port_code' => $request->input('port_code'),
            'type' => $request->input('type'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'description' => $request->input('description'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Port added successfully.');
    }

    public function destroyPort($id)
    {
        $this->ensureAdmin();

        DB::table('ports')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Port deleted successfully.');
    }

    public function storeArticle(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'is_published' => ['nullable'],
        ]);

        $title = $request->input('title');

        DB::table('articles')->insert([
            'user_id' => auth()->id(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . uniqid(),
            'category' => $request->input('category'),
            'content' => $request->input('content'),
            'is_published' => $request->has('is_published'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Article added successfully.');
    }

    public function destroyArticle($id)
    {
        $this->ensureAdmin();

        DB::table('articles')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Article deleted successfully.');
    }

    public function storePositiveWord(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'word' => ['required', 'string', 'max:100', 'unique:positive_words,word'],
        ]);

        DB::table('positive_words')->insert([
            'word' => strtolower($request->input('word')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Positive word added successfully.');
    }

    public function destroyPositiveWord($id)
    {
        $this->ensureAdmin();

        DB::table('positive_words')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Positive word deleted successfully.');
    }

    public function storeNegativeWord(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'word' => ['required', 'string', 'max:100', 'unique:negative_words,word'],
        ]);

        DB::table('negative_words')->insert([
            'word' => strtolower($request->input('word')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.index')
            ->with('success', 'Negative word added successfully.');
    }

    public function destroyNegativeWord($id)
    {
        $this->ensureAdmin();

        DB::table('negative_words')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('admin.index')
            ->with('success', 'Negative word deleted successfully.');
    }
}
