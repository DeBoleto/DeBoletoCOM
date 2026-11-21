<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Checkout\BoxOfficeSeatHolds;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\Checkout\Exceptions\CheckoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Aparta o libera un asiento al tocarlo en el mapa de taquilla o
 * administración, para que nadie más pueda elegirlo mientras se vende.
 */
class StageHoldController extends Controller
{
    public function __invoke(Request $request, SaleCatalog $catalog, BoxOfficeSeatHolds $holds, int $evento): JsonResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        $validated = $request->validate([
            'funcion' => ['required', 'integer'],
            'seccion' => ['required', 'string', 'max:100'],
            'asiento' => ['required', 'string', 'max:50'],
            'accion' => ['required', Rule::in(['apartar', 'liberar'])],
        ]);

        $functionId = (int) $validated['funcion'];
        abort_unless($catalog->functionBelongsToEvent($functionId, $evento), 404);

        $event = $catalog->event($evento);
        $localities = $catalog->localities($evento);
        $section = $catalog->section($catalog->stage($event, $localities), $validated['seccion']);
        $locality = $section ? $localities->firstWhere('pk_evento_localidad', (int) $section['localityId']) : null;
        abort_if($section === null || $locality === null, 404);

        $seat = $validated['asiento'];
        $valid = collect($section['rows'])->flatMap(fn (array $row) => $row['seats'])
            ->contains(fn (array $candidate) => (string) $candidate['id'] === $seat && ($candidate['selectable'] ?? true));

        abort_unless($valid && $section['capacity'] === null, 422, 'Ese asiento no existe en la sección.');

        if ($validated['accion'] === 'liberar') {
            $holds->release($evento, $functionId, $locality, $seat);

            return response()->json(['status' => 'released', 'seat' => $seat]);
        }

        try {
            $holds->hold($evento, $functionId, $locality, $seat);
        } catch (CheckoutException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['status' => 'held', 'seat' => $seat]);
    }
}
