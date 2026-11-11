<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Orders\Enums\PaymentType;
use Illuminate\Validation\ValidationException;

/**
 * Une las secciones elegidas en el mapa (una o varias zonas) en un solo
 * carrito. Con una sección conserva la forma de una sola localidad; con
 * varias, guarda el cupo de cada localidad en `capacities`.
 */
class SectionsStateBuilder
{
    private const SECTION_FIELDS = ['localidad', 'seccion', 'asientos', 'cantidad', 'especiales', 'sillas_extra'];

    /**
     * Normaliza la selección: `secciones` (varias zonas) o los campos de una
     * sola sección. Una sección no puede repetirse.
     *
     * @return list<array<string, mixed>>
     */
    public function requested(array $input): array
    {
        $sections = isset($input['secciones'])
            ? array_values((array) $input['secciones'])
            : [array_intersect_key($input, array_flip(self::SECTION_FIELDS))];

        if ($sections === [] || count(array_unique(array_map(fn (array $section) => (string) ($section['seccion'] ?? ''), $sections))) !== count($sections)) {
            throw ValidationException::withMessages(['secciones' => 'Revisa las zonas de la selección.']);
        }

        return $sections;
    }

    /**
     * @param  list<array{locality: EventosLocalidades, section: array, priced: PricedSelection}>  $sections
     */
    public function build(int $eventId, int $functionId, PaymentType $paymentType, array $sections, ?int $discount = null): CheckoutState
    {
        $lines = [];
        $promotion = 0;
        $promotionTexts = [];
        $gaIndex = [];

        foreach ($sections as ['locality' => $locality, 'priced' => $priced]) {
            foreach ($priced->lines as $line) {
                if ($line->isGeneralAdmission()) {
                    $index = $gaIndex[$locality->pk_evento_localidad] = ($gaIndex[$locality->pk_evento_localidad] ?? -1) + 1;
                    $line = new TicketLine(
                        $line->localityId, $line->localityName, 'NA_NA'.$index, $line->price, $line->serviceFee,
                        $line->paymentFee, $line->courtesy, $line->specialLocalityId, $line->specialLocalityName,
                    );
                }

                $lines[] = $line;
            }

            $promotion = max($promotion, $priced->promotion);

            if ($priced->promotionText !== '') {
                $promotionTexts[] = $priced->promotionText;
            }
        }

        $promotionText = implode(', ', array_unique($promotionTexts));

        if (count($sections) === 1) {
            $section = $sections[0]['section'];

            return new CheckoutState(
                $eventId, $functionId, $paymentType, $lines,
                $sections[0]['locality']->pk_evento_localidad, $section['sectionId'], $section['capacity'],
                $promotion, $discount, $promotionText,
            );
        }

        $capacities = [];

        foreach ($sections as ['locality' => $locality, 'section' => $section]) {
            $id = $locality->pk_evento_localidad;
            $capacities[$id] = $section['capacity'] === null || (array_key_exists($id, $capacities) && $capacities[$id] === null)
                ? null
                : ($capacities[$id] ?? 0) + $section['capacity'];
        }

        return new CheckoutState(
            $eventId, $functionId, $paymentType, $lines,
            promotion: $promotion, discount: $discount, promotionText: $promotionText, capacities: $capacities,
        );
    }
}
