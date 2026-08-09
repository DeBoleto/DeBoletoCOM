<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\VenueNameNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class VenuesController extends Controller
{
    private const CATALOG_KEY = 'escenarios_unicos';

    private const EVENTS_KEY = 'eventos_por_escenario';

    private const IMAGE_BASE = 'https://deboleto.com/images/eventos/';

    public function index(): JsonResponse
    {
        $eventsMap = $this->readEventsMap();
        $venues = $this->buildVenues($this->readCatalog(), $eventsMap);

        $venues = array_map(fn ($v) => [
            'slug' => $v['slug'],
            'name' => $v['name'],
            'city' => $v['city'],
            'state' => $v['state'],
            'image' => $v['image'],
            'eventCount' => $v['eventCount'],
        ], $venues);

        return response()->json(array_values($venues));
    }

    public function show(string $slug): Response
    {
        $eventsMap = $this->readEventsMap();
        $venue = collect($this->buildVenues($this->readCatalog(), $eventsMap))
            ->first(fn ($v) => ($v['slug'] ?? '') === $slug);

        if (! $venue) {
            abort(404);
        }

        $events = collect($eventsMap[$venue['name']] ?? []);

        return Inertia::render('VenueDetail', [
            'venue' => [
                'name' => $venue['name'],
                'city' => $venue['city'],
                'state' => $venue['state'],
                'address' => $venue['address'],
                'image' => $venue['image'],
                'latitude' => $venue['latitude'],
                'longitude' => $venue['longitude'],
                'eventCount' => $venue['eventCount'],
            ],
            'events' => $events
                ->sortBy(fn ($e) => $this->sortableDate($e['fecha'] ?? ''))
                ->values()
                ->map(fn ($e) => $this->transformEvent($e))
                ->all(),
            'facets' => $this->buildFacets($events),
        ]);
    }

    private function buildVenues(array $catalog, array $eventsMap): array
    {
        $venues = [];
        $seen = [];

        foreach ($catalog as $row) {
            $name = VenueNameNormalizer::normalize((string) ($row['nombre'] ?? ''));
            if ($name === '') {
                continue;
            }

            $events = collect($eventsMap[$name] ?? []);
            if ($events->isEmpty()) {
                continue;
            }

            $representative = $events->sortByDesc(fn ($e) => $e['id'] ?? 0)->first() ?? [];

            $venues[] = $this->buildVenue($name, $representative, $events->count());
            $seen[$name] = true;
        }

        foreach ($eventsMap as $name => $group) {
            if (isset($seen[$name]) || empty($group)) {
                continue;
            }

            $events = collect($group ?? []);
            $representative = $events->sortByDesc(fn ($e) => $e['id'] ?? 0)->first() ?? [];

            $venues[] = $this->buildVenue($name, $representative, $events->count());
        }

        usort($venues, fn ($a, $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        return array_values($venues);
    }

    private function buildVenue(string $name, array $representative, int $eventCount): array
    {
        return [
            'slug' => VenueNameNormalizer::slugify($name),
            'name' => $name,
            'city' => trim((string) ($representative['ciudad'] ?? '')),
            'state' => trim((string) ($representative['estado'] ?? '')),
            'image' => $this->resolveImage($representative['imagen'] ?? ''),
            'address' => trim((string) ($representative['direccion'] ?? '')),
            'latitude' => $representative['latitud'] ?? null,
            'longitude' => $representative['longitud'] ?? null,
            'eventCount' => $eventCount,
        ];
    }

    private function buildFacets($events): array
    {
        return [
            'cities' => $events->pluck('ciudad')->map(fn ($c) => trim((string) $c))->filter()->unique()->values()->all(),
            'states' => $events->pluck('estado')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all(),
            'months' => $events->pluck('fecha')->map(function ($fecha) {
                $parts = explode(' ', trim((string) $fecha));

                return $parts[1] ?? null;
            })->filter()->unique()->values()->all(),
            'priceMin' => (float) $events->min('desde'),
            'priceMax' => (float) $events->max('desde'),
        ];
    }

    private function readCatalog(): array
    {
        $raw = Redis::get(self::CATALOG_KEY);
        $data = $raw ? json_decode($raw, true) : [];

        return is_array($data) ? $data : [];
    }

    private function readEventsMap(): array
    {
        $raw = Redis::get(self::EVENTS_KEY);
        $map = $raw ? json_decode($raw, true) : [];

        if (! is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $name => $group) {
            if (! is_array($group)) {
                continue;
            }

            $normalized[VenueNameNormalizer::normalize((string) $name)] = array_values($group);
        }

        return $normalized;
    }

    private function transformEvent(array $event): array
    {
        $price = (float) ($event['desde'] ?? 0);
        $fecha = trim($event['fecha'] ?? '');
        $parts = explode(' ', $fecha);

        return [
            'id' => $event['id'] ?? 0,
            'slug' => $event['url'] ?? '',
            'title' => $event['evento'] ?? '',
            'image' => $this->resolveImage($event['imagen'] ?? ''),
            'date' => $fecha,
            'dateISO' => $this->sortableDate($fecha),
            'month' => $parts[1] ?? '',
            'price' => $price,
            'priceFormatted' => $this->formatPrice($event['desde'] ?? null),
            'city' => trim($event['ciudad'] ?? ''),
            'state' => trim($event['estado'] ?? ''),
            'venue' => trim($event['escenario'] ?? ''),
            'artist' => null,
            'availability' => ($event['venta_web'] ?? true) ? 'available' : 'sold-out',
        ];
    }

    private function resolveImage(string $imagen): string
    {
        if (empty($imagen)) {
            return '/events/concert-01.png';
        }

        if (str_contains($imagen, '/')) {
            return $imagen;
        }

        return self::IMAGE_BASE.$imagen;
    }

    private function formatPrice($desde): string
    {
        if ($desde === null || $desde === '') {
            return '';
        }

        if (is_string($desde) && str_starts_with($desde, '$')) {
            return $desde;
        }

        $num = (float) $desde;

        if ($num <= 0) {
            return '';
        }

        return '$'.($num >= 1000
            ? number_format($num, 0, '.', ',')
            : number_format($num, 0));
    }

    private function sortableDate(string $fecha): string
    {
        $parts = explode(' ', trim($fecha));

        if (count($parts) < 2) {
            return $fecha;
        }

        $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
        $day = (int) $parts[0];
        $month = array_search(mb_strtoupper($parts[1]), $months);

        if ($month === false) {
            return $fecha;
        }

        return sprintf('%04d-%02d-%02d', (int) date('Y'), $month + 1, $day);
    }
}
