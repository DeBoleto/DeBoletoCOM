<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;


class SearchController extends Controller
{
    private const REDIS_KEY = 'eventos_activos_app';
    private const MAX_RESULTS = 6;

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        if (mb_strlen(trim($query)) < 2) {
            return response()->json([]);
        }

        $raw = Redis::get(self::REDIS_KEY);

        if (!$raw) {
            return response()->json([]);
        }

        $events = json_decode($raw, true);

        if (!is_array($events)) {
            return response()->json([]);
        }

        $q = mb_strtolower(trim($query));
        $results = [];

        foreach ($events as $event) {
            if ($this->matchesQuery($event, $q)) {
                $results[] = $this->transformEvent($event);
            }

            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        return response()->json($results);
    }

    private function matchesQuery(array $event, string $query): bool
    {
        $fields = array_filter([
            $event['evento'] ?? '',
            $event['escenario'] ?? '',
            $event['ciudad'] ?? '',
        ]);

        foreach ($fields as $field) {
            if (mb_stripos($field, $query) !== false) {
                return true;
            }
        }

        return false;
    }

    private function transformEvent(array $event): array
    {
        $title = $event['evento'] ?? '';

        return [
            'id' => $event['id'] ?? 0,
            'slug' => $event['url'] ?? $this->slugify($title),
            'title' => $title,
            'artist' => $title,
            'category' => 'Concierto',
            'categoryColor' => 'concert',
            'date' => $event['fecha'] ?? '',
            'dateISO' => '',
            'venue' => $event['escenario'] ?? '',
            'city' => $event['ciudad'] ?? '',
            'image' => $this->resolveImage($event['imagen'] ?? ''),
            'priceFormatted' => $this->formatPrice($event['desde'] ?? null),
            'availability' => 'available',
        ];
    }

    private function resolveImage(string $imagen): string
    {
        if (empty($imagen)) {
            return '/events/concert-01.png';
        }

        $path = $imagen;
        if (!str_contains($path, '/')) {
            $ext = str_contains($path, '.') ? '' : '.png';
            $path = '/events/' . $path . $ext;
        }

        return $path;
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
