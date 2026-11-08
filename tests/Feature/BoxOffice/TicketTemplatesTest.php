<?php

namespace Tests\Feature\BoxOffice;

use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoLocalidadVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Users\Models\Usuarios;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketTemplatesTest extends TestCase
{
    public static function formats(): array
    {
        $config = require __DIR__.'/../../../config/boxoffice.php';

        return collect(array_merge(array_values($config['print_formats']), $config['unassigned_print_formats']))
            ->unique()
            ->mapWithKeys(fn (string $format) => [$format => [$format]])
            ->all();
    }

    #[DataProvider('formats')]
    public function test_print_format_renders(string $format): void
    {
        $html = view('boxoffice.print.formats.'.$format, $this->ticketData())->render();

        $this->assertStringContainsString('window.print', $html);
        $this->assertStringNotContainsString('{--', str_replace('{{--', '', $html));
    }

    public function test_digital_ticket_pdf_renders(): void
    {
        $output = Pdf::loadView('boxoffice.pdf.digital-ticket', $this->ticketData())->output();

        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_cash_cut_and_sales_reports_render(): void
    {
        $section = fn (string $title, bool $card) => [
            'title' => $title,
            'show_card_commission' => $card,
            'rows' => [[
                'locality' => 'ORO', 'quantity' => 2, 'unit_price' => 500.0, 'tickets' => 1000.0,
                'commission' => 60.0, 'card_commission' => $card ? 35.0 : 0.0, 'total' => 1060.0,
            ]],
            'totals' => ['quantity' => 2, 'tickets' => 1000.0, 'commission' => 60.0, 'card_commission' => 0.0, 'total' => 1060.0],
        ];

        $function = $this->model(EventoFuncionVista::class, ['pk_evento_funcion' => 1, 'fecha_natural' => '08/11/2026', 'hora_natural' => '20:00']);
        $common = [
            'title' => 'Corte de caja',
            'event' => $this->event(),
            'seller' => $this->seller(),
            'period' => 'Domingo 08 de Noviembre de 2026',
            'generatedAt' => Carbon::now(),
        ];

        $cashCut = Pdf::loadView('boxoffice.pdf.cash-cut', $common + ['functions' => [[
            'function' => $function,
            'sections' => ['Cash' => $section('Efectivo', false), 'Terminal' => $section('Terminal bancaria', true), 'Transfer' => ['rows' => []] + $section('Transferencia', false)],
        ]]])->output();

        $byDate = Pdf::loadView('boxoffice.pdf.sales-by-date', $common + ['functions' => [[
            'function' => $function,
            'sections' => [
                'general' => [
                    'rows' => [['locality' => 'ORO', 'quantity' => 2, 'courtesies' => 1, 'unit_price' => 500.0, 'total' => 1000.0]],
                    'totals' => ['quantity' => 2, 'courtesies' => 1, 'total' => 1000.0],
                ],
                'Cash' => $section('Efectivo', false),
                'Web' => $section('Venta web', false),
            ],
        ]]])->output();

        $this->assertStringStartsWith('%PDF', $cashCut);
        $this->assertStringStartsWith('%PDF', $byDate);
    }

    private function ticketData(): array
    {
        $ticket = fn (int $id, string $seat, int $courtesy) => [
            'pk_evento_funcion_boleto' => $id,
            'pk_evento_compra' => 5001,
            'pk_evento_localidad' => 3551,
            'funcion_fecha' => '2026-11-08 20:00:00',
            'funcion_fecha_natural' => '08/11/2026',
            'funcion_hora' => '20:00',
            'localidad' => 'ORO',
            'localidad_text' => $courtesy ? '' : 'Niño',
            'localidad_id' => 0,
            'asiento' => $seat,
            'codigo' => md5((string) $id),
            'folio' => $id,
            'cortesia' => $courtesy,
            'precio' => '500.00',
            'comision' => '30.00',
            'total' => '530.00',
            'mesas' => str_contains($seat, '-'),
        ];

        return [
            'evento' => $this->event(),
            'funcion' => $this->model(EventoFuncionVista::class, [
                'pk_evento_funcion' => 1, 'fecha' => '2026-11-08 20:00:00', 'fecha_natural' => '08/11/2026', 'hora_natural' => '20:00',
            ]),
            'localidad' => $this->model(EventoLocalidadVista::class, ['pk_evento_localidad' => 3551, 'evento_localidad' => 'ORO']),
            'boletos' => [$ticket(101, 'A12', 0), $ticket(102, 'NA_NA1', 1), $ticket(103, 'M4-2', 0)],
        ];
    }

    private function event(): EventoVista
    {
        return $this->model(EventoVista::class, [
            'pk_evento' => 504, 'evento' => 'Concierto de prueba', 'escenario' => 'Teatro Esperanza Iris',
            'direccion' => 'Centro', 'ciudad' => 'Villahermosa', 'estado' => 'Tabasco',
            'titulo_boleto' => 'CONCIERTO', 'subtitulo_boleto' => 'GIRA 2026', 'imagen_boleto' => 'boleto.png',
            'imagen' => 'evento.png', 'pk_evento_categoria' => 1, 'pk_evento_tipo' => 1,
        ]);
    }

    private function seller(): Usuarios
    {
        return (new Usuarios)->forceFill(['pk_usuario' => 7, 'nombre' => 'Ana', 'apellido_paterno' => 'López', 'correo' => 'ana@example.com']);
    }

    private function model(string $class, array $attributes)
    {
        return tap(new $class)->setRawAttributes($attributes);
    }
}
