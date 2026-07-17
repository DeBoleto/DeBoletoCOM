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
        'image' => !empty($e['imagen']) ? 'https://deboleto.com/images/eventos/' . $e['imagen'] : '',
        'date' => $e['fecha'] ?? '',
        'dateISO' => '',
        'venue' => $e['escenario'] ?? '',
        'city' => $e['ciudad'] ?? '',
        'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$' . number_format((float)$e['desde'], 0) : '',
        'artist' => null,
        'category' => null,
        'categoryColor' => null,
        'availability' => 'available',
    ], array_slice($events, 0, 6));

    $zoneEvents = collect($events)
        ->filter(fn($e) => ($e['estado'] ?? '') === 'Tabasco')
        ->sortByDesc(fn($e) => $e['id'] ?? '')
        ->take(6)
        ->map(fn($e) => [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => !empty($e['imagen']) ? 'https://deboleto.com/images/eventos/' . $e['imagen'] : '',
            'date' => $e['fecha'] ?? '',
            'dateISO' => '',
            'venue' => $e['escenario'] ?? '',
            'city' => $e['ciudad'] ?? '',
            'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$' . number_format((float)$e['desde'], 0) : '',
            'artist' => null,
            'category' => null,
            'categoryColor' => null,
            'availability' => 'available',
        ])
        ->values()
        ->all();

    $bannersData = Redis::get('eventos_activos_app');
    $rawBanners = $bannersData ? json_decode($bannersData, true) : [];

    $banners = array_map(fn($b) => [
        'url'   => $b['url'] ?? '#',
        'image' => !empty($b['imagen']) ? 'https://deboleto.com/images/eventos/' . $b['imagen'] : '',
        'price' => ($b['desde'] ?? 0) > 0 ? '$' . number_format((float)$b['desde'], 0) : '',
    ], $rawBanners);

    return Inertia::render('Home', [
        'nextEvents' => $nextEvents,
        'zoneEvents' => $zoneEvents,
        'banners' => $banners,
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
