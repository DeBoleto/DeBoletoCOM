<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SearchController extends Controller
{
    private const KEY_PREFIX = 'search:events:';
    private const IDS_KEY = 'search:events:ids';
    private const TOKEN_PREFIX = 'search:token:';
    private const MAX_RESULTS = 6;

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (mb_strlen(trim($query)) < 2) {
            return response()->json([]);
        }

        $results = $this->searchFromRedis($query);

        if (empty($results)) {
            $results = $this->searchFromDatabase($query);
        }

        return response()->json(array_slice($results, 0, self::MAX_RESULTS));
    }

    private function searchFromRedis(string $query): array
    {
        $hasIds = Redis::exists(self::IDS_KEY);

        if (!$hasIds) {
            return [];
        }

        $words = preg_split('/[\s,–—\-_\/]+/u', mb_strtolower(trim($query)));
        $words = array_filter($words, function ($w) {
            $w = preg_replace('/[^a-záéíóúñü0-9]/u', '', trim($w));
            return mb_strlen($w) >= 2;
        });

        if (empty($words)) {
            return [];
        }

        $ids = null;
        foreach ($words as $word) {
            $word = preg_replace('/[^a-záéíóúñü0-9]/u', '', trim($word));
            $tokenIds = Redis::smembers(self::TOKEN_PREFIX . $word);

            if (empty($tokenIds)) {
                return [];
            }

            $tokenIdSet = array_map('intval', $tokenIds);

            if ($ids === null) {
                $ids = $tokenIdSet;
            } else {
                $ids = array_intersect($ids, $tokenIdSet);
            }

            if (empty($ids)) {
                return [];
            }
        }

        if (empty($ids)) {
            return $this->fallbackSearchInRedis($query);
        }

        $pipeline = Redis::pipeline();
        foreach ($ids as $id) {
            $pipeline->get(self::KEY_PREFIX . $id);
        }
        $jsonResults = $pipeline->exec();

        $results = [];
        foreach ($jsonResults as $json) {
            if ($json !== false && $json !== null) {
                $data = json_decode($json, true);
                if ($data !== null) {
                    $results[] = $data;
                }
            }
        }

        return $results;
    }

    private function fallbackSearchInRedis(string $query): array
    {
        $ids = Redis::smembers(self::IDS_KEY);

        if (empty($ids)) {
            return [];
        }

        $pipeline = Redis::pipeline();
        foreach ($ids as $id) {
            $pipeline->get(self::KEY_PREFIX . $id);
        }
        $jsonResults = $pipeline->exec();

        $q = mb_strtolower(trim($query));
        $results = [];

        foreach ($jsonResults as $json) {
            if ($json === false || $json === null) continue;
            $data = json_decode($json, true);
            if ($data === null) continue;

            if ($this->matchesQuery($data, $q)) {
                $results[] = $data;
            }
        }

        return $results;
    }

    private function matchesQuery(array $data, string $query): bool
    {
        $fields = array_filter([
            $data['title'] ?? '',
            $data['artist'] ?? '',
            $data['venue'] ?? '',
            $data['city'] ?? '',
        ]);

        foreach ($fields as $field) {
            if (mb_stripos($field, $query) !== false) {
                return true;
            }
        }

        return false;
    }

    private function searchFromDatabase(string $query): array
    {
        try {
            $rows = DB::table('view_eventos')
                ->where('pk_evento_status', 2)
                ->where('ocultar', '0')
                ->where(function ($q) use ($query) {
                    $q->where('evento', 'LIKE', '%' . $query . '%')
                      ->orWhere('escenario', 'LIKE', '%' . $query . '%')
                      ->orWhere('ciudad', 'LIKE', '%' . $query . '%');
                })
                ->orderBy('inicio_fecha', 'ASC')
                ->limit(self::MAX_RESULTS)
                ->get();
        } catch (\Exception $e) {
            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        return $this->transformResults($rows);
    }

    private function transformResults($rows): array
    {
        $results = [];
        foreach ($rows as $event) {
            $category = $this->resolveCategory($event);

            $results[] = [
                'id' => $event->pk_evento ?? 0,
                'slug' => $event->friendly_url ?? $this->slugify($event->evento ?? ''),
                'title' => $event->evento ?? '',
                'artist' => $event->artista ?? $event->evento ?? '',
                'category' => $category['name'],
                'categoryColor' => $category['color'],
                'date' => $this->formatDate($event->inicio_fecha ?? null),
                'dateISO' => $event->inicio_fecha ?? '',
                'venue' => $event->escenario ?? '',
                'city' => $event->ciudad ?? '',
                'image' => $this->resolveImage($event),
                'priceFormatted' => $this->formatPrice($event),
                'availability' => $event->availability ?? 'available',
            ];
        }

        return $results;
    }

    private function resolveCategory($event): array
    {
        $map = [
            'concierto' => ['name' => 'Concierto', 'color' => 'concert'],
            'festival' => ['name' => 'Festival', 'color' => 'festival'],
            'teatro' => ['name' => 'Teatro', 'color' => 'theater'],
            'conferencia' => ['name' => 'Conferencia', 'color' => 'conference'],
            'deportes' => ['name' => 'Deportes', 'color' => 'sports'],
            'electronica' => ['name' => 'Electrónica', 'color' => 'edm'],
        ];

        if (!empty($event->categoria_slug)) {
            $slug = strtolower(trim($event->categoria_slug));
            if (isset($map[$slug])) return $map[$slug];
        }

        if (!empty($event->pk_evento_categoria)) {
            $slugMap = [1 => 'concierto', 2 => 'festival', 3 => 'teatro', 4 => 'conferencia', 5 => 'deportes', 6 => 'electronica'];
            $slug = $slugMap[(int)$event->pk_evento_categoria] ?? 'concierto';
            return $map[$slug];
        }

        return ['name' => 'Concierto', 'color' => 'concert'];
    }

    private function resolveImage($event): string
    {
        $img = $event->imagen ?? '';
        if (empty($img)) return '/events/concert-01.png';
        if (!str_starts_with($img, '/')) return '/events/' . $img;
        return $img;
    }

    private function formatPrice($event): string
    {
        $precio = $event->precio ?? $event->desde ?? $event->priceFormatted ?? null;
        if ($precio === null) return '';
        if (is_string($precio) && str_starts_with($precio, '$')) return $precio;
        $num = (float)$precio;
        if ($num <= 0) return '';
        return '$' . ($num >= 1000 ? number_format($num, 0, '.', ',') : number_format($num, 0));
    }

    private function formatDate($date): string
    {
        if (empty($date)) return '';
        try {
            return \Carbon\Carbon::parse($date)->format('j M Y');
        } catch (\Exception $e) {
            return (string)$date;
        }
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
