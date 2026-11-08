<?php

namespace Tests\Feature\Admin;

use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Venues\Models\EscenariosFormatos;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    public function test_event_and_venue_routes_require_admin_login(): void
    {
        foreach (['/admin/eventos', '/admin/eventos/nuevo', '/admin/eventos/5', '/admin/eventos/5/boletos', '/admin/eventos/5/reembolsos',
            '/admin/eventos/defender', '/admin/eventos/cortesias', '/admin/escenarios', '/admin/actualizar', '/admin/tickets-unlock'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->postJson('/admin/escenarios/vista-previa', ['xml' => '<stage/>'])->assertUnauthorized();
        $this->delete('/admin/eventos/5/boletos/9')->assertRedirect('/admin/login');
        $this->post('/admin/eventos/5/bloqueos-olmecas')->assertRedirect('/admin/login');
    }

    public function test_format_localities_are_read_from_xml(): void
    {
        $format = (new EscenariosFormatos)->forceFill([
            'escenario_xml' => file_get_contents(base_path('PosadaConSabor.xml')),
        ]);

        $localities = $format->localities();

        $this->assertNotEmpty($localities);
        $this->assertSame('Recinto Generico', $localities[1]);
    }

    public function test_olmecas_blocking_plan_is_well_formed(): void
    {
        $plan = config('olmecas_blocking');

        $this->assertArrayHasKey('Zona A', $plan);

        foreach ($plan as $zone => $groups) {
            foreach ($groups as $statusGroups) {
                foreach ($statusGroups as $seats) {
                    $this->assertIsArray($seats, $zone);
                    $this->assertSame(count($seats), count(array_unique($seats)), "Asientos repetidos en {$zone}");
                }
            }
        }
    }

    public function test_buyers_pdf_renders(): void
    {
        $event = tap(new EventoVista)->setRawAttributes(['pk_evento' => 1, 'evento' => 'Prueba', 'escenario' => 'Foro', 'direccion' => 'Centro', 'ciudad' => 'Villahermosa', 'estado' => 'Tabasco']);

        $pdf = Pdf::loadView('admin.pdf.buyers', [
            'event' => $event,
            'date' => 'Hoy',
            'buyers' => collect([(object) ['nombre' => 'Ana', 'adquisicion_correo' => 'a@b.mx', 'telefono' => '993', 'boletos' => 2]]),
        ])->output();

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
