<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\BoxOffice\Services\Checkout\PurchaseTotals;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones administrativas sobre boletos ya emitidos.
 */
class TicketAdministration
{
    public function __construct(private readonly PurchaseTotals $totals) {}

    /**
     * Elimina un boleto. Si era el último de la compra, elimina la compra; si
     * no, recalcula los totales de la compra.
     */
    public function delete(EventosFuncionesBoletos $ticket, Usuarios $actor): void
    {
        DB::transaction(function () use ($ticket, $actor) {
            $purchase = EventosCompras::query()->lockForUpdate()->find($ticket->pk_evento_compra);
            $snapshot = $ticket->toArray();

            $ticket->delete();

            if ($purchase !== null) {
                $remaining = $purchase->boletos()->count();

                if ($remaining === 0) {
                    $purchase->delete();
                } else {
                    $this->recalculate($purchase, $ticket->pk_evento_localidad);
                }
            }

            activity('boletos')
                ->causedBy($actor)
                ->withProperties(['boleto' => $snapshot])
                ->event('TICKET_ELIMINADO')
                ->log("Eliminó el boleto {$ticket->pk_evento_funcion_boleto}");
        });
    }

    /**
     * Permite volver a imprimir los boletos de una compra.
     */
    public function enablePrinting(EventosCompras $purchase, Usuarios $actor): int
    {
        $count = $purchase->boletos()->update(['impresion' => 0, 'modificacion_pk_usuario' => $actor->pk_usuario]);

        activity('boletos')->causedBy($actor)->event('IMPRESION_HABILITADA')->log("Habilitó la reimpresión de la compra {$purchase->pk_evento_compra}");

        return $count;
    }

    /**
     * Registra la compra como pagada en efectivo y recalcula sus totales.
     */
    public function convertToCash(EventosCompras $purchase, Usuarios $actor): void
    {
        DB::transaction(function () use ($purchase, $actor) {
            $locality = (int) $purchase->boletos()->value('pk_evento_localidad');

            $purchase->forceFill([
                'pk_evento_compra_tipo' => PaymentType::Cash->value,
                'tarjeta_banco' => 0,
                'tarjeta_propietario' => 0,
                'tarjeta_numero' => 0,
                'tarjeta_mes_expiracion' => 0,
                'tarjeta_ano_expiracion' => 0,
            ]);

            $this->recalculate($purchase, $locality);

            activity('compras')->causedBy($actor)->event('COMPRA_A_EFECTIVO')->log("Convirtió la compra {$purchase->pk_evento_compra} a pago en efectivo");
        });
    }

    private function recalculate(EventosCompras $purchase, int $localityId): void
    {
        $event = Eventos::query()->find($purchase->pk_evento);
        $locality = EventosLocalidades::query()->find($localityId);

        if ($event === null || $locality === null) {
            $purchase->save();

            return;
        }

        $this->totals->apply(
            $purchase,
            CommissionType::tryFrom((int) $event->pk_evento_comision_tipo),
            $locality,
            PaymentType::tryFrom((int) $purchase->pk_evento_compra_tipo) ?? PaymentType::Cash,
            0,
            0.0,
        );
    }
}
