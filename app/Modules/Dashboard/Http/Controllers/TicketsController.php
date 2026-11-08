<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Dashboard\Exports\RowsExport;
use App\Modules\Dashboard\Services\OlmecasBlocking;
use App\Modules\Dashboard\Services\TicketAdministration;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Boletos emitidos de un evento: consulta, baja, reimpresión y exportación.
 */
class TicketsController extends Controller
{
    public function __construct(private readonly TicketAdministration $tickets) {}

    public function index(Request $request, int $evento): Response
    {
        $event = EventoVista::query()->findOrFail($evento);
        $search = trim((string) $request->query('buscar', ''));
        $printedOnly = $request->boolean('impresos');

        $tickets = $this->baseQuery($evento)
            ->when($printedOnly, fn (Builder $query) => $query->where('b.impresion', 1))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    if (ctype_digit($search)) {
                        $inner->where('b.pk_evento_funcion_boleto', (int) $search)->orWhere('b.pk_evento_compra', (int) $search);
                    }
                    $inner->orWhere('b.asiento', $search)->orWhere('u.correo', 'like', '%'.addcslashes($search, '%_\\').'%');
                });
            })
            ->orderByDesc('b.pk_evento_funcion_boleto')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => (int) $row->pk_evento_funcion_boleto,
                'purchase' => (int) $row->pk_evento_compra,
                'locality' => $row->evento_localidad,
                'seat' => str_starts_with((string) $row->asiento, 'NA_NA') ? null : $row->asiento,
                'price' => (float) $row->precio,
                'courtesy' => (bool) $row->cortesia,
                'printed' => (bool) $row->impresion,
                'payment' => $row->evento_compra_tipo,
                'buyer' => $row->correo,
                'date' => $row->adquisicion_fecha ? Carbon::parse($row->adquisicion_fecha)->format('d/m/Y H:i') : null,
            ]);

        return Inertia::render('Admin/Tickets/Index', [
            'event' => ['id' => $event->pk_evento, 'name' => $event->evento, 'olmecas' => (int) $event->pk_evento_categoria === OlmecasBlocking::CATEGORY],
            'tickets' => $tickets,
            'filters' => ['buscar' => $search, 'impresos' => $printedOnly],
        ]);
    }

    public function destroy(Request $request, int $evento, int $boleto): RedirectResponse
    {
        $ticket = EventosFuncionesBoletos::query()->where('pk_evento', $evento)->findOrFail($boleto);
        $this->tickets->delete($ticket, $request->user());

        return back()->with('success', "Boleto {$boleto} eliminado.");
    }

    public function unlock(Request $request, int $evento, int $boleto): RedirectResponse
    {
        EventosFuncionesBoletos::query()->where('pk_evento', $evento)->findOrFail($boleto)
            ->forceFill(['impresion' => 0])->save();

        return back()->with('success', "El boleto {$boleto} se puede volver a imprimir.");
    }

    public function export(int $evento): BinaryFileResponse
    {
        $event = EventoVista::query()->findOrFail($evento);
        $type = CommissionType::tryFrom((int) $event->pk_evento_comision_tipo);
        $stored = $event->pk_evento >= (int) config('boxoffice.rules.stored_commission_since_event');
        $whatsappFee = $this->whatsappFeePerTicket($evento);

        $rows = $this->baseQuery($evento)
            ->leftJoin('eventos_localidades_especiales as le', 'le.pk_evento_localidad_especiales', '=', 'b.pk_evento_localidad_especiales')
            ->join('eventos_funciones as f', 'f.pk_evento_funcion', '=', 'b.pk_evento_funcion')
            ->whereNotIn('b.pk_evento_funcion_boleto_status', [6, 7])
            ->addSelect(['f.fecha as funcion', 'l.comision', 'le.comision as comision_especial', 'b.comision_deboleto', 'b.comision_servicio', 'b.codigo', 'c.pk_evento_compra_tipo', 'c.comentario', 'u.nombre', 'u.apellido_paterno', 'u.apellido_materno', 'u.telefono'])
            ->orderBy('b.pk_evento_funcion_boleto')
            ->get()
            ->map(function ($row) use ($type, $stored, $whatsappFee) {
                $commission = match (true) {
                    (bool) $row->cortesia => 0.0,
                    $stored => (float) $row->comision_deboleto,
                    in_array($type, [CommissionType::Percentage, CommissionType::PercentageNoCardFee], true) => (float) $row->precio * ((float) $row->comision / 100),
                    $type === CommissionType::Fixed && $row->comision_especial !== null => (float) $row->comision_especial,
                    default => (float) $row->comision,
                };
                $card = (int) $row->pk_evento_compra_tipo === PaymentType::Terminal->value ? (float) $row->comision_servicio : 0.0;

                return [
                    'Folio' => (int) $row->pk_evento_funcion_boleto,
                    'Función' => $row->funcion,
                    'Zona' => $row->evento_localidad,
                    'Asiento' => $row->asiento,
                    'Precio' => (float) $row->precio,
                    'Comisión' => round($commission, 2),
                    'Comisión tarjeta' => round($card, 2),
                    'Comisión WhatsApp' => (int) $row->pk_evento_compra_tipo === PaymentType::Web->value ? round($whatsappFee, 2) : 0,
                    'Total' => $row->cortesia ? 0 : round((float) $row->precio + $commission + $card, 2),
                    'Folio de compra' => (int) $row->pk_evento_compra,
                    'Fecha de compra' => $row->adquisicion_fecha,
                    'Usuario' => trim("{$row->nombre} {$row->apellido_paterno} {$row->apellido_materno}"),
                    'Correo' => $row->correo,
                    'Teléfono' => $row->telefono,
                    'Código' => $row->codigo,
                    'Forma de pago' => $row->evento_compra_tipo,
                    'Tipo de boleto' => $row->cortesia ? 'Cortesía' : 'Venta',
                    'Impreso' => $row->impresion ? 'Sí' : 'No',
                    'Comentario' => $row->comentario,
                ];
            })
            ->all();

        return Excel::download(new RowsExport($rows), 'boletos-'.$evento.'.xlsx');
    }

    public function exportByDate(Request $request): BinaryFileResponse
    {
        $day = Carbon::createFromFormat('Y-m-d', $request->validate(['fecha' => ['required', 'date_format:Y-m-d']])['fecha'], config('boxoffice.timezone'));

        $rows = DB::table('eventos_funciones_boletos as b')
            ->join('eventos as e', 'e.pk_evento', '=', 'b.pk_evento')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->join('eventos_compras_tipos as t', 't.pk_evento_compra_tipo', '=', 'c.pk_evento_compra_tipo')
            ->leftJoin('usuarios as u', 'u.pk_usuario', '=', 'b.creacion_pk_usuario')
            ->where('b.creacion_fecha', '>=', $day->copy()->startOfDay()->toDateTimeString())
            ->where('b.creacion_fecha', '<', $day->copy()->addDay()->startOfDay()->toDateTimeString())
            ->orderBy('b.pk_evento_funcion_boleto')
            ->get(['e.pk_evento', 'e.evento', 'b.pk_evento_funcion_boleto', 'l.evento_localidad', 'b.asiento', 'b.precio', 'b.comision_deboleto', 'b.cortesia', 'b.pk_evento_compra', 't.evento_compra_tipo', 'b.creacion_fecha', 'u.correo'])
            ->map(fn ($row) => [
                'Evento' => $row->evento,
                'Id evento' => (int) $row->pk_evento,
                'Folio' => (int) $row->pk_evento_funcion_boleto,
                'Zona' => $row->evento_localidad,
                'Asiento' => $row->asiento,
                'Precio' => (float) $row->precio,
                'Comisión' => (float) $row->comision_deboleto,
                'Tipo de boleto' => $row->cortesia ? 'Cortesía' : 'Venta',
                'Folio de compra' => (int) $row->pk_evento_compra,
                'Forma de pago' => $row->evento_compra_tipo,
                'Fecha' => $row->creacion_fecha,
                'Registró' => $row->correo,
            ])
            ->all();

        return Excel::download(new RowsExport($rows, ['Evento', 'Folio']), "boletos-{$day->toDateString()}.xlsx");
    }

    public function enablePrinting(Request $request, int $compra): RedirectResponse
    {
        $count = $this->tickets->enablePrinting(EventosCompras::query()->findOrFail($compra), $request->user());

        return back()->with('success', "Se habilitó la reimpresión de {$count} boletos.");
    }

    public function digital(int $compra, TicketPrintService $printing): HttpResponse
    {
        return $printing->digitalDocument(EventosCompras::query()->findOrFail($compra))->stream("compra-{$compra}.pdf");
    }

    public function olmecas(Request $request, OlmecasBlocking $blocking, int $evento): RedirectResponse
    {
        $result = $blocking->generate(Eventos::query()->findOrFail($evento), $request->user());

        return back()->with('success', "Bloqueos generados: {$result['tickets']} boletos en {$result['purchases']} compras ({$result['skipped']} asientos ya existían).");
    }

    private function baseQuery(int $eventId): Builder
    {
        return DB::table('eventos_funciones_boletos as b')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->join('eventos_compras_tipos as t', 't.pk_evento_compra_tipo', '=', 'c.pk_evento_compra_tipo')
            ->leftJoin('usuarios as u', 'u.pk_usuario', '=', 'b.adquisicion_pk_usuario')
            ->where('b.pk_evento', $eventId)
            ->where('b.ocultar', 0)
            ->select(['b.pk_evento_funcion_boleto', 'b.pk_evento_compra', 'l.evento_localidad', 'b.asiento', 'b.precio', 'b.cortesia', 'b.impresion', 't.evento_compra_tipo', 'u.correo', 'b.adquisicion_fecha']);
    }

    /**
     * Costo de WhatsApp prorrateado entre los boletos web del evento.
     */
    private function whatsappFeePerTicket(int $eventId): float
    {
        $webTickets = DB::table('eventos_funciones_boletos as b')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->where('b.pk_evento', $eventId)
            ->where('b.ocultar', 0)
            ->where('c.pk_evento_compra_tipo', PaymentType::Web->value)
            ->whereNotIn('b.pk_evento_funcion_boleto_status', [6, 7])
            ->count();

        if ($webTickets === 0) {
            return 0.0;
        }

        $webPurchases = EventosCompras::query()->where('pk_evento', $eventId)->where('pk_evento_compra_tipo', PaymentType::Web->value)->count();

        return $webPurchases * (float) config('boxoffice.whatsapp_fee', 15) / $webTickets;
    }
}
