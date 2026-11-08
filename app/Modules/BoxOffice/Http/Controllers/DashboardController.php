<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Queries\DashboardQuery;
use App\Modules\BoxOffice\Support\EventImage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardQuery $query): Response
    {
        $user = $request->user();
        $events = $query->events($user);
        $eventIds = $events->pluck('pk_evento')->map(fn ($id) => (int) $id)->all();

        return Inertia::render('BoxOffice/Dashboard', [
            'events' => $events->map(fn ($event) => $this->present($event))->values(),
            'history' => Inertia::defer(fn () => $query->events($user, past: true)->map(fn ($event) => $this->present($event))->values()),
            'balances' => Inertia::defer(fn () => $query->balances($eventIds)),
            'canSeePayments' => $user->hasPrivilege((int) config('boxoffice.privileges.payments_report')),
        ]);
    }

    private function present(object $event): array
    {
        return [
            'id' => (int) $event->pk_evento,
            'name' => $event->evento,
            'image' => EventImage::url($event->imagen),
            'venue' => $event->direccion ?: $event->escenario,
            'city' => $event->ciudad,
            'state' => $event->estado,
            'date' => $event->inicio_fecha ? substr((string) $event->inicio_fecha, 0, 10) : null,
        ];
    }
}
