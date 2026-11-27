<?php

namespace Tests\Feature;

use App\Support\Seo\SeoMeta;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SeoMetaTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['app.name' => 'DeBoletoMX']);
    }

    public function test_event_detail_renders_meta_tags_in_the_server_html(): void
    {
        $this->fakeRedis([
            'detalle_evento:leones-vs-olmecas' => json_encode([
                'id' => 1143,
                'nombre' => 'Leones VS Olmecas',
                'descripcion' => '<p>Vive la emoción del <b>béisbol</b>.</p>',
                'comentarios' => '',
                'imagen' => 'leones.jpeg',
                'tiene_promocion' => false,
                'ventaWeb' => true,
                'lugar' => 'Estadio Centenario',
                'ciudad' => 'Villahermosa',
                'estado' => 'Tabasco',
                'kit' => null,
                'funciones' => [['id' => 1305, 'fecha' => '2026-07-25T19:00:00']],
                'zonas' => [
                    ['id' => 1, 'nombre' => 'A', 'precio_original' => 150.0, 'precio_descuento' => 110.0, 'tiene_promocion' => true],
                    ['id' => 2, 'nombre' => 'B', 'precio_original' => 300.0, 'precio_descuento' => 0, 'tiene_promocion' => false],
                ],
            ]),
        ]);

        $html = $this->get('/evento/leones-vs-olmecas')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Leones VS Olmecas — DeBoletoMX</title>', $html);
        $this->assertStringContainsString('property="og:title" content="Leones VS Olmecas"', $html);
        $this->assertStringContainsString('property="og:description" content="Vive la emoción del béisbol ."', $html);
        $image = env('USE_LOCAL_JSON', false) ? asset('hero-bg.png') : config('app.deboleto_url').'/images/eventos/leones.jpeg';
        $this->assertStringContainsString('property="og:image" content="'.$image.'"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/evento/leones-vs-olmecas').'"', $html);
        $this->assertStringContainsString('name="robots" content="index, follow"', $html);

        preg_match('/<script inertia="schema-0" type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
        $schema = json_decode($match[1] ?? '', true);

        $this->assertSame('Event', $schema['@type']);
        $this->assertSame('2026-07-25T19:00:00-06:00', $schema['startDate']);
        $this->assertSame('Estadio Centenario', $schema['location']['name']);
        $this->assertEquals(110, $schema['offers']['lowPrice']);
        $this->assertEquals(300, $schema['offers']['highPrice']);
    }

    public function test_seo_meta_cleans_descriptions_and_makes_urls_absolute(): void
    {
        $seo = SeoMeta::make('Evento', '<p>Hola&nbsp;<br>mundo</p>'.str_repeat(' texto', 60))
            ->url('/evento/demo')
            ->image('//cdn.example.com/a.jpg')
            ->indexable(false)
            ->toArray();

        $this->assertStringStartsWith('Hola mundo texto', $seo['description']);
        $this->assertLessThanOrEqual(161, mb_strlen($seo['description']));
        $this->assertSame(url('/evento/demo'), $seo['url']);
        $this->assertSame('https://cdn.example.com/a.jpg', $seo['image']['url']);
        $this->assertSame('noindex, nofollow', $seo['robots']);
        $this->assertSame('Evento — DeBoletoMX', $seo['fullTitle']);
    }

    private function fakeRedis(array $values): void
    {
        Redis::shouldReceive('get')->andReturnUsing(fn ($key) => $values[$key] ?? null);
    }
}
