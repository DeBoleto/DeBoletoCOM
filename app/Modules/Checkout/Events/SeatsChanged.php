<?php

namespace App\Modules\Checkout\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Cambió la disponibilidad de asientos de una función (apartado, liberado o
 * vendido). Los mapas abiertos en web, taquilla y dashboard vuelven a
 * consultar su disponibilidad al recibirlo.
 */
class SeatsChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly int $functionId) {}

    public function broadcastOn(): Channel
    {
        return new Channel("funcion.{$this->functionId}");
    }

    public function broadcastAs(): string
    {
        return 'seats.changed';
    }

    /**
     * @return array{functionId: int}
     */
    public function broadcastWith(): array
    {
        return ['functionId' => $this->functionId];
    }
}
