<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\TicketAdministration;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta y correcciones de una compra por folio.
 */
class PurchasesController extends Controller
{
    public function index(Request $request): Response
    {
        $folio = $request->integer('folio') ?: null;
        $purchase = $folio ? EventosCompras::query()->with('boletos.localidad:pk_evento_localidad,evento_localidad')->find($folio) : null;

        return Inertia::render('Admin/Purchases/Index', [
            'folio' => $folio,
            'purchase' => $purchase ? [
                'id' => $purchase->pk_evento_compra,
                'event' => EventoVista::query()->whereKey($purchase->pk_evento)->value('evento'),
                'payment' => PaymentType::tryFrom((int) $purchase->pk_evento_compra_tipo)?->label(),
                'isCash' => (int) $purchase->pk_evento_compra_tipo === PaymentType::Cash->value,
                'total' => $purchase->total_compra,
                'commission' => $purchase->total_comision_aplicacion,
                'date' => $purchase->creacion_fecha,
                'tickets' => $purchase->boletos->map(fn ($ticket) => [
                    'id' => $ticket->pk_evento_funcion_boleto,
                    'locality' => $ticket->localidad?->evento_localidad,
                    'seat' => $ticket->isGeneralAdmission() ? null : $ticket->asiento,
                    'price' => $ticket->precio,
                    'printed' => $ticket->impresion,
                ])->values(),
            ] : null,
        ]);
    }

    public function convertToCash(Request $request, TicketAdministration $tickets, int $compra): RedirectResponse
    {
        $tickets->convertToCash(EventosCompras::query()->findOrFail($compra), $request->user());

        return back()->with('success', "La compra {$compra} quedó registrada como pago en efectivo.");
    }
}
