<?php

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Lee el JSON crudo de una clave Redis (sin decodificar).
$getData = function ($redisKey) {
    return Redis::get($redisKey);
};

/**
 * Resuelve la URL pública de la imagen de un evento.
 *
 * @param  string  $imagen  Nombre de archivo de la imagen (o vacío).
 * @param  bool  $useSlide  true = base de imágenes de slide (portada).
 */
$imageUrl = function ($imagen, $useSlide = false) {
    // Placeholder local durante desarrollo offline.
    if (env('USE_LOCAL_JSON', false)) {
        return asset('hero-bg.png');
    }
    if (empty($imagen)) {
        return asset('hero-bg.png');
    }
    $base = $useSlide
        ? 'https://deboleto.com/images/eventos/slide/'
        : 'https://deboleto.com/images/eventos/';

    return $base.$imagen;
};

// Página de inicio: expone el catálogo de Redis como props de Inertia para Home.vue.
Route::get('/', function () use ($getData, $imageUrl) {
    // Catálogo de eventos activos (fuente única de búsqueda y secciones).
    $searchData = Redis::get('eventos_activos_app');
    $events = $searchData ? json_decode($searchData, true) : [];

    // "Próximos Eventos": últimos 6 por id (orden descendente).
    $nextEvents = collect($events)
        ->sortByDesc(fn ($e) => $e['id'] ?? 0)
        ->take(6)
        ->values()
        ->map(fn ($e) => [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => $imageUrl($e['imagen']),
            'date' => $e['fecha'] ?? '',
            'dateISO' => '',
            'venue' => $e['escenario'] ?? '',
            'city' => $e['ciudad'] ?? '',
            'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$'.number_format((float) $e['desde'], 0) : '',
            'artist' => null,
            'category' => null,
            'categoryColor' => null,
            'availability' => 'available',
        ])
        ->all();

    // "Eventos en tu Zona": eventos del estado por defecto (Tabasco).
    $zoneEvents = collect($events)
        ->filter(fn ($e) => ($e['estado'] ?? '') === 'Tabasco')
        ->sortByDesc(fn ($e) => $e['id'] ?? '')
        ->take(6)
        ->map(fn ($e) => [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => $imageUrl($e['imagen']),
            'date' => $e['fecha'] ?? '',
            'dateISO' => '',
            'venue' => $e['escenario'] ?? '',
            'city' => $e['ciudad'] ?? '',
            'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$'.number_format((float) $e['desde'], 0) : '',
            'artist' => null,
            'category' => null,
            'categoryColor' => null,
            'availability' => 'available',
        ])
        ->values()
        ->all();

    // "Eventos Populares": 6 siguientes después de los próximos.
    $popularEvents = collect($events)
        ->sortBy(fn ($e) => $e['id'])
        ->skip(6)
        ->take(6)
        ->values()
        ->map(fn ($e) => [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => $imageUrl($e['imagen']),
            'date' => $e['fecha'] ?? '',
            'dateISO' => '',
            'venue' => $e['escenario'] ?? '',
            'city' => $e['ciudad'] ?? '',
            'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$'.number_format((float) $e['desde'], 0) : '',
            'artist' => null,
            'category' => null,
            'categoryColor' => null,
            'availability' => 'available',
        ])
        ->all();

    // Banner del HeroSlider del Home.
    $bannersData = $getData('eventos_sidebar_app');
    $rawBanners = $bannersData ? json_decode($bannersData, true) : [];

    $banners = array_map(fn ($b) => [
        'url' => ! empty($b['url']) ? '/evento/'.$b['url'] : '#',
        'image' => $imageUrl($b['imagen'], false),
        'price' => ($b['desde'] ?? 0) > 0 ? '$'.number_format((float) $b['desde'], 0) : '',
    ], $rawBanners);

    // Normaliza un evento para la sección "Cerca de ti" (sidebar + activos).
    // $useSlide=true usa las imágenes de slide (portadas de las tarjetas).
    $mapNearbyEvent = function ($e, $useSlide = false) use ($imageUrl) {
        $price = ($e['desde'] ?? 0) > 0 ? '$'.number_format((float) $e['desde'], 0) : '';

        return [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => $imageUrl($e['imagen'] ?? '', $useSlide),
            'date' => $e['fecha'] ?? '',
            'price' => $price,
            'city' => $e['ciudad'] ?? '',
            'estado' => trim((string) ($e['estado'] ?? '')),
            'location' => $e['escenario'] ?? '',
        ];
    };

    // "Cerca de ti" — tarjetas landscape de la izquierda (imágenes slide).
    $sidebarEvents = array_map(fn ($e) => $mapNearbyEvent($e, true), $rawBanners);
    // "Cerca de ti" — tickets de la derecha, filtrados por estado en el cliente.
    $activosData = $getData('eventos_activos_app');
    $rawActivos = $activosData ? json_decode($activosData, true) : [];
    $activosEvents = array_map($mapNearbyEvent, $rawActivos);

    return Inertia::render('Home', [
        'nextEvents' => $nextEvents,
        'zoneEvents' => $zoneEvents,
        'popularEvents' => $popularEvents,
        'banners' => $banners,
        'sidebarEvents' => $sidebarEvents,
        'activosEvents' => $activosEvents,
    ]);
})->name('home');

// Detalle de evento: lee la clave detalle_evento:{slug}, 404 si no existe.
Route::get('/evento/{slug}', function ($slug) use ($getData, $imageUrl) {
    $data = $getData('detalle_evento:'.$slug);
    abort_unless($data, 404);

    $event = json_decode($data, true);

    return Inertia::render('EventDetail', [
        'slug' => $slug,
        'event' => [
            'id' => $event['id'],
            'name' => $event['nombre'],
            'desc' => $event['descripcion'],
            'comments' => $event['comentarios'],
            'image' => $imageUrl($event['imagen'] ?? ''),
            'hasPromotion' => $event['tiene_promocion'],
            'ventaWeb' => $event['ventaWeb'],
            'venueName' => $event['lugar'] ?? '',
            'latitude' => $event['latitud'] ?? null,
            'longitude' => $event['longitud'] ?? null,
            'promotions' => $event['promociones'] ?? [],
            'functions' => array_map(fn ($f) => [
                'id' => $f['id'],
                'date' => $f['fecha'],
            ], $event['funciones'] ?? []),
            'zones' => array_map(fn ($z) => [
                'id' => $z['id'],
                'name' => $z['nombre'],
                'originalPrice' => $z['precio_original'],
                'discountPrice' => $z['precio_descuento'],
                'hasPromotion' => $z['tiene_promocion'],
            ], $event['zonas'] ?? []),
        ],
    ]);
})->name('event.detail');

// Zona autenticada (Jetstream + verificación de email).
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
