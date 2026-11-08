<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\BoxOffice\Support\SpanishDate;
use App\Modules\Dashboard\Http\Requests\StoreEventRequest;
use App\Modules\Dashboard\Services\EventCreator;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Users\Models\Usuarios;
use App\Modules\Venues\Models\Escenarios;
use App\Modules\Venues\Models\EscenariosFormatos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EventsController extends Controller
{
    /**
     * Banderas editables desde el listado => columna y valores permitidos.
     */
    private const FLAGS = [
        'venta_web' => ['column' => 'bool_venta_web', 'values' => [0, 1]],
        'promocion' => ['column' => 'promocion', 'values' => [0, 1]],
        'msi' => ['column' => 'msi', 'values' => [0, 1]],
        'transfer' => ['column' => 'transfer', 'values' => [0, 1]],
        'estado' => ['column' => 'pk_evento_status', 'values' => [2, 3]],
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('buscar', ''));

        $events = EventoVista::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('evento', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('pk_evento', ctype_digit($search) ? (int) $search : -1)))
            ->orderByDesc('pk_evento')
            ->paginate(25, ['pk_evento', 'evento', 'escenario', 'ciudad', 'pk_evento_status', 'bool_venta_web', 'promocion', 'msi', 'transfer', 'inicio_fecha'])
            ->withQueryString()
            ->through(fn (EventoVista $event) => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'venue' => "{$event->escenario}, {$event->ciudad}",
                'date' => $event->inicio_fecha ? Carbon::parse($event->inicio_fecha)->format('d/m/Y') : null,
                'published' => (int) $event->pk_evento_status === Eventos::STATUS_PUBLISHED,
                'flags' => [
                    'venta_web' => (int) $event->bool_venta_web === 1,
                    'promocion' => (int) $event->promocion === 1,
                    'msi' => (int) $event->msi === 1,
                    'transfer' => (int) $event->transfer === 1,
                ],
            ]);

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'filters' => ['buscar' => $search],
            'canCreate' => $request->user()->hasPrivilege((int) config('boxoffice.privileges.events_create')),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPrivilege((int) config('boxoffice.privileges.events_create')), 403);

        return Inertia::render('Admin/Events/Create', [
            'promoters' => Usuarios::query()->activos()->where('pk_usuario_tipo', '<=', 3)->orderBy('nombre')->get()
                ->map(fn (Usuarios $user) => ['id' => $user->pk_usuario, 'name' => $user->nombreCompleto()])->values(),
            'venues' => Escenarios::query()->activos()->with('ciudad:pk_ciudad,ciudad')->orderBy('escenario')->get(['pk_escenario', 'escenario', 'pk_ciudad'])
                ->map(fn (Escenarios $venue) => ['id' => $venue->pk_escenario, 'name' => $venue->escenario, 'city' => $venue->ciudad?->ciudad])->values(),
        ]);
    }

    public function formats(int $escenario): JsonResponse
    {
        return response()->json(
            EscenariosFormatos::query()->activos()->where('pk_escenario', $escenario)->orderByDesc('pk_escenario_formato')->get()
                ->map(fn (EscenariosFormatos $format) => [
                    'id' => $format->pk_escenario_formato,
                    'name' => $format->escenario_formato,
                    'localities' => collect($format->localities())->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
                ])->values(),
        );
    }

    public function store(StoreEventRequest $request, EventCreator $creator): RedirectResponse
    {
        $event = $creator->create(
            $request->safe()->only(['pk_usuario', 'pk_escenario', 'pk_escenario_formato', 'evento', 'descripcion', 'comentarios']),
            $request->validated('funciones'),
            array_map('floatval', $request->validated('precios')),
            $request->file('imagen'),
        );

        return redirect()->route('admin.events.show', $event->pk_evento)
            ->with('success', "Evento {$event->evento} creado. Revisa localidades y actívalo cuando esté listo.");
    }

    public function show(int $evento): Response
    {
        $event = EventoVista::query()->findOrFail($evento);

        return Inertia::render('Admin/Events/Show', [
            'event' => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'image' => EventImage::url($event->imagen),
                'venue' => "{$event->escenario}, {$event->ciudad}, {$event->estado}",
                'description' => $event->descripcion,
                'published' => (int) $event->pk_evento_status === Eventos::STATUS_PUBLISHED,
                'url' => $event->friendly_url ?? null,
            ],
            'functions' => EventoFuncionVista::query()->where('pk_evento', $evento)->orderBy('fecha')->get()
                ->map(fn (EventoFuncionVista $function) => "{$function->fecha_natural} {$function->hora_natural} h")->values(),
            'localities' => EventosLocalidades::query()->where('pk_evento', $evento)->orderBy('pk_evento_localidad')->get()
                ->map(fn (EventosLocalidades $locality) => [
                    'id' => $locality->pk_evento_localidad,
                    'name' => $locality->evento_localidad,
                    'price' => $locality->precio,
                    'commission' => $locality->comision,
                    'webCommission' => $locality->comisionWeb,
                    'capacity' => $locality->capacidad,
                ])->values(),
        ]);
    }

    public function updateLocality(Request $request, int $evento, int $localidad): RedirectResponse
    {
        $locality = EventosLocalidades::query()->where('pk_evento', $evento)->findOrFail($localidad);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'commission' => ['required', 'numeric', 'min:0', 'max:100000'],
            'webCommission' => ['required', 'numeric', 'min:0', 'max:100000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $locality->forceFill([
            'evento_localidad' => $validated['name'],
            'precio' => $validated['price'],
            'comision' => $validated['commission'],
            'comisionWeb' => $validated['webCommission'],
            'capacidad' => $validated['capacity'],
        ])->save();

        return back()->with('success', "Localidad {$locality->evento_localidad} actualizada.");
    }

    public function updateFlag(Request $request, int $evento): RedirectResponse
    {
        $validated = $request->validate([
            'flag' => ['required', Rule::in(array_keys(self::FLAGS))],
            'value' => ['required', 'integer'],
        ]);

        $flag = self::FLAGS[$validated['flag']];
        abort_unless(in_array((int) $validated['value'], $flag['values'], true), 422);

        $event = Eventos::query()->findOrFail($evento);
        $event->forceFill([$flag['column'] => (int) $validated['value']])->save();

        return back()->with('success', "Evento {$event->evento} actualizado.");
    }

    public function buyers(int $evento): HttpResponse
    {
        $event = EventoVista::query()->findOrFail($evento);

        $buyers = DB::table('view_eventos_funciones_boletos')
            ->where('pk_evento', $evento)
            ->whereNotNull('adquisicion_correo')
            ->groupBy('adquisicion_correo')
            ->selectRaw('adquisicion_correo, MAX(usuario_nombre_completo) as nombre, MAX(adquisicion_telefono) as telefono, COUNT(*) as boletos')
            ->orderBy('nombre')
            ->get();

        return Pdf::loadView('admin.pdf.buyers', [
            'event' => $event,
            'buyers' => $buyers,
            'date' => SpanishDate::long(now(config('boxoffice.timezone'))),
        ])->setPaper('letter', 'landscape')->stream("compradores-{$evento}.pdf");
    }
}
