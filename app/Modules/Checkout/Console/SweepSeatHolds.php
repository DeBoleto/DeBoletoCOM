<?php

namespace App\Modules\Checkout\Console;

use App\Modules\Checkout\Services\SeatHolds;
use Illuminate\Console\Command;

/**
 * Quita de Redis los apartados de asientos vencidos y avisa a los mapas
 * abiertos de esas funciones para que los muestren libres.
 */
class SweepSeatHolds extends Command
{
    protected $signature = 'checkout:sweep-holds';

    protected $description = 'Libera los apartados de asientos vencidos en Redis y avisa a los mapas';

    public function handle(SeatHolds $holds): int
    {
        $changed = $holds->sweepExpired();

        if ($changed !== []) {
            $this->info('Funciones con apartados vencidos: '.implode(', ', $changed));
        }

        return self::SUCCESS;
    }
}
