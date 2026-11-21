<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Checkout\BoxOfficeSeatHolds;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use App\Modules\Events\Models\EventosLocalidades;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Disponibilidad de una sección del mapa para una función.
 */
class StageInfoController extends Controller
{
    public function __invoke(Request $request, SaleCatalog $catalog, SeatAvailability $availability, BoxOfficeSeatHolds $holds, int $evento): JsonResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        $validated = $request->validate([
            'funcion' => ['required', 'integer'],
            'seccion' => ['required', 'string', 'max:100'],
        ]);

        $functionId = (int) $validated['funcion'];
        abort_unless($catalog->functionBelongsToEvent($functionId, $evento), 404);

        $event = $catalog->event($evento);
        $localities = $catalog->localities($evento);
        $section = $catalog->section($catalog->stage($event, $localities), $validated['seccion']);
        abort_if($section === null, 404);

        $locality = $localities->firstWhere('pk_evento_localidad', (int) $section['localityId']);
        abort_if($locality === null, 404);

        $generalAdmission = $section['capacity'] !== null;

        return response()->json([
            'localityId' => $locality->pk_evento_localidad,
            'price' => $locality->precio,
            'capacity' => $section['capacity'],
            'remaining' => $generalAdmission
                ? $availability->remaining($evento, $functionId, $locality->pk_evento_localidad, $section['capacity'])
                : null,
            'statuses' => $generalAdmission
                ? (object) []
                : (object) $this->statuses($catalog, $holds, $evento, $functionId, $locality),
        ]);
    }

    /**
     * Vendidos y no disponibles, más los asientos que otra persona tiene en
     * proceso de compra.
     *
     * @return array<string, string>
     */
    private function statuses(SaleCatalog $catalog, BoxOfficeSeatHolds $holds, int $evento, int $functionId, EventosLocalidades $locality): array
    {
        $statuses = $catalog->seatStatuses($evento, $functionId, $locality->pk_evento_localidad);

        foreach ($holds->heldByOthers($evento, $functionId, $locality->evento_localidad) as $seat) {
            $statuses[$seat] ??= 'en_proceso';
        }

        return $statuses;
    }
}
