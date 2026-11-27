<?php

namespace App\Modules\Checkout\Tracking;

use App\Modules\Events\Models\EventosPixel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Píxeles de Meta de un evento. El navegador usa los de `eventos.pixel`
 * (separados por coma) y los de `eventos_pixel`; la API de conversiones solo
 * los de `eventos_pixel` que traen token.
 */
class MetaPixels
{
    private const CACHE_SECONDS = 600;

    /**
     * @return array{browser: list<string>, server: list<string>}
     */
    public function forEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            return ['browser' => [], 'server' => []];
        }

        return Cache::remember("meta_pixels:{$eventId}", self::CACHE_SECONDS, function () use ($eventId) {
            $rows = rescue(fn () => EventosPixel::query()->where('pk_evento', $eventId)->get(['pixel', 'api']), collect());
            $legacy = (string) rescue(fn () => DB::table('eventos')->where('pk_evento', $eventId)->value('pixel'), '');

            $browser = collect(preg_split('/[\s,;]+/', $legacy) ?: [])
                ->merge($rows->pluck('pixel'))
                ->map(fn ($id) => trim((string) $id))
                ->filter(fn ($id) => self::isPixelId($id))
                ->unique()
                ->values()
                ->all();

            $server = $rows
                ->filter(fn ($row) => self::isPixelId(trim((string) $row->pixel)) && trim((string) $row->api) !== '')
                ->map(fn ($row) => trim((string) $row->pixel))
                ->unique()
                ->values()
                ->all();

            return ['browser' => $browser, 'server' => $server];
        });
    }

    public static function isPixelId(string $id): bool
    {
        return preg_match('/^\d{5,20}$/', $id) === 1;
    }
}
