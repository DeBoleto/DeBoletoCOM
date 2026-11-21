<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Checkout\BoxOfficeSeatHolds;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Disponibilidad de todo el mapa del recinto para una función: estado de los
 * asientos ocupados y lugares libres por sección.
 */
class StageVenueController extends Controller
{
    public function __invoke(Request $request, SaleCatalog $catalog, SeatAvailability $availability, BoxOfficeSeatHolds $holds, int $evento): JsonResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        $validated = $request->validate([
            'funcion' => ['required', 'integer'],
        ]);

        $functionId = (int) $validated['funcion'];
        abort_unless($catalog->functionBelongsToEvent($functionId, $evento), 404);

        $event = $catalog->event($evento);
        $localities = $catalog->localities($evento)->keyBy('pk_evento_localidad');
        $stage = $catalog->stage($event, $localities->values());
        $statusesByLocality = [];
        $zones = [];

        foreach ($stage['sections'] ?? [] as $section) {
            $locality = $localities->get((int) $section['localityId']);

            if ($locality === null) {
                continue;
            }

            if ($section['capacity'] !== null) {
                $zones[$section['key']] = [
                    'remaining' => $availability->remaining($evento, $functionId, $locality->pk_evento_localidad, $section['capacity']),
                    'statuses' => (object) [],
                ];

                continue;
            }

            if (! isset($statusesByLocality[$locality->pk_evento_localidad])) {
                $known = $catalog->seatStatuses($evento, $functionId, $locality->pk_evento_localidad);

                foreach ($holds->heldByOthers($evento, $functionId, $locality->evento_localidad) as $seat) {
                    $known[$seat] ??= 'en_proceso';
                }

                $statusesByLocality[$locality->pk_evento_localidad] = $known;
            }

            $known = $statusesByLocality[$locality->pk_evento_localidad];

            $seats = collect($section['rows'])->flatMap(fn (array $row) => $row['seats']);
            $statuses = $seats->mapWithKeys(fn (array $seat) => isset($known[$seat['id']]) ? [$seat['id'] => $known[$seat['id']]] : [])->all();

            $zones[$section['key']] = [
                'remaining' => $seats->filter(fn (array $seat) => ($seat['selectable'] ?? true) && ! isset($statuses[$seat['id']]))->count(),
                'statuses' => (object) $statuses,
            ];
        }

        return response()->json(['zones' => (object) $zones]);
    }
}
