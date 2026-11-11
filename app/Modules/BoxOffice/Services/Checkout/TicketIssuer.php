<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Models\EventoLocalidadAsiento;
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
use Illuminate\Support\Str;

/**
 * Crea órdenes y boletos en las tablas legacy. Compartido por la taquilla y
 * la compra web; debe llamarse dentro de la transacción de la compra.
 */
class TicketIssuer
{
    public function salesPeriodId(int $eventId): int
    {
        return (int) EventosPeriodosVentas::query()
            ->where('pk_evento', $eventId)
            ->valueOrFail('pk_evento_periodo_venta');
    }

    public function newPurchase(Usuarios $buyer, int $eventId, int $functionId, ?PaymentType $type = null, array $attributes = []): EventosCompras
    {
        $purchase = new EventosCompras;
        $purchase->forceFill($attributes + [
            'pk_usuario' => $buyer->pk_usuario,
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

    public function issue(
        EventosCompras $purchase,
        CheckoutState $state,
        TicketLine $line,
        int $salesPeriodId,
        Usuarios $buyer,
        bool $printed = false,
        TicketStatus $status = TicketStatus::Pending,
    ): EventosFuncionesBoletos {
        $ticket = new EventosFuncionesBoletos;
        $ticket->forceFill([
            'pk_evento' => $state->eventId,
            'pk_evento_funcion' => $state->functionId,
            'pk_evento_localidad' => $line->localityId,
            'pk_evento_localidad_especiales' => $line->specialLocalityId,
            'pk_evento_periodo_venta' => $salesPeriodId,
            'pk_evento_funcion_boleto_status' => $status->value,
            'pk_evento_compra' => $purchase->pk_evento_compra,
            'asiento' => $line->seat,
            'precio' => $line->price,
            'comision_deboleto' => $line->courtesy ? 0 : $line->serviceFee,
            'comision_servicio' => $line->courtesy ? 0 : $line->paymentFee,
            'cortesia' => $line->courtesy,
            'impresion' => $printed,
            'codigo' => bin2hex(random_bytes(16)),
            'adquisicion_pk_usuario' => $buyer->pk_usuario,
            'adquisicion_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
        ])->save();

        return $ticket;
    }

    /**
     * Venta por mesa: cada lugar vendido se reparte en N asientos con precio y
     * comisiones prorrateados. En venta con mapa la mesa queda marcada como
     * ocupada con un boleto de cortesía en una orden aparte (como en legacy).
     *
     * @return array{lines: list<TicketLine>, markers: list<int>} Líneas a emitir e ids de las órdenes marcadoras.
     */
    public function expandTables(CheckoutState $state, EventoVista $event, Usuarios $buyer, int $salesPeriodId): array
    {
        $mapLocalities = $state->localityId !== null ? [$state->localityId] : array_keys($state->capacities);

        if ($mapLocalities === []) {
            $lines = in_array($event->pk_evento, config('boxoffice.rules.race_table_events'), true)
                ? $this->expandRaceTables($state->lines)
                : $state->lines;

            return ['lines' => $lines, 'markers' => []];
        }

        $tables = EventoLocalidadAsiento::query()
            ->whereIn('pk_evento_localidad', $mapLocalities)
            ->where('cantidad_asientos', '>=', 1)
            ->get()
            ->keyBy('pk_evento_localidad');

        if ($tables->isEmpty()) {
            return ['lines' => $state->lines, 'markers' => []];
        }

        $expanded = [];
        $markers = [];

        foreach ($state->lines as $line) {
            $table = $tables->get($line->localityId);

            if ($table === null || $line->isTableSeat()) {
                $expanded[] = $line;

                continue;
            }

            $marker = $this->newPurchase($buyer, $event->pk_evento, $state->functionId, PaymentType::Courtesy);
            $this->issue($marker, $state, TicketLine::courtesy($line->localityId, $line->localityName, $line->seat), $salesPeriodId, $buyer, printed: true);
            $markers[] = $marker->pk_evento_compra;

            foreach ($table->seatLabels() as $label) {
                $expanded[] = $line->withSeat(
                    $line->seat.'-'.$label,
                    $line->price / $table->cantidad_asientos,
                    $line->serviceFee / $table->cantidad_asientos,
                    $line->paymentFee / $table->cantidad_asientos,
                );
            }
        }

        return ['lines' => $expanded, 'markers' => $markers];
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
