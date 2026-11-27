<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\VenuesController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * sitemap.xml y robots.txt con las páginas públicas indexables: inicio,
 * eventos activos, recintos con eventos y categorías (desde Redis).
 */
class SitemapController extends Controller
{
    private const CACHE_SECONDS = 3600;

    public function index(CategoriesController $categories, VenuesController $venues): Response
    {
        $xml = Cache::remember('sitemap.xml:'.url('/'), self::CACHE_SECONDS, fn () => $this->render([
            ...$this->entries([route('home')], 'daily', '1.0'),
            ...$this->entries($this->eventUrls(), 'daily', '0.9'),
            ...$this->entries([route('buscar')], 'daily', '0.6'),
            ...$this->entries(array_map(
                fn ($slug) => route('buscar', ['categoria' => $slug]),
                rescue(fn () => $categories->sitemapSlugs(), []),
            ), 'daily', '0.7'),
            ...$this->entries(array_map(
                fn ($slug) => route('venue.detail', $slug),
                rescue(fn () => $venues->sitemapSlugs(), []),
            ), 'weekly', '0.6'),
        ]));

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /compra/',
            'Disallow: /boxoffice',
            'Disallow: /dashboard',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    private function eventUrls(): array
    {
        $events = json_decode((string) rescue(fn () => Redis::get('eventos_activos_app'), ''), true);

        return collect(is_array($events) ? $events : [])
            ->pluck('url')
            ->map(fn ($slug) => trim((string) $slug))
            ->filter()
            ->unique()
            ->map(fn ($slug) => route('event.detail', $slug))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $urls
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    private function entries(array $urls, string $changefreq, string $priority): array
    {
        return array_map(fn ($url) => ['loc' => $url, 'changefreq' => $changefreq, 'priority' => $priority], $urls);
    }

    private function render(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach (collect($urls)->unique('loc') as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc>\n";
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
