<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CountryDataController;
use App\Http\Controllers\SupplyGuardApiController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\VisualizationController;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\ApiLogController;
use App\Http\Controllers\AdminPortController;

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

    Route::get('/data-negara', [CountryDataController::class, 'index'])->name('countries.index');

    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');

    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::patch('/watchlist/country/{countryId}/note', [WatchlistController::class, 'updateNote'])->name('watchlist.note.update');
    Route::delete('/watchlist/country/{countryId}', [WatchlistController::class, 'destroyByCountry'])->name('watchlist.destroyByCountry');

    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison');

    Route::get('/data-visualization', [VisualizationController::class, 'index'])->name('visualization.index');

    Route::get('/port-location', [PortController::class, 'index'])->name('ports.index');

    Route::get('/news-intelligence', [NewsController::class, 'index'])->name('news.index');

    Route::get('/rest-api', [ApiDocumentationController::class, 'index'])->name('api.docs');

    Route::get('/weather-monitoring', [WeatherController::class, 'index'])->name('weather.index');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin/ports', [AdminPortController::class, 'index'])->name('admin.ports.index');
    Route::post('/admin/ports', [AdminPortController::class, 'store'])->name('admin.ports.store');
    Route::patch('/admin/ports/{id}', [AdminPortController::class, 'update'])->name('admin.ports.update');
    Route::delete('/admin/ports/{id}', [AdminPortController::class, 'destroy'])->name('admin.ports.destroy');
    Route::get('/admin/api-logs', [ApiLogController::class, 'index'])->name('admin.apiLogs.index');

    Route::post('/admin/sentiment/reanalyze', [AdminController::class, 'reanalyzeSentiment'])
        ->name('admin.sentiment.reanalyze');

    Route::post('/admin/risk/recalculate', [AdminController::class, 'recalculateRisk'])
        ->name('admin.risk.recalculate');

    Route::patch('/admin/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/admin/users/{id}/role', [AdminController::class, 'updateUserRole'])->name('admin.users.role');
    Route::delete('/admin/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');

    Route::post('/admin/articles', [AdminController::class, 'storeArticle'])->name('admin.articles.store');
    Route::patch('/admin/articles/{id}', [AdminController::class, 'updateArticle'])->name('admin.articles.update');
    Route::delete('/admin/articles/{id}', [AdminController::class, 'destroyArticle'])->name('admin.articles.destroy');

    Route::post('/admin/positive-words', [AdminController::class, 'storePositiveWord'])->name('admin.positiveWords.store');
    Route::patch('/admin/positive-words/{id}', [AdminController::class, 'updatePositiveWord'])->name('admin.positiveWords.update');
    Route::delete('/admin/positive-words/{id}', [AdminController::class, 'destroyPositiveWord'])->name('admin.positiveWords.destroy');

    Route::post('/admin/negative-words', [AdminController::class, 'storeNegativeWord'])->name('admin.negativeWords.store');
    Route::patch('/admin/negative-words/{id}', [AdminController::class, 'updateNegativeWord'])->name('admin.negativeWords.update');
    Route::delete('/admin/negative-words/{id}', [AdminController::class, 'destroyNegativeWord'])->name('admin.negativeWords.destroy');
});

Route::get('/api/countries', [SupplyGuardApiController::class, 'countries']);
Route::get('/api/risk', [SupplyGuardApiController::class, 'risk']);
Route::get('/api/ports', [SupplyGuardApiController::class, 'ports']);
Route::get('/api/news', [SupplyGuardApiController::class, 'news']);
Route::get('/api/currency', [SupplyGuardApiController::class, 'currency']);
