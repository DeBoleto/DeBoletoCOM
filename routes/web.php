<?php

use App\Http\Controllers\SitemapController;
use App\Modules\Checkout\Tracking\MetaTracker;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Support\EventPromotions;
use App\Support\Seo\SchemaOrg;
use App\Support\Seo\SeoMeta;
use App\Support\VenueNameNormalizer;
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
        ? config('app.deboleto_url').'/images/eventos/slide/'
        : config('app.deboleto_url').'/images/eventos/';

    return $base.$imagen;
};

// Página de inicio: expone el catálogo de Redis como props de Inertia para Home.vue.
Route::get('/', function () use ($getData, $imageUrl) {
    // Catálogo de eventos activos (fuente única de búsqueda y secciones).
    $searchData = Redis::get('eventos_activos_app');
    $events = $searchData ? json_decode($searchData, true) : [];

    // Etiqueta de promoción de cada evento: el texto que trae Redis
    // (promocion_text) y, si no viene, la que se arma con lo capturado en el admin.
    $promotions = rescue(
        fn () => app(EventPromotions::class)->badges(collect($events)
            ->filter(fn ($e) => trim((string) ($e['promocion_text'] ?? '')) === '')
            ->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all()),
        [],
    );
    $promotionOf = fn ($e) => trim((string) ($e['promocion_text'] ?? '')) ?: ($promotions[(int) ($e['id'] ?? 0)] ?? null);

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
            'promotion' => $promotionOf($e),
        ])
        ->all();

    // "Promociones": todos los eventos con promoción general o por zona.
    $promotionEvents = collect($events)
        ->filter(fn ($e) => $promotionOf($e) !== null)
        ->sortByDesc(fn ($e) => $e['id'] ?? 0)
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
            'promotion' => $promotionOf($e),
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
            'promotion' => $promotionOf($e),
        ])
        ->all();

    // "Próximos Eventos" (NextEventsGrid): catálogo completo pre-cargado, el
    // cliente pagina y filtra sin peticiones async.
    $upcomingEvents = collect($events)
        ->sortByDesc(fn ($e) => $e['id'] ?? 0)
        ->values()
        ->map(fn ($e) => [
            'id' => $e['id'] ?? 0,
            'slug' => $e['url'] ?? '',
            'title' => $e['evento'] ?? '',
            'image' => $imageUrl($e['imagen'] ?? ''),
            'date' => $e['fecha'] ?? '',
            'hora' => $e['hora'] ?? '',
            'venue' => $e['escenario'] ?? '',
            'city' => $e['ciudad'] ?? '',
            'estado' => trim((string) ($e['estado'] ?? '')),
            'price' => (float) ($e['desde'] ?? 0),
            'priceFormatted' => ($e['desde'] ?? 0) > 0 ? '$'.number_format((float) $e['desde'], 0) : '',
            'category' => $e['categoria'] ?? '',
            'categoryName' => $e['categoria_nombre'] ?? '',
            'promociones' => $e['promociones'] ?? [],
            'promotion' => $promotionOf($e),
        ])
        ->all();

    // Banner del HeroSlider del Home.
    $bannersData = $getData('eventos_sidebar_app');
    $rawBanners = $bannersData ? json_decode($bannersData, true) : [];

    $banners = array_map(fn ($b) => [
        'url' => ! empty($b['url']) ? '/evento/'.$b['url'] : '#',
        'image' => $imageUrl($b['imagen'], true),
        'price' => ($b['desde'] ?? 0) > 0 ? '$'.number_format((float) $b['desde'], 0) : '',
    ], $rawBanners);

    // Normaliza un evento para la sección "Cerca de ti" (sidebar + activos).
    // $useSlide=true usa las imágenes de slide (portadas de las tarjetas).
    $mapNearbyEvent = function ($e, $useSlide = false) use ($imageUrl, $promotionOf) {
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
            'promotion' => $promotionOf($e),
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
        'promotionEvents' => $promotionEvents,
        'popularEvents' => $popularEvents,
        'banners' => $banners,
        'sidebarEvents' => $sidebarEvents,
        'activosEvents' => $activosEvents,
        'upcomingEvents' => $upcomingEvents,
        'seo' => SeoMeta::make('Boletos para conciertos, teatro, deportes y más')
            ->url(route('home'))
            ->schema(SchemaOrg::organization(), SchemaOrg::website())
            ->toArray(),
    ]);
})->name('home');

// Detalle de evento: lee la clave detalle_evento:{slug}, 404 si no existe.
Route::get('/evento/{slug}', function ($slug) use ($getData, $imageUrl) {
    $data = $getData('detalle_evento:'.$slug);
    abort_unless($data, 404);

    $event = json_decode($data, true);

    // "Eventos Cercanos": eventos del mismo recinto del actual (fallback a
    // ciudad/estado) desde el catálogo activo (eventos_activos_app),
    // excluyendo el evento actual.
    $activosData = $getData('eventos_activos_app');
    $activosRaw = $activosData ? json_decode($activosData, true) : [];

    $currentEventId = (int) ($event['id'] ?? 0);
    $currentEvent = null;

    if (is_array($activosRaw)) {
        foreach ($activosRaw as $candidate) {
            if (($candidate['url'] ?? '') === $slug
                || ((int) ($candidate['id'] ?? 0) !== 0
                    && (int) ($candidate['id'] ?? 0) === $currentEventId)) {
                $currentEvent = $candidate;
                break;
            }
        }
    }

    $sidebarData = $getData('eventos_sidebar_app');
    $sidebarRaw = $sidebarData ? json_decode($sidebarData, true) : [];

    $sliderImage = null;

    if (is_array($sidebarRaw)) {
        foreach ($sidebarRaw as $candidate) {
            if (($candidate['url'] ?? '') === $slug
                || ((int) ($candidate['id'] ?? 0) !== 0
                    && (int) ($candidate['id'] ?? 0) === $currentEventId)) {
                $sliderImage = ! empty($candidate['imagen'])
                    ? $imageUrl($candidate['imagen'], true)
                    : null;
                break;
            }
        }
    }

    $nearbyEvents = [];

    if ($currentEvent !== null) {
        $venueKey = VenueNameNormalizer::slugify((string) ($currentEvent['escenario'] ?? ''));
        $city = trim((string) ($currentEvent['ciudad'] ?? ''));
        $estado = trim((string) ($currentEvent['estado'] ?? ''));

        $pool = collect($activosRaw)
            ->filter(fn ($e) => (int) ($e['id'] ?? 0) !== $currentEventId);

        $sameVenue = $venueKey !== ''
            ? $pool->filter(fn ($e) => VenueNameNormalizer::slugify((string) ($e['escenario'] ?? '')) === $venueKey)
            : collect();

        $sameCity = $sameVenue->isNotEmpty()
            ? $sameVenue
            : $pool->filter(fn ($e) => trim((string) ($e['ciudad'] ?? '')) === $city
                && trim((string) ($e['estado'] ?? '')) === $estado);

        $nearbyPool = $sameCity->isNotEmpty()
            ? $sameCity
            : $pool->filter(fn ($e) => trim((string) ($e['estado'] ?? '')) === $estado);

        $nearbyEvents = $nearbyPool
            ->take(10)
            ->values()
            ->map(fn ($e) => [
                'slug' => $e['url'] ?? '',
                'title' => $e['evento'] ?? '',
                'image' => $imageUrl($e['imagen'] ?? ''),
                'date' => $e['fecha'] ?? '',
                'city' => $e['ciudad'] ?? '',
                'estado' => trim((string) ($e['estado'] ?? '')),
            ])
            ->all();
    }

    // Carreras: el kit (elementos con su ícono de Lucide, fecha y lugar de
    // entrega) y la convocatoria del evento (eventos.convocatoria) vienen en el
    // detalle de Redis, que escriben el organizador y deboletoadmin. Si el detalle aún no los trae (cargado antes
    // de que existieran) se leen de MySQL (categoría con carrera = 1); si la
    // consulta falla se registra y el detalle se muestra sin ellos.
    $kit = null;
    $convocatoria = (string) ($event['convocatoria'] ?? '');

    if (array_key_exists('kit', $event)) {
        $kit = is_array($event['kit']) ? [
            'items' => array_values(array_map(fn ($item) => [
                'name' => (string) ($item['nombre'] ?? ''),
                'icon' => (string) ($item['icono'] ?? ''),
            ], array_filter((array) ($event['kit']['elementos'] ?? []), 'is_array'))),
            'deliveryDate' => $event['kit']['entrega_fecha'] ?? null,
            'deliveryPlace' => trim((string) ($event['kit']['entrega_lugar'] ?? '')),
        ] : null;
    } else {
        $race = rescue(fn () => Eventos::query()
            ->where('pk_evento', $currentEventId)
            ->carreras()
            ->with('kit.kitsElementos')
            ->first());

        $kit = $race?->kit ? [
            'items' => $race->kit->kitsElementos
                ->reject(fn ($item) => $item->eliminado)
                ->map(fn ($item) => [
                    'name' => (string) $item->kit_elemento,
                    'icon' => (string) ($item->icono ?? ''),
                ])
                ->values()
                ->all(),
            'deliveryDate' => $race->kit->entrega_fecha?->format('Y-m-d\TH:i:s'),
            'deliveryPlace' => trim((string) $race->kit->entrega_lugar),
        ] : null;
        $convocatoria = (string) ($race?->convocatoria ?? '');
    }

    $imagesUrl = config('app.deboleto_url').'/images/eventos/';

    // Patrocinadores del evento (los registra el organizador): logo en
    // /images/eventos/patrocinadores, en su orden.
    $sponsors = collect((array) ($event['patrocinadores'] ?? []))
        ->filter(fn ($s) => is_array($s) && ! empty($s['logo']))
        ->sortBy(fn ($s) => (int) ($s['orden'] ?? 0))
        ->values()
        ->map(fn ($s) => [
            'name' => (string) ($s['nombre'] ?? ''),
            'image' => $imagesUrl.'patrocinadores/'.rawurlencode($s['logo']),
            'url' => trim((string) ($s['url'] ?? '')),
        ])
        ->all();

    $eventProps = [
        'id' => $event['id'],
        'name' => $event['nombre'],
        'desc' => $event['descripcion'],
        'comments' => $event['comentarios'],
        'image' => $imageUrl($event['imagen'] ?? ''),
        'sliderImage' => $sliderImage,
        'hasPromotion' => $event['tiene_promocion'],
        'ventaWeb' => $event['ventaWeb'],
        'venueName' => $event['lugar'] ?? '',
        'latitude' => $event['latitud'] ?? null,
        'longitude' => $event['longitud'] ?? null,
        'promotions' => array_values(array_unique(array_filter([
            trim((string) ($event['promocion_text'] ?? '')),
            ...(array) ($event['promociones'] ?? []),
        ]))),
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
    ];

    $eventUrl = route('event.detail', $slug);
    $eventImage = $eventProps['sliderImage'] ?: $eventProps['image'];
    $eventCity = trim((string) ($event['ciudad'] ?? $currentEvent['ciudad'] ?? ''));
    $eventState = trim((string) ($event['estado'] ?? $currentEvent['estado'] ?? ''));
    $eventPlace = implode(', ', array_filter([$eventProps['venueName'], $eventCity]));

    $seo = SeoMeta::make(
        $eventProps['name'],
        trim((string) $eventProps['desc']) !== ''
            ? (string) $eventProps['desc']
            : 'Compra boletos para '.$eventProps['name'].($eventPlace !== '' ? ' en '.$eventPlace : '').'.',
    )
        ->url($eventUrl)
        ->image($eventImage)
        ->schema(...SchemaOrg::events($eventProps, $eventUrl, $eventImage, $eventCity, $eventState))
        ->toArray();

    $tracker = app(MetaTracker::class);
    $tracker->track($currentEventId, 'PageView', [], server: false);
    $tracker->track($currentEventId, 'ViewContent', ['content_name' => (string) $eventProps['name']], sourceUrl: $eventUrl);

    return Inertia::render('EventDetail', [
        'seo' => $seo,
        'slug' => $slug,
        'kit' => $kit && ($kit['items'] !== [] || $kit['deliveryPlace'] !== '') ? $kit : null,
        'convocatoria' => $convocatoria !== ''
            ? config('app.convocatorias_url').'/'.rawurlencode($convocatoria)
            : null,
        'sponsors' => $sponsors,
        'nearbyEvents' => $nearbyEvents,
        'event' => $eventProps,
    ]);
})->name('event.detail');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Zona autenticada (Jetstream + verificación de email).
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
