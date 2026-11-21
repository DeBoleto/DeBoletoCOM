<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Exceptions\SeatHeldException;
use App\Modules\Checkout\Support\RedisSeatLocks;
use App\Modules\Events\Models\EventoReservaBoleto;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asientos apartados mientras alguien completa su compra en web o taquilla.
 *
 * Redis decide quién se queda cada asiento de forma atómica. Cada apartado
 * se copia en `eventos_reserva_boleto` con el nombre de la localidad como
 * sección (igual que deboletox) para que ambos sistemas vean los asientos en
 * proceso de compra. Si Redis no responde se usa solo la tabla, bajo
 * bloqueo de la localidad. Una retención caduca a los `checkout.hold_minutes`.
 */
class SeatHolds
{
    public function __construct(
        private readonly RedisSeatLocks $locks,
        private readonly SeatUpdates $updates,
    ) {}

    /**
     * Aparta asientos numerados de una localidad; todos o ninguno. El límite
     * cuenta lo que el dueño ya tiene apartado en todas las zonas de la función.
     *
     * @param  list<string>  $seats
     *
     * @throws SeatHeldException
     */
    public function holdMany(int $eventId, int $functionId, EventosLocalidades $locality, array $seats, string $owner, int $limit): void
    {
        $seats = array_values(array_unique($seats));
        $locked = $this->attempt(fn () => $this->locks->hold($functionId, $locality->evento_localidad, $seats, $owner, $limit, $this->expiresAtMs()));

        if ($locked !== null) {
            [$result, $seat] = $locked;

            if ($result === 'taken') {
                throw SeatHeldException::for($seat);
            }

            if ($result === 'limit') {
                throw new CheckoutException("Puedes apartar hasta {$limit} boletos por compra.");
            }
        }

        try {
            DB::transaction(function () use ($eventId, $functionId, $locality, $seats, $owner, $limit, $locked) {
                if ($locked === null) {
                    EventosLocalidades::query()->whereKey($locality->pk_evento_localidad)->lockForUpdate()->first();
                }

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

                if ($locked === null && $this->heldElsewhere($eventId, $functionId, $locality, $seats, $owner) + count($seats) > $limit) {
                    throw new CheckoutException("Puedes apartar hasta {$limit} boletos por compra.");
                }

                EventoReservaBoleto::query()
                    ->where('pk_evento', $eventId)
                    ->where('pk_evento_funcion', $functionId)
                    ->where('pk_seccion', $locality->evento_localidad)
                    ->whereIn('asiento', $seats)
                    ->where(fn ($query) => $query
                        ->where('pk_usuario', $owner)
                        ->orWhere('creacion_fecha', '<', $this->activeSince()))
                    ->delete();

                $now = $this->now();

                EventoReservaBoleto::query()->insert(array_map(fn (string $seat) => [
                    'pk_usuario' => $owner,
                    'pk_evento' => $eventId,
                    'pk_evento_funcion' => $functionId,
                    'pk_seccion' => $locality->evento_localidad,
                    'asiento' => $seat,
                    'creacion_fecha' => $now,
                ], $seats));
            });
        } catch (Throwable $exception) {
            if ($locked !== null) {
                $this->attempt(fn () => $this->locks->release($functionId, $owner, $this->fields($locality->evento_localidad, $seats)));
            }

            throw $exception;
        }

        $this->updates->changed($functionId);
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

        $this->attempt(fn () => $this->locks->release($functionId, $owner, $this->fields($locality->evento_localidad, [$seat])));
        $this->updates->changed($functionId);
    }

    /**
     * Libera lo que el dueño tenga apartado en la función salvo los asientos
     * indicados (nombre de localidad => asientos).
     *
     * @param  array<string, list<string>>  $keep
     */
    public function releaseExcept(int $eventId, int $functionId, string $owner, array $keep): void
    {
        $deleted = EventoReservaBoleto::query()
            ->where('pk_usuario', $owner)
            ->where('pk_evento', $eventId)
            ->where('pk_evento_funcion', $functionId)
            ->where(function ($query) use ($keep) {
                foreach ($keep as $localityName => $seats) {
                    $query->whereNot(fn ($pair) => $pair
                        ->where('pk_seccion', $localityName)
                        ->whereIn('asiento', $seats === [] ? [''] : $seats));
                }
            })
            ->delete();

        $fields = collect($keep)->flatMap(fn (array $seats, $localityName) => $this->fields((string) $localityName, array_map('strval', $seats)))->values()->all();
        $released = $this->attempt(fn () => $this->locks->release($functionId, $owner, $fields, except: true)) ?? 0;

        if ($deleted > 0 || $released > 0) {
            $this->updates->changed($functionId);
        }
    }

    /**
     * Libera lo que el dueño tenga apartado en otras funciones.
     */
    public function releaseOtherFunctions(string $owner, int $functionId): void
    {
        $functions = $this->ownerFunctions($owner)->reject(fn (int $candidate) => $candidate === $functionId);

        EventoReservaBoleto::query()
            ->where('pk_usuario', $owner)
            ->where('pk_evento_funcion', '!=', $functionId)
            ->delete();

        foreach ($functions as $candidate) {
            $this->attempt(fn () => $this->locks->release($candidate, $owner, [], except: true));
            $this->updates->changed($candidate);
        }
    }

    public function releaseAll(string $owner): void
    {
        $functions = $this->ownerFunctions($owner);

        EventoReservaBoleto::releaseFor($owner);

        foreach ($functions as $functionId) {
            $this->attempt(fn () => $this->locks->release($functionId, $owner, [], except: true));
            $this->updates->changed($functionId);
        }

        $this->attempt(fn () => $this->locks->forgetOwner($owner));
    }

    /**
     * Asientos que otras sesiones tienen apartados en la localidad.
     *
     * @return list<string>
     */
    public function heldByOthers(int $eventId, int $functionId, string $localityName, string $owner): array
    {
        $stored = $this->active($eventId, $functionId, $localityName)
            ->where('pk_usuario', '!=', $owner)
            ->pluck('asiento')
            ->all();

        $locked = array_keys(array_filter(
            $this->attempt(fn () => $this->locks->active($functionId, $localityName)) ?? [],
            fn (string $holder) => $holder !== $owner,
        ));

        return array_values(array_unique([...$stored, ...array_map('strval', $locked)]));
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
        $this->attempt(fn () => $this->locks->refresh($owner, $this->expiresAtMs()));
    }

    public function purgeExpired(): int
    {
        return EventoReservaBoleto::query()->where('creacion_fecha', '<', $this->activeSince())->delete();
    }

    /**
     * Quita de Redis los apartados vencidos y avisa a los mapas abiertos.
     *
     * @return list<int> Funciones que cambiaron.
     */
    public function sweepExpired(): array
    {
        $changed = $this->locks->sweep();

        foreach ($changed as $functionId) {
            $this->updates->changed($functionId);
        }

        return $changed;
    }

    private function active(int $eventId, int $functionId, string $localityName)
    {
        return EventoReservaBoleto::query()
            ->where('pk_evento', $eventId)
            ->where('pk_evento_funcion', $functionId)
            ->where('pk_seccion', $localityName)
            ->where('creacion_fecha', '>=', $this->activeSince());
    }

    /**
     * @param  list<string>  $seats
     */
    private function heldElsewhere(int $eventId, int $functionId, EventosLocalidades $locality, array $seats, string $owner): int
    {
        return EventoReservaBoleto::query()
            ->where('pk_evento', $eventId)
            ->where('pk_evento_funcion', $functionId)
            ->where('creacion_fecha', '>=', $this->activeSince())
            ->where('pk_usuario', $owner)
            ->where(fn ($query) => $query
                ->where('pk_seccion', '!=', $locality->evento_localidad)
                ->orWhereNotIn('asiento', $seats))
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function ownerFunctions(string $owner)
    {
        return EventoReservaBoleto::query()
            ->where('pk_usuario', $owner)
            ->distinct()
            ->pluck('pk_evento_funcion')
            ->map(fn ($functionId) => (int) $functionId)
            ->merge($this->attempt(fn () => $this->locks->functionsOf($owner)) ?? [])
            ->unique()
            ->values();
    }

    /**
     * @param  list<string>  $seats
     * @return list<string>
     */
    private function fields(string $localityName, array $seats): array
    {
        return array_map(fn (string $seat) => RedisSeatLocks::field($localityName, $seat), $seats);
    }

    /**
     * Ejecuta una operación de Redis; si Redis no responde se sigue solo con
     * la base de datos.
     */
    private function attempt(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            Log::warning('Redis de apartados no disponible; se usa solo la base de datos.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    private function expiresAtMs(): int
    {
        return (int) floor(microtime(true) * 1000) + (int) config('checkout.hold_minutes') * 60000;
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
