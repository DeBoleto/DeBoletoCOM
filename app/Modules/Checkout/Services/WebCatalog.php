<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\BoxOffice\Services\Checkout\TicketPricing;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\EventosLocalidadesEspeciales;
use App\Modules\Events\Models\EventosLocalidadesPromos;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Tickets\Models\EventoEstacionamientoBoleto;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que el sitio público puede vender: eventos publicados con venta web,
 * funciones abiertas y localidades no cerradas.
 */
class WebCatalog
{
    public function __construct(
        private readonly SaleCatalog $catalog,
        private readonly TicketPricing $pricing,
    ) {}

    public function eventBySlug(string $slug): EventoVista
    {
        $event = EventoVista::query()->where('friendly_url', $slug)->first();

        abort_unless($event !== null && $this->isSellable($event), 404);

        return $event;
    }

    public function event(int $eventId): EventoVista
    {
        $event = EventoVista::query()->find($eventId);

        abort_unless($event !== null && $this->isSellable($event), 404);

        return $event;
    }

    public function eventRow(int $eventId): Eventos
    {
        return Eventos::query()->findOrFail($eventId);
    }

    public function isSellable(EventoVista $event): bool
    {
        return $event->pk_evento_status === Eventos::STATUS_PUBLISHED && (int) $event->getAttribute('bool_venta_web') === 1;
    }

    /**
     * @return Collection<int, EventoFuncionVista>
     */
    public function openFunctions(int $eventId): Collection
    {
        return EventoFuncionVista::query()
            ->where('pk_evento', $eventId)
            ->where('cerrado', 0)
            ->orderBy('fecha')
            ->get();
    }

    public function openFunction(int $eventId, int $functionId): EventoFuncionVista
    {
        return $this->openFunctions($eventId)->firstWhere('pk_evento_funcion', $functionId) ?? abort(404);
    }

    /**
     * @return Collection<int, EventosLocalidades>
     */
    public function openLocalities(int $eventId): Collection
    {
        return $this->catalog->localities($eventId)
            ->reject(fn (EventosLocalidades $locality) => (int) $locality->getAttribute('cerrada') === 1)
            ->values();
    }

    public function stage(EventoVista $event, Collection $localities): ?array
    {
        return $this->catalog->stage($event, $localities);
    }

    public function section(?array $stage, string $sectionId): ?array
    {
        return $this->catalog->section($stage, $sectionId);
    }

    /**
     * @return array<string, string>
     */
    public function seatStatuses(int $eventId, int $functionId, int $localityId): array
    {
        return $this->catalog->seatStatuses($eventId, $functionId, $localityId);
    }

    public function maxTickets(int $eventId): int
    {
        return (int) (config('checkout.max_tickets_events')[$eventId] ?? config('checkout.max_tickets'));
    }

    public function listPrice(EventoVista $event, EventosLocalidades $locality): float
    {
        return round($this->pricing->listPrice($event, $locality), 2);
    }

    /**
     * @return array{count: int, label: string}|null
     */
    public function promotion(EventoVista $event, EventosLocalidades $locality): ?array
    {
        $promotion = $this->pricing->promotionFor($event, $locality);

        if ($promotion <= 0) {
            return null;
        }

        $label = $promotion === (int) config('boxoffice.four_for_two_code', 42)
            ? '4x2'
            : ($promotion + 1).'x'.$promotion;

        return ['count' => $promotion, 'label' => $label];
    }

    /**
     * Tipos de boleto especiales que el sitio ofrece para la localidad.
     *
     * @return list<array{id: int, name: string, price: float}>
     */
    public function specials(EventoVista $event, EventosLocalidades $locality): array
    {
        if (in_array($event->pk_evento, config('checkout.specials.disabled_events'), true)) {
            return [];
        }

        return $locality->especiales
            ->map(fn (EventosLocalidadesEspeciales $special) => [
                'id' => $special->pk_evento_localidad_especiales,
                'name' => $special->evento_localidad,
                'price' => (float) $special->precio,
            ])
            ->values()
            ->all();
    }

    /**
     * En legacy el tipo especial solo se ofrece en cada segundo boleto.
     */
    public function specialsOnEveryTicket(int $eventId): bool
    {
        return in_array($eventId, config('checkout.specials.every_ticket_events'), true);
    }

    public function volumeDiscount(EventosLocalidades $locality): ?EventosLocalidadesPromos
    {
        return EventosLocalidadesPromos::query()->where('pk_evento_localidad', $locality->pk_evento_localidad)->first();
    }

    /**
     * Producto adicional (estacionamiento o Meet & Greet) mientras haya cupo.
     *
     * @return array{label: string, price: float}|null
     */
    public function parkingOffer(Eventos $event): ?array
    {
        $capacity = (int) $event->getAttribute('estacionamiento');

        if ($capacity <= 0) {
            return null;
        }

        $sold = EventoEstacionamientoBoleto::query()->where('pk_evento', $event->pk_evento)->count();

        if ($sold >= $capacity) {
            return null;
        }

        return [
            'label' => config('checkout.parking_labels')[$event->pk_evento] ?? config('checkout.parking_label'),
            'price' => (float) $event->getAttribute('estacionamiento_precio'),
        ];
    }

    /**
     * Texto de reglas de la localidad, sin HTML.
     */
    public function rules(EventosLocalidades $locality): ?string
    {
        if (! (int) $locality->getAttribute('reglas')) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", (string) $locality->getAttribute('reglas_text')))));

        return $text === '' ? null : $text;
    }
}
