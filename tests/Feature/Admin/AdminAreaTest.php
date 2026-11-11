<?php

namespace Tests\Feature\Admin;

use App\Modules\Dashboard\Enums\StaffType;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    public function test_admin_login_screen_renders_with_admin_labels(): void
    {
        $this->get('/dashboard/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BoxOffice/Auth/Login', false)
                ->where('areaLabel', 'Administración')
                ->where('submitRoute', 'admin.login.store'));
    }

    public function test_guests_are_sent_to_admin_login(): void
    {
        $this->get('/dashboard/inicio')->assertRedirect('/dashboard/login');

        foreach (StaffType::sections() as $section) {
            $this->get("/dashboard/{$section}")->assertRedirect('/dashboard/login');
        }

        $this->get('/dashboard/administradores/3/documentos')->assertRedirect('/dashboard/login');
    }

    public function test_back_office_root_sends_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/dashboard/login');
    }

    public function test_customer_account_lives_under_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_staff_types_map_to_legacy_user_types(): void
    {
        $this->assertSame(1, StaffType::fromSection('administradores')->value);
        $this->assertSame(4, StaffType::fromSection('puntoventa')->value);
        $this->assertSame(5, StaffType::fromSection('usuarios')->value);
    }
}
