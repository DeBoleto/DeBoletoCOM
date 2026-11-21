<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Checkout\Events\SeatsChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa en tiempo real (Reverb) que cambió la disponibilidad de una función.
 * Se envía al confirmar la transacción y un fallo del socket nunca rompe la
 * operación: los mapas también consultan periódicamente.
 */
class SeatUpdates
{
    public function changed(int $functionId): void
    {
        if (in_array(config('broadcasting.default'), [null, 'log', 'null'], true)) {
            return;
        }

        DB::afterCommit(function () use ($functionId) {
            try {
                event(new SeatsChanged($functionId));
            } catch (Throwable $exception) {
                Log::warning('No se pudo avisar el cambio de asientos.', ['function' => $functionId, 'error' => $exception->getMessage()]);
            }
        });
    }
}
