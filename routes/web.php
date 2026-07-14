<?php

use App\Http\Controllers\HomeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use App\Http\Controllers\SitemapController;

Route::get('/', function () {
    $searchData = Redis::get('eventos_activos_app');
    $events = $searchData ? json_decode($searchData, true) : [];

    $nextEvents = array_map(fn($e) => [
        'id' => $e['id'] ?? 0,
        'slug' => $e['url'] ?? '',
        'title' => $e['evento'] ?? '',
        'image' => !empty($e['imagen']) ? '/events/' . $e['imagen'] . '.png' : '',
        'date' => $e['fecha'] ?? '',
        'dateISO' => '',
        'venue' => $e['escenario'] ?? '',
        'city' => $e['ciudad'] ?? '',
        'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$' . number_format((float)$e['desde'], 0) : '',
        'artist' => null,
        'category' => null,
        'categoryColor' => null,
        'availability' => 'available',
    ], array_slice($events, 0, 16));

    return Inertia::render('Home', [
        'nextEvents' => $nextEvents,
    ]);
})->name('home');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
