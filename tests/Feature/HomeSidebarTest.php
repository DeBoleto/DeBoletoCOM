<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeSidebarTest extends TestCase
{
    public function test_home_sidebar_only_lists_events_with_a_sidebar_image(): void
    {
        $this->withoutVite();

        $event = fn (int $id, $imagen) => [
            'id' => $id,
            'evento' => "Evento {$id}",
            'imagen' => $imagen,
            'desde' => 100,
            'ciudad' => 'Villahermosa',
            'estado' => 'Tabasco',
            'url' => "evento-{$id}",
        ];

        $values = [
            'eventos_sidebar_app' => json_encode([
                $event(1, 'slide1.jpeg'),
                $event(2, ''),
                $event(3, null),
                $event(4, '   '),
                $event(5, 'slide5.jpeg'),
            ]),
        ];
        Redis::shouldReceive('get')->andReturnUsing(fn ($key) => $values[$key] ?? null);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('sidebarEvents', 2)
            ->where('sidebarEvents.0.id', 1)
            ->where('sidebarEvents.1.id', 5)
            ->has('banners', 2)
            ->where('banners.0.url', '/evento/evento-1')
            ->where('banners.1.url', '/evento/evento-5')
        );
    }
}
