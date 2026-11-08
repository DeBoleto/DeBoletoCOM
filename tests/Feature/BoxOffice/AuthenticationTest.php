<?php

namespace Tests\Feature\BoxOffice;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_screen_renders(): void
    {
        $this->get('/boxoffice/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('BoxOffice/Auth/Login', false));
    }

    public function test_guests_are_sent_to_the_box_office_login(): void
    {
        $this->get('/boxoffice/inicio')->assertRedirect('/boxoffice/login');
        $this->get('/boxoffice/tickets')->assertRedirect('/boxoffice/login');
        $this->get('/boxoffice/ventas/10/corte-caja')->assertRedirect('/boxoffice/login');
    }

    public function test_phase_two_sections_require_login(): void
    {
        foreach (['tickets-courtesy', 'tickets-courtesy-create', 'tickets-exchange', 'tickets-count', 'tickets-change', 'boletos-venta', 'reportes', 'reportes-por-dia', 'reportes-contador-entradas', 'reportes-por-categoria', 'escenarios/capacity', 'reportes/general/10'] as $section) {
            $this->get("/boxoffice/{$section}")->assertRedirect('/boxoffice/login');
        }

        $this->postJson('/boxoffice/tickets-count/escanear', ['codigo' => '1'])->assertUnauthorized();
        $this->delete('/boxoffice/tickets-courtesy/boletos/5')->assertRedirect('/boxoffice/login');
    }

    public function test_seller_cut_is_no_longer_public(): void
    {
        $this->get('/boxoffice/ventas/10/corte-caja/usuario/3?fecha=2026-11-08')->assertRedirect('/boxoffice/login');
    }

    public function test_logout_requires_post(): void
    {
        $this->get('/boxoffice/logout')->assertStatus(405);
    }

    public function test_login_validates_credentials_format(): void
    {
        $this->post('/boxoffice/login', ['correo' => 'no-es-correo', 'password' => ''])
            ->assertSessionHasErrors(['correo', 'password']);
    }
}
