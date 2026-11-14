<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use Illuminate\Support\Collection;

/**
 * Mapa del recinto listo para la compra web y la selección guardada que se
 * restaura sobre él. Lo comparten el proceso de compra y el mapa completo.
 */
class StageView
{
    public function __construct(private readonly WebCatalog $catalog) {}

    /**
     * Mapa del recinto; las secciones de localidades cerradas quedan sin precio.
     */
    public function priced(EventoVista $event, Collection $localities): ?array
    {
        $stage = $this->catalog->stage($event, $localities);

        if ($stage === null) {
            return null;
        }

        $prices = $localities->mapWithKeys(fn (EventosLocalidades $locality) => [
            $locality->pk_evento_localidad => $this->catalog->listPrice($event, $locality),
        ]);

        $stage['sections'] = array_map(function (array $section) use ($prices) {
            $price = $prices->get((int) $section['localityId']);
            $section['available'] = $price !== null;
            $section['price'] = $price === null ? null : [
                'originalPrice' => $section['price']['originalPrice'] ?? $price,
                'discountPrice' => $price < ($section['price']['originalPrice'] ?? $price) ? $price : null,
            ];

            return $section;
        }, $stage['sections']);

        return $stage;
    }

    /**
     * Selección guardada, agrupada por sección del mapa para restaurarla.
     */
    public function previousSelection(WebCart $cart, ?array $stage): array
    {
        $lines = collect($cart->selection->lines);
        $sections = collect($stage['sections'] ?? []);
        $extraChairSuffix = (string) config('boxoffice.rules.extra_chair.seat_suffix');
        $grouped = [];

        foreach ($lines as $line) {
            if (str_ends_with($line->seat, $extraChairSuffix)) {
                continue;
            }

            $generalAdmission = $line->isGeneralAdmission();
            $section = $cart->selection->sectionId !== null
                ? $sections->firstWhere('sectionId', $cart->selection->sectionId)
                : $sections->first(fn (array $candidate) => (int) $candidate['localityId'] === $line->localityId
                    && ($generalAdmission
                        ? $candidate['capacity'] !== null
                        : collect($candidate['rows'])->flatMap(fn (array $row) => $row['seats'])->contains('id', $line->seat)));

            if ($section === null) {
                continue;
            }

            $grouped[$section['sectionId']] ??= ['sectionId' => $section['sectionId'], 'seats' => [], 'quantity' => 0];

            if ($generalAdmission) {
                $grouped[$section['sectionId']]['quantity']++;
            } else {
                $grouped[$section['sectionId']]['seats'][] = $line->seat;
            }
        }

        return [
            'sections' => array_values($grouped),
            'quantities' => $lines->filter(fn (TicketLine $line) => $line->isGeneralAdmission())->countBy('localityId')->all(),
            'parking' => $cart->parking,
        ];
    }
}
