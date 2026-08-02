<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class VenuesController extends Controller
{
    private const REDIS_KEY = 'eventos_activos_app';
    private const IMAGE_BASE = 'https://deboleto.com/images/eventos/';

    public function index(): JsonResponse
    {
        $venues = $this->groupedVenues();

        return response()->json($venues);
    }

    public function show(string $slug): Response
    {
        $events = collect($this->activeEvents())
            ->filter(fn ($event) => $this->slugify($event['escenario'] ?? '') === $slug)
            ->values();

        if ($events->isEmpty()) {
            abort(404);
        }

        $venue = $events->sortByDesc(fn ($e) => $e['id'] ?? 0)->first();

        return Inertia::render('VenueDetail', [
            'venue' => [
                'name'       => trim($venue['escenario'] ?? ''),
                'city'       => $venue['ciudad'] ?? '',
                'state'      => trim($venue['estado'] ?? ''),
                'image'      => $this->resolveImage($venue['imagen'] ?? ''),
                'eventCount' => $events->count(),
            ],
            'events' => $events
                ->sortBy(fn ($e) => $this->sortableDate($e['fecha'] ?? ''))
                ->values()
                ->map(fn ($e) => $this->transformEvent($e))
                ->all(),
            'facets' => $this->buildFacets($events),
        ]);
    }

    private function groupedVenues(): array
    {
        $events = $this->activeEvents();

        return collect($events)
            ->groupBy(fn ($event) => $event['escenario'] ?? '')
            ->filter(fn ($group, $name) => trim((string) $name) !== '')
            ->map(function ($group, $name) {
                $representative = $group->sortByDesc(fn ($e) => $e['id'] ?? 0)->first();

                return [
                    'slug'       => $this->slugify($name),
                    'name'       => trim($name),
                    'city'       => $representative['ciudad'] ?? '',
                    'state'      => trim($representative['estado'] ?? ''),
                    'image'      => $this->resolveImage($representative['imagen'] ?? ''),
                    'eventCount' => $group->count(),
                ];
            })
            ->values()
            ->sortBy(fn ($venue) => mb_strtolower($venue['name']))
            ->values()
            ->all();
    }

    private function buildFacets($events): array
    {
        return [
            'cities'   => $events->pluck('ciudad')->map(fn ($c) => trim((string) $c))->filter()->unique()->values()->all(),
            'states'   => $events->pluck('estado')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all(),
            'months'   => $events->pluck('fecha')->map(function ($fecha) {
                $parts = explode(' ', trim((string) $fecha));
                return $parts[1] ?? null;
            })->filter()->unique()->values()->all(),
            'priceMin' => (float) $events->min('desde'),
            'priceMax' => (float) $events->max('desde'),
        ];
    }

    private function activeEvents(): array
    {
        $raw = Redis::get(self::REDIS_KEY);
        $events = $raw ? json_decode($raw, true) : [];

        return is_array($events) ? $events : [];
    }

    private function transformEvent(array $event): array
    {
        $price = (float) ($event['desde'] ?? 0);
        $fecha = trim($event['fecha'] ?? '');
        $parts = explode(' ', $fecha);

        return [
            'id'             => $event['id'] ?? 0,
            'slug'           => $event['url'] ?? '',
            'title'          => $event['evento'] ?? '',
            'image'          => $this->resolveImage($event['imagen'] ?? ''),
            'date'           => $fecha,
            'dateISO'        => $this->sortableDate($fecha),
            'month'          => $parts[1] ?? '',
            'price'          => $price,
            'priceFormatted' => $this->formatPrice($event['desde'] ?? null),
            'city'           => trim($event['ciudad'] ?? ''),
            'state'          => trim($event['estado'] ?? ''),
            'venue'          => trim($event['escenario'] ?? ''),
            'artist'         => null,
            'availability'   => ($event['venta_web'] ?? true) ? 'available' : 'sold-out',
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

        return self::IMAGE_BASE . $imagen;
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

        return '$' . ($num >= 1000
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

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $text
        );
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
