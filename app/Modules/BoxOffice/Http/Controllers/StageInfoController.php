<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Disponibilidad de una sección del mapa para una función.
 */
class StageInfoController extends Controller
{
    public function __invoke(Request $request, SaleCatalog $catalog, SeatAvailability $availability, int $evento): JsonResponse
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
                : $catalog->seatStatuses($evento, $functionId, $locality->pk_evento_localidad),
        ]);
    }
}
