<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Exceptions\SeatHeldException;
use App\Modules\Events\Models\EventoReservaBoleto;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Asientos apartados mientras alguien completa su compra.
 *
 * Se guardan en `eventos_reserva_boleto` con el nombre de la localidad como
 * sección (igual que deboletox) para que ambos sistemas vean los asientos en
 * proceso de compra. Una retención caduca a los `checkout.hold_minutes`.
 */
class SeatHolds
{
    /**
     * Aparta asientos numerados de una localidad; todos o ninguno.
     *
     * @param  list<string>  $seats
     *
     * @throws SeatHeldException
     */
    public function holdMany(int $eventId, int $functionId, EventosLocalidades $locality, array $seats, string $owner, int $limit): void
    {
        DB::transaction(function () use ($eventId, $functionId, $locality, $seats, $owner, $limit) {
            EventosLocalidades::query()->whereKey($locality->pk_evento_localidad)->lockForUpdate()->first();

            $sold = EventosFuncionesBoletos::query()
                ->where('pk_evento', $eventId)
                ->deFuncionYLocalidad($functionId, $locality->pk_evento_localidad)
                ->whereIn('asiento', $seats)
                ->value('asiento');

            if ($sold !== null) {
                throw SeatHeldException::for($sold);
            }

            $heldByOthers = $this->active($eventId, $functionId, $locality->evento_localidad)
                ->where('pk_usuario', '!=', $owner)
                ->whereIn('asiento', $seats)
                ->value('asiento');

            if ($heldByOthers !== null) {
                throw SeatHeldException::for($heldByOthers);
            }

            $alreadyMine = $this->active($eventId, $functionId, $locality->evento_localidad)
                ->where('pk_usuario', $owner)
                ->whereNotIn('asiento', $seats)
                ->count();

            if ($alreadyMine + count($seats) > $limit) {
                throw new CheckoutException("Puedes apartar hasta {$limit} boletos por compra.");
            }

            EventoReservaBoleto::query()
                ->where('pk_evento', $eventId)
                ->where('pk_evento_funcion', $functionId)
                ->where('pk_seccion', $locality->evento_localidad)
                ->whereIn('asiento', $seats)
                ->delete();

            $now = $this->now();

            foreach ($seats as $seat) {
                EventoReservaBoleto::query()->insert([
                    'pk_usuario' => $owner,
                    'pk_evento' => $eventId,
                    'pk_evento_funcion' => $functionId,
                    'pk_seccion' => $locality->evento_localidad,
                    'asiento' => $seat,
                    'creacion_fecha' => $now,
                ]);
            }
        });
    }

    public function release(int $eventId, int $functionId, EventosLocalidades $locality, string $seat, string $owner): void
    {
        EventoReservaBoleto::query()
            ->where('pk_evento', $eventId)
            ->where('pk_evento_funcion', $functionId)
            ->where('pk_seccion', $locality->evento_localidad)
            ->where('asiento', $seat)
            ->where('pk_usuario', $owner)
            ->delete();
    }

    public function releaseAll(string $owner): void
    {
        EventoReservaBoleto::releaseFor($owner);
    }

    /**
     * Asientos que otras sesiones tienen apartados en la localidad.
     *
     * @return list<string>
     */
    public function heldByOthers(int $eventId, int $functionId, string $localityName, string $owner): array
    {
        return $this->active($eventId, $functionId, $localityName)
            ->where('pk_usuario', '!=', $owner)
            ->pluck('asiento')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function heldBy(int $eventId, int $functionId, string $localityName, string $owner): array
    {
        return $this->active($eventId, $functionId, $localityName)
            ->where('pk_usuario', $owner)
            ->pluck('asiento')
            ->all();
    }

    /**
     * Renueva las retenciones del dueño (al pasar al pago).
     */
    public function refresh(string $owner): void
    {
        EventoReservaBoleto::query()->where('pk_usuario', $owner)->update(['creacion_fecha' => $this->now()]);
    }

    public function purgeExpired(): int
    {
        return EventoReservaBoleto::query()->where('creacion_fecha', '<', $this->activeSince())->delete();
    }

    private function active(int $eventId, int $functionId, string $localityName)
    {
        return EventoReservaBoleto::query()
            ->where('pk_evento', $eventId)
            ->where('pk_evento_funcion', $functionId)
            ->where('pk_seccion', $localityName)
            ->where('creacion_fecha', '>=', $this->activeSince());
    }

    private function activeSince(): Carbon
    {
        return $this->now()->subMinutes((int) config('checkout.hold_minutes'));
    }

    private function now(): Carbon
    {
        return Carbon::now(LegacyModel::legacyTimezone());
    }
}
