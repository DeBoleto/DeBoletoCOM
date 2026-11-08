<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Evita registrar dos veces la misma operación (doble clic, reintentos).
 * Cada pantalla de confirmación entrega un token de un solo uso.
 */
class ConfirmationGuard
{
    public const IN_PROGRESS = 0;

    private const TTL_MINUTES = 30;

    /**
     * Ejecuta $action una sola vez por llave. Devuelve el id generado por la
     * primera ejecución, o IN_PROGRESS si otra petición sigue procesándola.
     *
     * @param  Closure(): int  $action
     */
    public function once(string $key, Closure $action): int
    {
        $key = 'boxoffice:confirm:'.$key;

        if (! Cache::add($key, self::IN_PROGRESS, now()->addMinutes(self::TTL_MINUTES))) {
            return (int) Cache::get($key, self::IN_PROGRESS);
        }

        try {
            $id = $action();
        } catch (Throwable $exception) {
            Cache::forget($key);

            throw $exception;
        }

        Cache::put($key, $id, now()->addMinutes(self::TTL_MINUTES));

        return $id;
    }
}
