<?php

namespace App\Modules\Events\Support;

use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use Illuminate\Support\Collection;

/**
 * Etiquetas de las promociones de un evento para mostrarlas en tarjetas y
 * mapas, con las mismas reglas del cobro (TicketPricing): el "N+1 x N" de la
 * localidad reemplaza al del evento y los porcentajes del evento y de la
 * localidad se suman al precio. Los eventos sin promociones configurados en
 * boxoffice.rules.promotion_disabled_events solo conservan el "N+1 x N" de la
 * localidad.
 */
class EventPromotions
{
    /**
     * @return array{general: string|null, zones: array<int, array{label: string, own: bool}>, badge: array{label: string, scope: 'general'|'zones'}|null}
     */
    public function forEvent(int $eventId): array
    {
        return $this->forEvents([$eventId])[$eventId] ?? self::none();
    }

    /**
     * @param  list<int>  $eventIds
     * @return array<int, array{general: string|null, zones: array<int, array{label: string, own: bool}>, badge: array{label: string, scope: 'general'|'zones'}|null}>
     */
    public function forEvents(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $events = Eventos::query()
            ->whereIn('pk_evento', $eventIds)
            ->get(['pk_evento', 'promocion', 'promocion_porcentaje'])
            ->keyBy('pk_evento');

        $localities = EventosLocalidades::query()
            ->whereIn('pk_evento', $eventIds)
            ->orderBy('pk_evento_localidad')
            ->get(['pk_evento_localidad', 'pk_evento', 'promocion', 'promocion_porcentaje'])
            ->groupBy('pk_evento');

        return $events->map(fn (Eventos $event) => $this->summary($event, $localities->get($event->pk_evento, collect())))->all();
    }

    public static function nxmLabel(int $count): ?string
    {
        if ($count <= 0) {
            return null;
        }

        return $count === (int) config('boxoffice.four_for_two_code', 42) ? '4x2' : ($count + 1).'x'.$count;
    }

    /**
     * @param  Collection<int, EventosLocalidades>  $localities
     */
    private function summary(Eventos $event, Collection $localities): array
    {
        $disabled = in_array($event->pk_evento, config('boxoffice.rules.promotion_disabled_events'), true);
        $eventCount = $disabled ? 0 : (int) $event->promocion;
        $eventPercent = $disabled ? 0.0 : (float) $event->promocion_porcentaje;

        $general = $this->label($eventCount, [$eventPercent]);
        $zones = [];

        foreach ($localities as $locality) {
            $label = $this->label(
                (int) $locality->promocion > 0 ? (int) $locality->promocion : $eventCount,
                [$eventPercent, $disabled ? 0.0 : (float) $locality->promocion_porcentaje],
            );

            if ($label !== null) {
                $zones[$locality->pk_evento_localidad] = ['label' => $label, 'own' => $label !== $general];
            }
        }

        $own = array_values(array_unique(array_column(array_filter($zones, fn (array $zone) => $zone['own']), 'label')));

        $badge = match (true) {
            $general !== null => ['label' => $general, 'scope' => 'general'],
            count($own) === 1 => ['label' => $own[0], 'scope' => 'zones'],
            count($own) > 1 => ['label' => 'Promociones', 'scope' => 'zones'],
            default => null,
        };

        return ['general' => $general, 'zones' => $zones, 'badge' => $badge];
    }

    /**
     * @param  list<float>  $percents
     */
    private function label(int $count, array $percents): ?string
    {
        $parts = array_filter([self::nxmLabel($count)]);
        $discount = 1.0;

        foreach ($percents as $percent) {
            if ($percent > 0) {
                $discount *= 1 - $percent / 100;
            }
        }

        if ($discount < 1.0) {
            $parts[] = '-'.rtrim(rtrim(number_format((1 - $discount) * 100, 1, '.', ''), '0'), '.').'%';
        }

        return $parts === [] ? null : implode(' + ', $parts);
    }

    private static function none(): array
    {
        return ['general' => null, 'zones' => [], 'badge' => null];
    }
}
