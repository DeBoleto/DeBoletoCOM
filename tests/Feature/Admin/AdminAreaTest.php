<?php

namespace Tests\Feature\Admin;

use App\Modules\Dashboard\Enums\StaffType;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    public function test_admin_login_screen_renders_with_admin_labels(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BoxOffice/Auth/Login', false)
                ->where('areaLabel', 'Administración')
                ->where('submitRoute', 'admin.login.store'));
    }

    public function test_guests_are_sent_to_admin_login(): void
    {
        $this->get('/admin/inicio')->assertRedirect('/admin/login');

        foreach (StaffType::sections() as $section) {
            $this->get("/admin/{$section}")->assertRedirect('/admin/login');
        }

        $this->get('/admin/administradores/3/documentos')->assertRedirect('/admin/login');
    }

    public function test_legacy_dashboard_links_redirect_to_admin(): void
    {
        $this->get('/dashboard/administradores')->assertRedirect('/admin/administradores')->assertStatus(301);
        $this->get('/dashboard/inicio')->assertRedirect('/admin/inicio');
    }

    public function test_customer_dashboard_is_untouched(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_staff_types_map_to_legacy_user_types(): void
    {
        $this->assertSame(1, StaffType::fromSection('administradores')->value);
        $this->assertSame(4, StaffType::fromSection('puntoventa')->value);
        $this->assertSame(5, StaffType::fromSection('usuarios')->value);
    }
}
