<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Support\StageMapParser;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Datos de solo lectura que necesita la pantalla de venta.
 */
class SaleCatalog
{
    public function event(int $eventId): EventoVista
    {
        return EventoVista::query()->findOrFail($eventId);
    }

    /**
     * @return Collection<int, EventoFuncionVista>
     */
    public function functions(int $eventId): Collection
    {
        return EventoFuncionVista::query()->where('pk_evento', $eventId)->orderBy('fecha')->get();
    }

    public function functionBelongsToEvent(int $functionId, int $eventId): bool
    {
        return EventoFuncionVista::query()->whereKey($functionId)->where('pk_evento', $eventId)->exists();
    }

    /**
     * @return Collection<int, EventosLocalidades>
     */
    public function localities(int $eventId): Collection
    {
        return EventosLocalidades::query()
            ->with(['especiales:pk_evento_localidad_especiales,pk_evento_localidad,evento_localidad,precio', 'configuracionMesa'])
            ->where('pk_evento', $eventId)
            ->orderBy('pk_evento_localidad')
            ->get();
    }

    public function locality(int $eventId, int $localityId): EventosLocalidades
    {
        return EventosLocalidades::query()->where('pk_evento', $eventId)->findOrFail($localityId);
    }

    /**
     * Mapa del recinto con el precio de cada sección.
     */
    public function stage(EventoVista $event, Collection $localities): ?array
    {
        $xml = EventoVista::query()->whereKey($event->pk_evento)->value('escenario_xml');

        if (! $xml) {
            return null;
        }

        $zones = $localities->map(fn (EventosLocalidades $locality) => [
            'id' => $locality->pk_evento_localidad,
            'name' => $locality->evento_localidad,
            'originalPrice' => $locality->precio,
        ])->all();

        try {
            return StageMapParser::parseXml($xml, $zones);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function section(?array $stage, string $sectionId): ?array
    {
        return collect($stage['sections'] ?? [])->firstWhere('sectionId', $sectionId);
    }

    /**
     * Estado de cada asiento emitido en la localidad: vendido > bloqueado > libre.
     *
     * @return array<string, string>
     */
    public function seatStatuses(int $eventId, int $functionId, int $localityId): array
    {
        $statuses = [];

        EventosFuncionesBoletos::query()
            ->where('pk_evento', $eventId)
            ->deFuncionYLocalidad($functionId, $localityId)
            ->get(['asiento', 'pk_evento_funcion_boleto_status'])
            ->each(function (EventosFuncionesBoletos $ticket) use (&$statuses) {
                $status = match (TicketStatus::tryFrom($ticket->pk_evento_funcion_boleto_status)) {
                    TicketStatus::Issued, TicketStatus::Pending => 'vendido',
                    TicketStatus::Blocked => 'no_disponible',
                    default => null,
                };

                $current = $statuses[$ticket->asiento] ?? null;

                if ($status === 'vendido' || ($status === 'no_disponible' && $current !== 'vendido')) {
                    $statuses[$ticket->asiento] = $status;
                }
            });

        return $statuses;
    }
}
