
    <?php

use App\Http\Controllers\HomeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use App\Http\Controllers\SitemapController;

$getData = function ($redisKey) {
    return Redis::get($redisKey);
};

$imageUrl = function ($imagen, $useSlide = false) {
    if (env('USE_LOCAL_JSON', false)) {
        return asset('hero-bg.png');
    }
    if (empty($imagen)) {
        return asset('hero-bg.png');
    }
    $base = $useSlide
        ? 'https://deboleto.com/images/eventos/slide/'
        : 'https://deboleto.com/images/eventos/';
    return $base . $imagen;
};

Route::get('/', function () use ($getData, $imageUrl) {
    $searchData = Redis::get('eventos_activos_app');
    $events = $searchData ? json_decode($searchData, true) : [];

    $nextEvents = array_map(fn($e) => [
        'id' => $e['id'] ?? 0,
        'slug' => $e['url'] ?? '',
        'title' => $e['evento'] ?? '',
        'image' => $imageUrl($e['imagen']),
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
            'image' => $imageUrl($e['imagen']),
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

    $popularEvents = collect($events)
        ->sortBy(fn($e) => $e['id'])
        ->skip(6)
        ->take(6)
        ->values()
        ->map(fn($e) => [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => $imageUrl($e['imagen']),
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
        ->all();

    $bannersData = $getData('eventos_sidebar_app');
    $rawBanners = $bannersData ? json_decode($bannersData, true) : [];

    $banners = array_map(fn($b) => [
        'url'   => !empty($b['url']) ? '/evento/' . $b['url'] : '#',
        'image' => $imageUrl($b['imagen'], true),
        'price' => ($b['desde'] ?? 0) > 0 ? '$' . number_format((float)$b['desde'], 0) : '',
    ], $rawBanners);

    return Inertia::render('Home', [
        'nextEvents' => $nextEvents,
        'zoneEvents' => $zoneEvents,
        'popularEvents' => $popularEvents,
        'banners' => $banners,
    ]);
})->name('home');

Route::get('/evento/{slug}', function ($slug) use ($getData, $imageUrl) {
    $data = $getData('detalle_evento:' . $slug);
    abort_unless($data, 404);

    $event = json_decode($data, true);

    return Inertia::render('EventDetail', [
        'slug'  => $slug,
        'event' => [
            'id'        => $event['id'],
            'name'      => $event['nombre'],
            'image'     => $imageUrl($event['imagen'] ?? ''),
            'hasPromotion' => $event['tiene_promocion'],
            'ventaWeb'  => $event['ventaWeb'],
            'venueName' => $event['lugar'] ?? '',
            'latitude'  => $event['latitud'] ?? null,
            'longitude' => $event['longitud'] ?? null,
            'promotions' => $event['promociones'] ?? [],
            'functions' => array_map(fn($f) => [
                'id'   => $f['id'],
                'date' => $f['fecha'],
            ], $event['funciones'] ?? []),
            'zones' => array_map(fn($z) => [
                'id'             => $z['id'],
                'name'           => $z['nombre'],
                'originalPrice'  => $z['precio_original'],
                'discountPrice'  => $z['precio_descuento'],
                'hasPromotion'   => $z['tiene_promocion'],
            ], $event['zonas'] ?? []),
        ],
    ]);
})->name('event.detail');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
