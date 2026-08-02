<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SyncSearchData extends Command
{
    protected $signature = 'events:sync-search';
    protected $description = 'Sincroniza datos de eventos desde MySQL a Redis para búsqueda (clave eventos_activos_app)';

    private const REDIS_KEY = 'eventos_activos_app';

    public function handle(): int
    {
        $events = $this->fetchEvents();

        if (empty($events)) {
            $this->warn('No se encontraron eventos para sincronizar.');
            return self::SUCCESS;
        }

        $categories = $this->fetchCategories();

        $bar = $this->output->createProgressBar(count($events));
        $bar->start();

        $data = [];
        foreach ($events as $event) {
            $data[] = $this->transformEvent($event, $categories);
            $bar->advance();
        }

        Redis::set(self::REDIS_KEY, json_encode($data, JSON_UNESCAPED_UNICODE));

        $bar->finish();
        $this->newLine();
        $this->info(count($data) . ' eventos sincronizados en Redis (clave: ' . self::REDIS_KEY . ').');

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

    private function transformEvent($event, array $categories = []): array
    {
        $category = $categories[$event->pk_evento ?? $event->id ?? 0] ?? null;

        return [
            'id' => (int) ($event->pk_evento ?? $event->id ?? 0),
            'evento' => $event->evento ?? $event->title ?? '',
            'imagen' => $this->resolveImageName($event),
            'fecha' => $this->formatDate($event->inicio_fecha ?? $event->date ?? null),
            'desde' => (float) ($event->desde ?? $event->precio ?? 0),
            'ciudad' => $event->ciudad ?? $event->city ?? '',
            'escenario' => $event->escenario ?? $event->venue ?? '',
            'estado' => $event->estado ?? '',
            'venta_web' => true,
            'url' => $event->friendly_url ?? $event->url ?? $event->slug ?? $this->slugify($event->evento ?? ''),
            'categoria' => $category['slug'] ?? null,
            'categoria_nombre' => $category['nombre'] ?? null,
            'categoria_imagen' => $category['imagen'] ?? null,
        ];
    }

    private function fetchCategories(): array
    {
        $categories = [];

        try {
            $hasImage = \Illuminate\Support\Facades\Schema::hasColumn('eventos_categorias', 'imagen');

            $columns = ['pk_evento_categoria', 'evento_categoria', 'friendly_url'];

            if ($hasImage) {
                $columns[] = 'imagen';
            }

            $rows = DB::table('eventos_categorias')
                ->where('eliminado', 0)
                ->get($columns);
        } catch (\Exception $e) {
            $rows = collect();
        }

        $catalog = [];
        foreach ($rows as $row) {
            $catalog[(int) $row->pk_evento_categoria] = [
                'slug' => $this->slugify($row->friendly_url ?? $row->evento_categoria ?? ''),
                'nombre' => trim((string) ($row->evento_categoria ?? '')),
                'imagen' => trim((string) ($row->imagen ?? '')),
            ];
        }

        if (empty($catalog)) {
            return [];
        }

        try {
            $map = DB::table('eventos_map_eventos_categorias')
                ->get(['pk_evento', 'pk_evento_categoria']);
        } catch (\Exception $e) {
            return [];
        }

        foreach ($map as $link) {
            $category = $catalog[(int) $link->pk_evento_categoria] ?? null;

            if ($category && !empty($category['slug'])) {
                $categories[(int) $link->pk_evento] = $category;
            }
        }

        return $categories;
    }

    private function resolveImageName($event): string
    {
        $img = $event->imagen ?? $event->image ?? '';

        if (empty($img)) {
            return '';
        }

        $img = basename($img);

        $img = preg_replace('/\.(png|jpg|jpeg|webp|avif)$/i', '', $img);

        return $img;
    }

    private function formatDate($date): string
    {
        if (empty($date)) return '';

        try {
            $dt = \Carbon\Carbon::parse($date);
            $meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
            return $dt->format('j') . ' ' . $meses[(int) $dt->format('n') - 1];
        } catch (\Exception $e) {
            if (is_string($date)) return $date;
            return (string) $date;
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
