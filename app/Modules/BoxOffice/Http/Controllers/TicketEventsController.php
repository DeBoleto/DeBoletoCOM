<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\CashRegister\CashRegister;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\UsuarioEventoVista;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketEventsController extends Controller
{
    public function __invoke(Request $request, CashRegister $registers): Response
    {
        $events = UsuarioEventoVista::query()
            ->delUsuario($request->user()->pk_usuario)
            ->whereIn('pk_evento_status', [Eventos::STATUS_DRAFT, Eventos::STATUS_PUBLISHED])
            ->where('fin_fecha', '>=', now(config('boxoffice.timezone'))->toDateString())
            ->orderBy('inicio_fecha')
            ->get(['pk_evento', 'evento', 'imagen', 'escenario', 'direccion', 'ciudad', 'estado', 'inicio_fecha', 'inicio_fecha_natural']);

        $register = $registers->current($request->user());

        return Inertia::render('BoxOffice/Tickets/Index', [
            'cashRegister' => $register === null ? null : [
                'name' => $register->caja,
                'openedAt' => $register->apertura_fecha?->format('d/m/Y H:i'),
            ],
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
