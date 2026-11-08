<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\UsuarioEventoVista;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketEventsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $events = UsuarioEventoVista::query()
            ->delUsuario($request->user()->pk_usuario)
            ->whereIn('pk_evento_status', [Eventos::STATUS_DRAFT, Eventos::STATUS_PUBLISHED])
            ->where('fin_fecha', '>=', now(config('boxoffice.timezone'))->toDateString())
            ->orderBy('inicio_fecha')
            ->get(['pk_evento', 'evento', 'imagen', 'escenario', 'direccion', 'ciudad', 'estado', 'inicio_fecha', 'inicio_fecha_natural']);

        return Inertia::render('BoxOffice/Tickets/Index', [
            'events' => $events->map(fn (UsuarioEventoVista $event) => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'image' => EventImage::url($event->imagen),
                'venue' => $event->escenario,
                'city' => $event->ciudad,
                'state' => $event->estado,
                'date' => $event->inicio_fecha_natural,
            ])->values(),
        ]);
    }
}
