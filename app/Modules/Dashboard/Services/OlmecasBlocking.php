<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosFunciones;
use App\Modules\Events\Models\EventosPeriodosVentas;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Genera los bloqueos de temporada de un partido de Olmecas
 * (config/olmecas_blocking.php). Omite asientos que ya existan, así que
 * puede ejecutarse más de una vez sin duplicar boletos.
 */
class OlmecasBlocking
{
    public const CATEGORY = 1;

    /**
     * @return array{purchases: int, tickets: int, skipped: int}
     */
    public function generate(Eventos $event, Usuarios $actor): array
    {
        if ($event->pk_evento_categoria !== self::CATEGORY) {
            throw ValidationException::withMessages(['evento' => 'El evento no pertenece a la categoría Olmecas.']);
        }

        $function = EventosFunciones::query()->where('pk_evento', $event->pk_evento)->orderBy('fecha')->firstOrFail();
        $period = EventosPeriodosVentas::query()->where('pk_evento', $event->pk_evento)->firstOrFail();
        $plan = config('olmecas_blocking');
        $result = ['purchases' => 0, 'tickets' => 0, 'skipped' => 0];

        DB::transaction(function () use ($event, $function, $period, $plan, $actor, &$result) {
            foreach ($event->localidades()->lockForUpdate()->get() as $locality) {
                $zone = $plan[$locality->evento_localidad] ?? null;

                if ($zone === null) {
                    continue;
                }

                $taken = EventosFuncionesBoletos::query()
                    ->where('pk_evento', $event->pk_evento)
                    ->deFuncionYLocalidad($function->pk_evento_funcion, $locality->pk_evento_localidad)
                    ->pluck('asiento')
                    ->flip();

                foreach ([TicketStatus::Pending, TicketStatus::Blocked] as $index => $status) {
                    foreach ($zone[$index] ?? [] as $group) {
                        $seats = array_values(array_filter($group, fn (string $seat) => ! $taken->has($seat)));
                        $result['skipped'] += count($group) - count($seats);

                        if ($seats === []) {
                            continue;
                        }

                        $purchase = $this->purchase($event, $function->pk_evento_funcion, $actor);
                        $result['purchases']++;

                        foreach ($seats as $seat) {
                            $this->ticket($purchase, $locality->pk_evento_localidad, $seat, $period->pk_evento_periodo_venta, $status, $actor);
                            $result['tickets']++;
                        }
                    }
                }
            }
        });

        activity('eventos')->causedBy($actor)->performedOn($event)->withProperties($result)->event('BLOQUEOS_OLMECAS')->log("Generó bloqueos de temporada del evento {$event->pk_evento}");

        return $result;
    }

    private function purchase(Eventos $event, int $functionId, Usuarios $actor): EventosCompras
    {
        $purchase = new EventosCompras;
        $purchase->forceFill([
            'pk_usuario' => $actor->pk_usuario,
            'pk_evento' => $event->pk_evento,
            'pk_evento_funcion' => $functionId,
            'pk_evento_compra_tipo' => PaymentType::Courtesy->value,
            'codigo' => (string) Str::uuid(),
            'total_compra' => 0,
            'total_comision_aplicacion' => 0,
            'total_comision_tarjeta' => 0,
            'total_comision_pago_linea' => 0,
        ])->save();

        return $purchase;
    }

    private function ticket(EventosCompras $purchase, int $localityId, string $seat, int $periodId, TicketStatus $status, Usuarios $actor): void
    {
        (new EventosFuncionesBoletos)->forceFill([
            'pk_evento' => $purchase->pk_evento,
            'pk_evento_funcion' => $purchase->pk_evento_funcion,
            'pk_evento_localidad' => $localityId,
            'pk_evento_periodo_venta' => $periodId,
            'pk_evento_funcion_boleto_status' => $status->value,
            'pk_evento_compra' => $purchase->pk_evento_compra,
            'asiento' => $seat,
            'precio' => 0,
            'codigo' => bin2hex(random_bytes(16)),
            'cortesia' => 1,
            'adquisicion_pk_usuario' => $actor->pk_usuario,
            'adquisicion_fecha' => Carbon::now(LegacyModel::legacyTimezone()),
        ])->save();
    }
}
