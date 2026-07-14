<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SyncSearchData extends Command
{
    protected $signature = 'events:sync-search {--flush : Limpiar datos de búsqueda en Redis antes de sincronizar}';
    protected $description = 'Sincroniza datos de eventos desde MySQL a Redis para búsqueda rápida';

    private const KEY_PREFIX = 'search:events:';
    private const IDS_KEY = 'search:events:ids';
    private const TOKEN_PREFIX = 'search:token:';

    public function handle(): int
    {
        if ($this->option('flush')) {
            $this->flushRedis();
        }

        $events = $this->fetchEvents();

        if (empty($events)) {
            $this->warn('No se encontraron eventos para sincronizar.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($events));
        $bar->start();

        $pipeline = Redis::pipeline();

        foreach ($events as $event) {
            $eventId = $event->pk_evento ?? $event->id;

            $searchData = $this->transformEvent($event);

            $pipeline->set(self::KEY_PREFIX . $eventId, json_encode($searchData));
            $pipeline->sadd(self::IDS_KEY, $eventId);

            $this->indexTokens($pipeline, $eventId, $searchData);

            $bar->advance();
        }

        $pipeline->exec();
        $bar->finish();

        $this->newLine();
        $this->info(count($events) . ' eventos sincronizados en Redis.');

        return self::SUCCESS;
    }

    private function fetchEvents(): array
    {
        try {
            $rows = DB::table('view_eventos')
                ->where('pk_evento_status', 2)
                ->where('ocultar', '0')
                ->orderBy('inicio_fecha', 'ASC')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->toArray();
            }
        } catch (\Exception $e) {
            $this->warn('Vista view_eventos no disponible, usando tabla eventos directamente.');
        }

        try {
            return DB::table('eventos')
                ->where('pk_evento_status', 2)
                ->where('eliminado', 0)
                ->where('ocultar', '0')
                ->orderBy('inicio_fecha', 'ASC')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $this->error('Error al consultar eventos: ' . $e->getMessage());
            return [];
        }
    }

    private function transformEvent($event): array
    {
        $category = $this->resolveCategory($event);
        $venue = $this->resolveVenue($event);
        $image = $this->resolveImage($event);

        return [
            'id' => $event->pk_evento ?? $event->id ?? 0,
            'slug' => $event->friendly_url ?? $this->slugify($event->evento ?? ''),
            'title' => $event->evento ?? $event->title ?? '',
            'artist' => $event->artista ?? $event->artist ?? $event->evento ?? '',
            'category' => $category['name'],
            'categoryColor' => $category['color'],
            'date' => $this->formatDate($event->inicio_fecha ?? $event->date ?? null),
            'dateISO' => $event->inicio_fecha ?? $event->dateISO ?? '',
            'venue' => $venue,
            'city' => $event->ciudad ?? $event->city ?? '',
            'image' => $image,
            'priceFormatted' => $this->formatPrice($event),
            'availability' => $event->availability ?? 'available',
        ];
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
            if (isset($map[$slug])) {
                return $map[$slug];
            }
        }

        if (!empty($event->pk_evento_categoria)) {
            $slugMap = [
                1 => 'concierto',
                2 => 'festival',
                3 => 'teatro',
                4 => 'conferencia',
                5 => 'deportes',
                6 => 'electronica',
            ];
            $slug = $slugMap[(int)$event->pk_evento_categoria] ?? 'concierto';
            return $map[$slug];
        }

        if (!empty($event->categoria)) {
            $cat = strtolower(trim($event->categoria));
            foreach ($map as $key => $val) {
                if (str_contains($cat, $key)) {
                    return $val;
                }
            }
        }

        return ['name' => 'Concierto', 'color' => 'concert'];
    }

    private function resolveVenue($event): string
    {
        if (!empty($event->escenario)) {
            return $event->escenario;
        }
        if (!empty($event->venue)) {
            return $event->venue;
        }
        if (!empty($event->pk_escenario)) {
            try {
                $venue = DB::table('escenarios')
                    ->where('pk_escenario', $event->pk_escenario)
                    ->value('escenario');
                if ($venue) return $venue;
            } catch (\Exception $e) {}
        }
        return $event->escenario_nombre ?? '';
    }

    private function resolveImage($event): string
    {
        $img = $event->imagen ?? $event->image ?? '';
        if (empty($img)) return '/events/concert-01.png';
        if (!str_starts_with($img, '/')) {
            $ext = str_contains($img, '.') ? '' : '.png';
            return '/events/' . $img . $ext;
        }
        return $img;
    }

    private function formatPrice($event): string
    {
        $precio = $event->precio ?? $event->priceFormatted ?? $event->desde ?? null;
        if ($precio === null) return '';

        if (is_string($precio) && str_starts_with($precio, '$')) {
            return $precio;
        }

        $num = (float)$precio;
        if ($num <= 0) return '';

        if ($num >= 1000) {
            return '$' . number_format($num, 0, '.', ',');
        }
        return '$' . number_format($num, 0);
    }

    private function formatDate($date): string
    {
        if (empty($date)) return '';
        if ($date instanceof \DateTime || $date instanceof \Carbon\Carbon) {
            return $date->format('j M Y');
        }
        if (is_string($date)) {
            try {
                return \Carbon\Carbon::parse($date)->format('j M Y');
            } catch (\Exception $e) {
                return $date;
            }
        }
        return (string)$date;
    }

    private function indexTokens($pipeline, int $eventId, array $data): void
    {
        $stopWords = ['el', 'la', 'los', 'las', 'de', 'del', 'en', 'un', 'una', 'y', 'e', 'o', 'a', 'con', 'por', 'para', 'que', 'es', 'su', 'lo', 'se', 'no', 'le', 'al'];

        $textFields = array_filter([
            $data['title'] ?? '',
            $data['artist'] ?? '',
            $data['venue'] ?? '',
            $data['city'] ?? '',
        ]);

        $tokens = [];
        foreach ($textFields as $text) {
            $words = preg_split('/[\s,–—\-_\/]+/u', mb_strtolower(trim($text)));
            foreach ($words as $word) {
                $word = trim($word);
                $word = preg_replace('/[^a-záéíóúñüA-ZÁÉÍÓÚÑÜ0-9]/u', '', $word);
                if (mb_strlen($word) < 2) continue;
                if (in_array($word, $stopWords)) continue;
                $tokens[$word] = true;
            }
        }

        foreach (array_keys($tokens) as $token) {
            $pipeline->sadd(self::TOKEN_PREFIX . $token, $eventId);
        }
    }

    private function flushRedis(): void
    {
        $keys = Redis::keys(self::KEY_PREFIX . '*');
        if (!empty($keys)) {
            Redis::del($keys);
        }

        $tokenKeys = Redis::keys(self::TOKEN_PREFIX . '*');
        if (!empty($tokenKeys)) {
            Redis::del($tokenKeys);
        }

        Redis::del(self::IDS_KEY);

        $this->info('Datos de búsqueda eliminados de Redis.');
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
        $text = trim($text, '-');
        return $text;
    }
}
