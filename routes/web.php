<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupplyGuardApiController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\AdminController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.process');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::delete('/watchlist/country/{countryId}', [WatchlistController::class, 'destroyByCountry'])->name('watchlist.destroyByCountry');

    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');

    Route::post('/admin/users/{id}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.role');
    Route::delete('/admin/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');

    Route::post('/admin/ports', [AdminController::class, 'storePort'])->name('admin.ports.store');
    Route::delete('/admin/ports/{id}', [AdminController::class, 'destroyPort'])->name('admin.ports.destroy');

    Route::post('/admin/articles', [AdminController::class, 'storeArticle'])->name('admin.articles.store');
    Route::delete('/admin/articles/{id}', [AdminController::class, 'destroyArticle'])->name('admin.articles.destroy');

    Route::post('/admin/positive-words', [AdminController::class, 'storePositiveWord'])->name('admin.positiveWords.store');
    Route::delete('/admin/positive-words/{id}', [AdminController::class, 'destroyPositiveWord'])->name('admin.positiveWords.destroy');

    Route::post('/admin/negative-words', [AdminController::class, 'storeNegativeWord'])->name('admin.negativeWords.store');
    Route::delete('/admin/negative-words/{id}', [AdminController::class, 'destroyNegativeWord'])->name('admin.negativeWords.destroy');
});

Route::get('/api/countries', [SupplyGuardApiController::class, 'countries']);
Route::get('/api/risk', [SupplyGuardApiController::class, 'risk']);
Route::get('/api/ports', [SupplyGuardApiController::class, 'ports']);
Route::get('/api/news', [SupplyGuardApiController::class, 'news']);
Route::get('/api/currency', [SupplyGuardApiController::class, 'currency']);
