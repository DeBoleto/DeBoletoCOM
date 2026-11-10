<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Registra la compra de taquilla: valida disponibilidad bajo bloqueo, crea la
 * orden, emite los boletos (expandiendo mesas) y calcula totales.
 */
class PurchaseService
{
    public function __construct(
        private readonly SeatAvailability $availability,
        private readonly PurchaseTotals $totals,
        private readonly TicketIssuer $issuer,
    ) {}

    /**
     * @throws SeatsUnavailableException
     */
    public function purchase(CheckoutState $state, EventoVista $event, Usuarios $seller, ?string $comment = null, bool $expandTables = true): EventosCompras
    {
        return DB::transaction(function () use ($state, $event, $seller, $comment, $expandTables) {
            $localityIds = collect($state->lines)->pluck('localityId')->unique()->values();
            $localities = EventosLocalidades::query()
                ->whereIn('pk_evento_localidad', $localityIds)
                ->where('pk_evento', $event->pk_evento)
                ->lockForUpdate()
                ->get()
                ->keyBy('pk_evento_localidad');

            if ($localities->count() !== $localityIds->count() || ! $this->availability->isAvailable($state)) {
                throw new SeatsUnavailableException;
            }

            $salesPeriodId = $this->issuer->salesPeriodId($event->pk_evento);

            $purchase = $this->issuer->newPurchase($seller, $event->pk_evento, $state->functionId);
            $lines = $expandTables ? $this->issuer->expandTables($state, $event, $seller, $salesPeriodId)['lines'] : $state->lines;

            foreach ($lines as $line) {
                $this->issuer->issue($purchase, $state, $line, $salesPeriodId, $seller);
            }

            $mainLocality = $localities->get($state->localityId ?? $localityIds->last());
            $localCommission = collect($lines)
                ->reject(fn (TicketLine $line) => $line->courtesy)
                ->sum(fn (TicketLine $line) => $localities->get($line->localityId)?->comision ?? 0);

            $purchase->forceFill([
                'pk_evento' => $event->pk_evento,
                'pk_evento_funcion' => $state->functionId,
                'pk_evento_compra_tipo' => $state->paymentType->value,
            ]);

            if ($comment !== null) {
                $purchase->comentario = $comment;
            }

            $this->totals->apply(
                $purchase,
                CommissionType::tryFrom((int) $event->pk_evento_comision_tipo),
                $mainLocality,
                $state->paymentType,
                $state->promotion,
                (float) $localCommission,
            );

            return $purchase;
        });
    }
}
