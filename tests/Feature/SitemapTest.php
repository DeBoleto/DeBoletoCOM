<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_sitemap_lists_home_active_events_categories_and_venues(): void
    {
        $values = [
            'eventos_activos_app' => json_encode([
                ['id' => 1, 'url' => 'leones-vs-olmecas', 'evento' => 'Leones'],
                ['id' => 2, 'url' => '', 'evento' => 'Sin slug'],
            ]),
            'eventos_por_categoria' => json_encode([
                'Conciertos & Shows' => [['id' => 3, 'evento' => 'Concierto', 'imagen' => '']],
            ]),
            'escenarios_unicos' => json_encode([]),
            'eventos_por_escenario' => json_encode([]),
        ];
        Redis::shouldReceive('get')->andReturnUsing(fn ($key) => $values[$key] ?? null);

        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = $response->getContent();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/evento/leones-vs-olmecas').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/buscar').'?categoria=', $xml);
        $this->assertStringNotContainsString('/evento/</loc>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_robots_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertSee('Disallow: /compra/', false);
    }
}
