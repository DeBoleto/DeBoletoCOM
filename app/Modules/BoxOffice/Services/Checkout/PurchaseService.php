<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventoLocalidadAsiento;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\EventosPeriodosVentas;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registra la compra de taquilla: valida disponibilidad bajo bloqueo, crea la
 * orden, emite los boletos (expandiendo mesas) y calcula totales.
 */
class PurchaseService
{
    public function __construct(
        private readonly SeatAvailability $availability,
        private readonly PurchaseTotals $totals,
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

            $salesPeriodId = EventosPeriodosVentas::query()
                ->where('pk_evento', $event->pk_evento)
                ->valueOrFail('pk_evento_periodo_venta');

            $purchase = $this->newPurchase($seller, $event->pk_evento, $state->functionId);
            $lines = $expandTables ? $this->expandTables($state, $event, $seller, (int) $salesPeriodId) : $state->lines;

            foreach ($lines as $line) {
                $this->issueTicket($purchase, $state, $line, (int) $salesPeriodId, $seller);
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

    private function newPurchase(Usuarios $seller, int $eventId, int $functionId, ?PaymentType $type = null): EventosCompras
    {
        $purchase = new EventosCompras;
        $purchase->forceFill([
            'pk_usuario' => $seller->pk_usuario,
            'pk_evento' => $eventId,
            'pk_evento_funcion' => $functionId,
            'pk_evento_compra_tipo' => $type?->value,
            'codigo' => (string) Str::uuid(),
            'total_compra' => 0,
            'total_comision_aplicacion' => 0,
            'total_comision_tarjeta' => 0,
            'total_comision_pago_linea' => 0,
        ])->save();

        return $purchase;
    }

    private function issueTicket(EventosCompras $purchase, CheckoutState $state, TicketLine $line, int $salesPeriodId, Usuarios $seller, bool $printed = false): EventosFuncionesBoletos
    {
        $ticket = new EventosFuncionesBoletos;
        $ticket->forceFill([
            'pk_evento' => $state->eventId,
            'pk_evento_funcion' => $state->functionId,
            'pk_evento_localidad' => $line->localityId,
            'pk_evento_localidad_especiales' => $line->specialLocalityId,
            'pk_evento_periodo_venta' => $salesPeriodId,
            'pk_evento_funcion_boleto_status' => TicketStatus::Pending->value,
            'pk_evento_compra' => $purchase->pk_evento_compra,
            'asiento' => $line->seat,
            'precio' => $line->price,
            'comision_deboleto' => $line->courtesy ? 0 : $line->serviceFee,
            'comision_servicio' => $line->courtesy ? 0 : $line->paymentFee,
            'cortesia' => $line->courtesy,
            'impresion' => $printed,
            'codigo' => bin2hex(random_bytes(16)),
            'adquisicion_pk_usuario' => $seller->pk_usuario,
            'adquisicion_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
        ])->save();

        return $ticket;
    }

    /**
     * Venta por mesa: cada lugar vendido se reparte en N asientos con precio y
     * comisiones prorrateados. En venta con mapa la mesa queda marcada como
     * ocupada con un boleto de cortesía en una orden aparte (como en legacy).
     *
     * @return list<TicketLine>
     */
    private function expandTables(CheckoutState $state, EventoVista $event, Usuarios $seller, int $salesPeriodId): array
    {
        if ($state->localityId === null) {
            return in_array($event->pk_evento, config('boxoffice.rules.race_table_events'), true)
                ? $this->expandRaceTables($state->lines)
                : $state->lines;
        }

        $table = EventoLocalidadAsiento::query()->where('pk_evento_localidad', $state->localityId)->first();

        if ($table === null || $table->cantidad_asientos < 1) {
            return $state->lines;
        }

        $expanded = [];

        foreach ($state->lines as $line) {
            if ($line->isTableSeat()) {
                $expanded[] = $line;

                continue;
            }

            $marker = $this->newPurchase($seller, $event->pk_evento, $state->functionId, PaymentType::Courtesy);
            $this->issueTicket($marker, $state, TicketLine::courtesy($line->localityId, $line->localityName, $line->seat), $salesPeriodId, $seller, printed: true);

            foreach ($table->seatLabels() as $label) {
                $expanded[] = $line->withSeat(
                    $line->seat.'-'.$label,
                    $line->price / $table->cantidad_asientos,
                    $line->serviceFee / $table->cantidad_asientos,
                    $line->paymentFee / $table->cantidad_asientos,
                );
            }
        }

        return $expanded;
    }

    /**
     * @param  list<TicketLine>  $lines
     * @return list<TicketLine>
     */
    private function expandRaceTables(array $lines): array
    {
        $tables = EventoLocalidadAsiento::query()
            ->whereIn('pk_evento_localidad', collect($lines)->pluck('localityId')->unique())
            ->get()
            ->keyBy('pk_evento_localidad');

        return collect($lines)->flatMap(function (TicketLine $line, int $index) use ($tables): Collection {
            $seats = max(1, (int) $tables->get($line->localityId)?->cantidad_asientos);

            return collect(range(0, $seats - 1))->map(fn (int $seat) => $line->withSeat(
                'NA_NA'.$index.'_'.$seat,
                $line->price / $seats,
                $line->serviceFee / $seats,
                $line->paymentFee / $seats,
            ));
        })->values()->all();
    }
}
