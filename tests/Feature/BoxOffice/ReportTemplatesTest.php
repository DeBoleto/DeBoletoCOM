<?php

namespace Tests\Feature\BoxOffice;

use App\Modules\BoxOffice\Exports\RaceParticipantsExport;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTemplatesTest extends TestCase
{
    private function base(): array
    {
        return [
            'title' => 'Reporte',
            'event' => tap(new EventoVista)->setRawAttributes(['pk_evento' => 1, 'evento' => 'Prueba', 'escenario' => 'Foro', 'direccion' => 'Centro', 'ciudad' => 'Villahermosa', 'estado' => 'Tabasco']),
            'period' => 'Domingo 08 de Noviembre de 2026',
            'generatedAt' => Carbon::now(),
            'pendingColumns' => true,
        ];
    }

    private function function(): EventoFuncionVista
    {
        return tap(new EventoFuncionVista)->setRawAttributes(['pk_evento_funcion' => 1, 'fecha_natural' => '08/11/2026', 'hora_natural' => '20:00']);
    }

    private function render(string $view, array $data): string
    {
        return Pdf::loadView("boxoffice.pdf.reports.{$view}", $this->base() + $data)->output();
    }

    public function test_all_report_pdfs_render(): void
    {
        $line = ['locality' => 'ORO', 'price' => 500.0, 'capacity' => 100, 'sold' => 10, 'courtesies' => 2, 'blocked' => 1, 'toSell' => 3, 'toCollect' => 1500.0, 'available' => 84, 'saleAmount' => 5000.0, 'pendingAmount' => 43500.0, 'total' => 16, 'percentage' => 12.0];
        $totals = array_map(fn ($value) => is_numeric($value) ? $value : 0, $line);
        $payment = ['title' => 'Efectivo', 'show_card' => true, 'card_label' => 'Terminal', 'rows' => [['locality' => 'ORO', 'quantity' => 2, 'unit_price' => 500.0, 'amount' => 1000.0, 'service' => 60.0, 'card' => 30.0, 'total' => 1090.0]], 'totals' => ['quantity' => 2, 'amount' => 1000.0, 'service' => 60.0, 'card' => 30.0, 'total' => 1090.0]];

        $outputs = [
            $this->render('general', ['functions' => [['function' => $this->function(), 'lines' => [$line], 'sold' => 10, 'courtesies' => 2, 'reserved' => 1, 'people' => 12, 'hasCapacity' => true, 'totals' => $totals]]]),
            $this->render('detailed', ['functions' => [[
                'function' => $this->function(),
                'general' => ['rows' => [['locality' => 'ORO', 'quantity' => 2, 'courtesies' => 1, 'blocked' => 0, 'unit_price' => 500.0, 'service' => 60.0, 'online' => 10.0, 'amount' => 1000.0, 'commissions' => 70.0, 'total' => 1070.0]], 'totals' => ['quantity' => 2, 'courtesies' => 1, 'service' => 60.0, 'online' => 10.0, 'amount' => 1000.0, 'commissions' => 70.0, 'total' => 1070.0]],
                'payments' => ['Cash' => $payment, 'Web' => ['rows' => []] + $payment],
            ]]]),
            $this->render('payments', ['rows' => [['date' => '01/11/2026', 'description' => 'Anticipo', 'reason' => 'Orden de pago', 'comments' => '', 'amount' => 5000.0]], 'total' => 5000.0]),
            $this->render('whatsapp', ['functions' => [['function' => $this->function(), 'rows' => [['locality' => 'ORO', 'purchases' => 3, 'tickets' => 5, 'cost' => 45.0]], 'totals' => ['purchases' => 3, 'tickets' => 5, 'cost' => 45.0]]]]),
            $this->render('parking', ['rows' => [['locality' => 'ORO', 'total' => 4]], 'total' => 4]),
            $this->render('daily', [
                'sections' => [['title' => 'Efectivo', 'rows' => [['purchase' => 9, 'time' => '10:30', 'seat' => null, 'locality' => 'ORO', 'tickets' => 2, 'amount' => 1000.0, 'commission' => 60.0]], 'totals' => ['tickets' => 2, 'amount' => 1000.0, 'commission' => 60.0]]],
                'localities' => [['locality' => 'ORO', 'tickets' => 2, 'amount' => 1000.0, 'commission' => 60.0]],
                'totals' => ['tickets' => 2, 'amount' => 1000.0, 'commission' => 60.0],
            ]),
        ];

        foreach ($outputs as $output) {
            $this->assertStringStartsWith('%PDF', $output);
        }
    }

    public function test_race_export_uses_event_catalogs(): void
    {
        $participant = (object) [
            'pk_evento_funcion_boleto' => 55, 'pk_evento_localidad' => 99, 'evento_localidad' => '10K', 'num_participante' => 12,
            'nombre' => 'Ana', 'correo' => 'a@b.mx', 'telefono' => '9931234567', 'creacion_fecha' => '2026-11-01 10:00:00',
            'fecha_nacimiento' => '1990-01-01', 'talla' => 2, 'tipo_sangre' => 4, 'tipo_extra' => 1, 'tipo_extrab' => 'ACME',
            'genero' => 2, 'discapacidad' => 'No', 'nombre_emergencia' => 'Luis', 'telefono_emergencia' => '9937654321',
        ];
        $pending = collect([(object) ['pk_evento_funcion_boleto' => 56, 'evento_localidad' => '10K']]);

        $default = (new RaceParticipantsExport(1, collect([$participant]), $pending))->array();
        $this->assertSame('Mediana', $default[0]['Talla']);
        $this->assertSame('O+', $default[0]['Tipo de sangre']);
        $this->assertSame('Femenino', $default[0]['Género']);
        $this->assertSame(56, $default[1]['Folio']);
        $this->assertSame('', $default[1]['Nombre']);

        $custom = (new RaceParticipantsExport(1157, collect([$participant]), collect()))->array();
        $this->assertSame('MEDIANA', $custom[0]['Talla']);
        $this->assertSame('Corte dama', $custom[0]['Corte Playera']);
        $this->assertSame('ACME', $custom[0]['EMPRESA/INSTITUCIÓN']);

        $doubles = new RaceParticipantsExport(1086, collect([$participant]), collect());
        $this->assertSame('B DOBLES', $doubles->array()[0]['SubCategoria']);
        $this->assertContains('Nombre de pareja', $doubles->headings());
    }
}
