<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Queries\EventReportQuery;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Support\StageMapParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Aforo: porcentaje vendido por localidad, en lista y sobre el mapa.
 */
class CapacityController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('BoxOffice/Reports/Capacity', [
            'events' => $request->user()->eventos()
                ->publicados()
                ->orderByDesc('eventos.pk_evento')
                ->limit(30)
                ->get(['eventos.pk_evento', 'eventos.evento'])
                ->map(fn (Eventos $event) => ['id' => $event->pk_evento, 'name' => $event->evento])
                ->values(),
        ]);
    }

    public function data(EventReportQuery $query, SaleCatalog $catalog, int $evento): JsonResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        $sold = $query->occupancy($evento);
        $localities = EventosLocalidades::query()->where('pk_evento', $evento)->orderBy('pk_evento_localidad')->get();

        return response()->json([
            'localities' => $localities->map(fn (EventosLocalidades $locality) => [
                'id' => $locality->pk_evento_localidad,
                'name' => $locality->evento_localidad,
                'capacity' => $locality->capacidad,
                'sold' => $sold->get($locality->pk_evento_localidad, 0),
                'percentage' => $locality->capacidad ? round($sold->get($locality->pk_evento_localidad, 0) / $locality->capacidad * 100, 1) : null,
            ])->values(),
            'stage' => $this->stage($catalog, $evento, $localities),
        ]);
    }

    /**
     * Mapa del reporte de localidades (report_locality) o, si no hay, el del evento.
     */
    private function stage(SaleCatalog $catalog, int $eventId, $localities): ?array
    {
        $xml = DB::table('report_locality')->where('event_id', $eventId)->value('xml');

        if ($xml) {
            try {
                return StageMapParser::parseXml($xml);
            } catch (RuntimeException) {
            }
        }

        return $catalog->stage($catalog->event($eventId), $localities);
    }
}
